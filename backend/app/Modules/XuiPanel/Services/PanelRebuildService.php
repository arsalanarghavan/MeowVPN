<?php

namespace App\Modules\XuiPanel\Services;

use App\Services\Commerce\ServiceProvisioner;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;

class PanelRebuildService
{
    private const BATCH_SIZE = 40;

    public function __construct(
        protected XuiClient $xui,
        protected ServiceProvisioner $provisioner,
        protected ConfigsSyncService $configs,
        protected SettingsStore $settings,
    ) {}

    /** @param  array<string, mixed>  $opts */
    public function rebuildAll(array $opts = []): array
    {
        $panelId = max(0, (int) ($opts['panel_id'] ?? 0));
        $dryRun = ! empty($opts['dry_run']);
        $offset = max(0, (int) ($opts['offset'] ?? 0));
        $limit = max(1, min(50, (int) ($opts['limit'] ?? self::BATCH_SIZE)));

        $totals = ['created' => 0, 'patched' => 0, 'skipped' => 0, 'failed' => 0];
        $errors = [];
        $touchedPanels = [];
        $inboundMap = $this->inboundMap($panelId, $opts);

        $rows = $this->fetchBatch($panelId, $offset, $limit);
        $total = $this->countServices($panelId);

        foreach ($rows as $svc) {
            $one = $this->rebuildOne($svc, $dryRun, $inboundMap);
            $act = (string) ($one['action'] ?? 'failed');
            if (isset($totals[$act])) {
                $totals[$act]++;
            } else {
                $totals['failed']++;
            }
            if ($act === 'failed' && count($errors) < 20) {
                $errors[] = [
                    'service_id' => (int) ($svc->id ?? 0),
                    'email' => (string) ($svc->email ?? ''),
                    'panel_id' => (int) ($svc->panel_id ?? 0),
                    'reason' => (string) ($one['reason'] ?? $one['message'] ?? 'unknown'),
                ];
            }
            if (! $dryRun && in_array($act, ['created', 'patched'], true)) {
                $touchedPanels[max(1, (int) ($svc->panel_id ?? 1))] = true;
            }
        }

        $nextOffset = $offset + count($rows);
        $done = $nextOffset >= $total || count($rows) < 1;

        if ($done && ! $dryRun && $touchedPanels !== []) {
            foreach (array_keys($touchedPanels) as $pid) {
                $this->configs->syncPanelToDb((int) $pid, true);
            }
        }

        return svp_ok([
            'dry_run' => $dryRun,
            'totals' => $totals,
            'errors' => $errors,
            'done' => $done,
            'next_offset' => $done ? $total : $nextOffset,
            'total' => $total,
            'processed' => count($rows),
        ]);
    }

    /**
     * @param  array<string, int>|null  $inboundMap
     * @return array{action:string, reason?:string, message?:string}
     */
    protected function rebuildOne(object $svc, bool $dryRun, ?array $inboundMap): array
    {
        if (! $this->isRebuildable($svc)) {
            return ['action' => 'skipped', 'reason' => 'not_xray'];
        }

        $svcArr = $this->resolveInbound((array) $svc, $inboundMap);
        $panelId = max(1, (int) ($svcArr['panel_id'] ?? 1));
        $email = trim((string) ($svcArr['email'] ?? ''));
        $iid = (int) ($svcArr['inbound_id'] ?? 0);
        if ($iid < 1) {
            return ['action' => 'failed', 'reason' => 'inbound_unmapped'];
        }

        if ($dryRun) {
            $exists = $this->panelClientExists($panelId, $iid, $email);

            return ['action' => $exists ? 'patched' : 'created'];
        }

        if ($this->panelClientExists($panelId, $iid, $email)) {
            $res = $this->runOnPanel($panelId, function ($client) use ($svcArr) {
                if (! $client->loginWithRetries()) {
                    return ['ok' => false, 'message' => 'login_fail'];
                }

                return $client->syncServiceRowToPanel($svcArr);
            });
            if (! empty($res['ok'])) {
                return ['action' => 'patched'];
            }

            return ['action' => 'failed', 'reason' => 'patch_failed', 'message' => (string) ($res['message'] ?? '')];
        }

        $add = $this->provisioner->addClientFromServiceRow($svcArr);
        if (! empty($add['ok'])) {
            if (($add['action'] ?? '') === 'already_on_panel') {
                $res = $this->runOnPanel($panelId, function ($client) use ($svcArr) {
                    if (! $client->loginWithRetries()) {
                        return ['ok' => false, 'message' => 'login_fail'];
                    }

                    return $client->syncServiceRowToPanel($svcArr);
                });
                if (! empty($res['ok'])) {
                    return ['action' => 'patched'];
                }

                return ['action' => 'failed', 'reason' => 'patch_after_exists', 'message' => (string) ($res['message'] ?? '')];
            }

            return ['action' => 'created'];
        }

        return ['action' => 'failed', 'reason' => (string) ($add['reason'] ?? 'add_failed'), 'message' => (string) ($add['detail'] ?? '')];
    }

