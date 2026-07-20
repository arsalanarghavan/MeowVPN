<?php

namespace App\Modules\XuiPanel\Services;

use App\Modules\L2tp\Services\L2tpProvisionerService;
use App\Modules\PasarGuard\Services\PanelClientFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PanelOrphanClientsService
{
    public const MAX_SCAN = 500;

    public function __construct(
        protected PanelClientFactory $panels,
    ) {}

    /**
     * @return array{ok:bool, orphans?:array<int,array<string,mixed>>, linked?:array<int,string>, message?:string}
     */
    public function scan(int $panelId, int $userId = 0, int $serviceId = 0): array
    {
        if ($panelId < 1) {
            return ['ok' => false, 'message' => 'bad_panel'];
        }
        if ($serviceId > 0) {
            $svc = DB::table('svp_services')->where('id', $serviceId)->first();
            if (! $svc) {
                return ['ok' => false, 'message' => 'service_not_found'];
            }
            $userId = (int) ($svc->user_id ?? 0);
        }
        if ($userId < 1) {
            return ['ok' => false, 'message' => 'bad_user'];
        }

        $linked = $this->linkedEmailsForUser($userId, $panelId);
        $patterns = $this->userMatchPatterns($userId);

        return $this->panels->runWithPanel($panelId, function ($client) use ($linked, $patterns, $panelId) {
            if (! $client->loginWithRetries(6, 300000)) {
                return ['ok' => false, 'message' => 'panel_login'];
            }
            $candidates = $this->panelClientsMatchingPatterns($client, $patterns);
            $orphans = [];
            foreach ($candidates as $row) {
                $em = trim((string) ($row['email'] ?? ''));
                if ($em === '' || isset($linked[$em])) {
                    continue;
                }
                $orphans[] = [
                    'panel_id' => $panelId,
                    'inbound_id' => (int) ($row['inbound_id'] ?? 0),
                    'email' => $em,
                    'remark' => (string) ($row['remark'] ?? ''),
                    'sub_id' => (string) ($row['sub_id'] ?? ''),
                    'used_bytes' => (int) ($row['used_bytes'] ?? 0),
                ];
            }

            return [
                'ok' => true,
                'orphans' => $orphans,
                'linked' => array_values(array_keys($linked)),
            ];
        }) ?? ['ok' => false, 'message' => 'panel_login'];
    }

    /**
     * @param  array<int, string>  $emails
     * @return array{ok:bool, deleted?:int, skipped?:int, errors?:array<int,array<string,mixed>>, message?:string}
     */
    public function deleteUnlinked(int $panelId, array $emails, bool $confirm = false): array
    {
        if ($panelId < 1) {
            return ['ok' => false, 'message' => 'bad_panel'];
        }
        if (! $confirm) {
            return ['ok' => false, 'message' => 'confirm_required'];
        }
        $list = array_values(array_unique(array_filter(array_map(
            static fn ($e) => trim((string) $e),
            $emails
        ), static fn ($e) => $e !== '')));
        if ($list === []) {
            return ['ok' => false, 'message' => 'no_emails'];
        }
        $linkedAny = $this->linkedEmailsGlobal($list);
        $toDelete = array_values(array_diff($list, $linkedAny));
        if ($toDelete === []) {
            return ['ok' => true, 'deleted' => 0, 'skipped' => count($list), 'message' => 'all_linked'];
        }

        return $this->panels->runWithPanel($panelId, function ($client) use ($toDelete, $panelId) {
            if (! $client->loginWithRetries(6, 300000)) {
                return ['ok' => false, 'message' => 'panel_login'];
            }
            $deleted = 0;
            $skipped = 0;
            $errors = [];
            foreach ($toDelete as $em) {
                if ($this->emailIsLinkedInDb($em)) {
                    $skipped++;

                    continue;
                }
                $iid = $this->resolveInboundForEmail($em);
                $uuid = '';
                if ($client->isV3ClientsApi()) {
                    $cl = $client->clientGetV3($em);
                    if (is_array($cl)) {
                        $uuid = (string) ($cl['id'] ?? $cl['uuid'] ?? '');
                    }
                }
                $res = $client->delClient($iid, $uuid, $em);
                $ok = false;
                if ($client instanceof XuiClient) {
                    $ok = $this->panelResponseOk($client, $res);
                } elseif (is_array($res)) {
                    $ok = ! empty($res['ok']);
                }
                if ($ok) {
                    $deleted++;
                    Log::info('orphan_panel_client_deleted', ['panel_id' => $panelId, 'email' => $em]);
                } else {
                    $errors[] = [
                        'email' => $em,
                        'message' => is_array($res) ? ($res['msg'] ?? $res['message'] ?? 'delete_failed') : 'delete_failed',
                    ];
                }
            }

            return [
                'ok' => $errors === [] || $deleted > 0,
                'deleted' => $deleted,
                'skipped' => $skipped,
                'errors' => $errors,
            ];
        }) ?? ['ok' => false, 'message' => 'panel_login'];
    }

    /** @return array<string, true> */
    protected function linkedEmailsForUser(int $userId, int $panelId = 0): array
    {
        $q = DB::table('svp_services')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where('email', '<>', '');
        if ($panelId > 0) {
            $q->where('panel_id', $panelId);
        }
        $out = [];
        foreach ($q->pluck('email') as $em) {
            $em = trim((string) $em);
            if ($em !== '') {
                $out[$em] = true;
            }
        }

        return $out;
    }

    /** @param  array<int, string>  $emails */
    /** @return array<int, string> */
    protected function linkedEmailsGlobal(array $emails): array
    {
        $linked = [];
        foreach (array_chunk($emails, 50) as $chunk) {
            $rows = DB::table('svp_services')
                ->whereNull('deleted_at')
                ->whereIn('email', $chunk)
                ->pluck('email');
            foreach ($rows as $em) {
                $linked[] = trim((string) $em);
            }
        }

        return array_values(array_unique($linked));
    }

    protected function emailIsLinkedInDb(string $email): bool
    {
        $em = trim($email);
        if ($em === '') {
            return false;
        }

        return DB::table('svp_services')
            ->where('email', $em)
            ->whereNull('deleted_at')
            ->exists();
    }

    /** @return array{email_prefix:string, remark_prefix:string} */
    protected function userMatchPatterns(int $userId): array
    {
        return [
            'email_prefix' => 'u'.$userId.'_',
            'remark_prefix' => '#'.$userId.'_',
        ];
    }

    /**
     * @param  array{email_prefix:string, remark_prefix:string}  $patterns
     * @return array<int, array<string, mixed>>
     */
    protected function panelClientsMatchingPatterns(mixed $client, array $patterns): array
    {
        $out = [];
        $ep = (string) ($patterns['email_prefix'] ?? '');
        $rp = (string) ($patterns['remark_prefix'] ?? '');
        $count = 0;

        if ($client->isV3ClientsApi()) {
            $page = 1;
            while ($page <= 20 && $count < self::MAX_SCAN) {
                $batch = $client->clientsListPagedV3($page, 500);
                if (! is_array($batch) || empty($batch['clients'])) {
                    break;
                }
                foreach ($batch['clients'] as $c) {
                    if (! is_array($c) || empty($c['email']) || ! $this->clientMatchesPatterns($c, $ep, $rp)) {
                        continue;
                    }
                    $inboundIds = $c['inboundIds'] ?? $c['inbound_ids'] ?? [];
                    $iid = is_array($inboundIds) && $inboundIds !== [] ? (int) $inboundIds[0] : 0;
                    $used = 0;
                    if (isset($c['usedTraffic']) && is_numeric($c['usedTraffic'])) {
                        $used = (int) $c['usedTraffic'];
                    } elseif (isset($c['traffic']) && is_array($c['traffic'])) {
                        $tr = $c['traffic'];
                        $used = (int) round((float) ($tr['up'] ?? 0) + (float) ($tr['down'] ?? 0));
                    }
                    $out[] = [
                        'email' => (string) $c['email'],
                        'inbound_id' => $iid,
                        'remark' => (string) ($c['comment'] ?? $c['remark'] ?? ''),
                        'sub_id' => (string) ($c['subId'] ?? ''),
                        'used_bytes' => $used,
                    ];
                    $count++;
                    if ($count >= self::MAX_SCAN) {
                        break 2;
                    }
                }
                if (count($batch['clients']) < 500) {
                    break;
                }
                $page++;
            }

            return $out;
        }

        $inbounds = $client->inboundsList();
        if (! is_array($inbounds)) {
            return $out;
        }
        foreach ($inbounds as $inb) {
            if (! is_array($inb) || empty($inb['id'])) {
                continue;
            }
            $iid = (int) $inb['id'];
            $full = $client->inboundGet($iid);
            if (! is_array($full)) {
                continue;
            }
            $settings = $full['settings'] ?? '';
            $dec = is_string($settings) ? json_decode($settings, true) : (is_array($settings) ? $settings : []);
            if (! is_array($dec) || empty($dec['clients']) || ! is_array($dec['clients'])) {
                continue;
            }
            foreach ($dec['clients'] as $c) {
                if (! is_array($c) || empty($c['email']) || ! $this->clientMatchesPatterns($c, $ep, $rp)) {
                    continue;
                }
                $out[] = [
                    'email' => (string) $c['email'],
                    'inbound_id' => $iid,
                    'remark' => (string) ($c['remark'] ?? $c['comment'] ?? ''),
                    'sub_id' => (string) ($c['subId'] ?? ''),
                    'used_bytes' => 0,
                ];
                $count++;
                if ($count >= self::MAX_SCAN) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $c */
    protected function clientMatchesPatterns(array $c, string $ep, string $rp): bool
    {
        $email = strtolower(trim((string) ($c['email'] ?? '')));
        if ($ep !== '' && str_starts_with($email, strtolower($ep))) {
            return true;
        }
        foreach (['remark', 'comment'] as $rk) {
            $text = trim((string) ($c[$rk] ?? ''));
            if ($rp !== '' && $text !== '' && str_starts_with($text, $rp)) {
                return true;
            }
        }

        return false;
    }

    protected function resolveInboundForEmail(string $email): int
    {
        $em = trim($email);
        if ($em === '') {
            return 0;
        }

        return (int) (DB::table('svp_services')
            ->where('email', $em)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->value('inbound_id') ?? 0);
    }

    protected function panelResponseOk(XuiClient $client, mixed $res): bool
    {
        if (! is_array($res)) {
            return false;
        }

        return $client->panelJsonMsg($res) === '' || ! empty($res['success']) || ! empty($res['ok']);
    }
}