    protected function panelClientExists(int $panelId, int $inboundId, string $email): bool
    {
        $found = false;
        $this->runOnPanel($panelId, function ($client) use ($inboundId, $email, &$found) {
            if (! $client->loginWithRetries(4, 280000)) {
                return null;
            }
            $inbound = $client->inboundGet($inboundId);
            $found = is_array($client->inboundClientByEmail($inbound, $email));

            return null;
        });

        return $found;
    }

    protected function runOnPanel(int $panelId, callable $fn): mixed
    {
        if (function_exists('svp_modules') && svp_modules()->isEnabled('pasarguard') && class_exists(\App\Modules\PasarGuard\Services\PanelClientFactory::class)) {
            return app(\App\Modules\PasarGuard\Services\PanelClientFactory::class)->runWithPanel($panelId, $fn);
        }

        return $this->xui->runWithPanel($panelId, function () use ($fn) {
            return $fn($this->xui);
        });
    }

    /** @param  array<string, mixed>  $opts */
    protected function inboundMap(int $panelId, array $opts): ?array
    {
        if (isset($opts['inbound_map']) && is_array($opts['inbound_map'])) {
            return $opts['inbound_map'];
        }
        if ($panelId < 1) {
            return null;
        }
        $raw = $this->settings->get('panel_inbound_map_'.$panelId);
        if (is_string($raw)) {
            $dec = json_decode($raw, true);

            return is_array($dec) ? $dec : null;
        }

        return is_array($raw) ? $raw : null;
    }

    /** @param  array<string, mixed>  $svc */
    protected function resolveInbound(array $svc, ?array $map): array
    {
        if ($map === null || $map === []) {
            return $svc;
        }
        $dbIid = (int) ($svc['inbound_id'] ?? 0);
        if (isset($map[$dbIid]) && (int) $map[$dbIid] > 0) {
            $svc['inbound_id'] = (int) $map[$dbIid];
        } elseif (isset($map[(string) $dbIid]) && (int) $map[(string) $dbIid] > 0) {
            $svc['inbound_id'] = (int) $map[(string) $dbIid];
        }

        return $svc;
    }

    protected function isRebuildable(object $svc): bool
    {
        $stype = strtolower(trim((string) ($svc->service_type ?? 'xray')));
        if ($stype === 'l2tp') {
            return false;
        }
        $email = trim((string) ($svc->email ?? ''));

        return $email !== '' && (int) ($svc->inbound_id ?? 0) > 0;
    }

    /** @return array<int, object> */
    protected function fetchBatch(int $panelId, int $offset, int $limit): array
    {
        $q = DB::table('svp_services')
            ->whereNull('deleted_at')
            ->where('inbound_id', '>', 0)
            ->where('email', '!=', '')
            ->where(function ($query) {
                $query->whereNull('service_type')
                    ->orWhere('service_type', '')
                    ->orWhere('service_type', 'xray');
            });
        if ($panelId > 0) {
            $q->where('panel_id', $panelId);
        }

        return $q->orderBy('id')->offset($offset)->limit($limit)->get()->all();
    }

    protected function countServices(int $panelId): int
    {
        $q = DB::table('svp_services')
            ->whereNull('deleted_at')
            ->where('inbound_id', '>', 0)
            ->where('email', '!=', '')
            ->where(function ($query) {
                $query->whereNull('service_type')
                    ->orWhere('service_type', '')
                    ->orWhere('service_type', 'xray');
            });
        if ($panelId > 0) {
            $q->where('panel_id', $panelId);
        }

        return (int) $q->count();
    }
}
