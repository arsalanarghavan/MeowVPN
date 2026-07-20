<?php

namespace App\Modules\XuiPanel\Services;

use App\Modules\PasarGuard\Services\PanelClientFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Batched backfill of svp_services.panel_limit_ip from panel clients (WP: backfill_panel_limit_ip_batch).
 */
class PanelLimitIpBackfillService
{
    public const DONE_KEY = 'panel_limit_ip_backfill_v1_done';

    public const CURSOR_KEY = 'panel_limit_ip_backfill_cursor';

    public const BATCH_SIZE = 20;

    public function __construct(protected PanelClientFactory $panels) {}

    public function isDone(): bool
    {
        if (! Schema::hasTable('svp_settings')) {
            return true;
        }

        return (string) DB::table('svp_settings')->where('key_name', self::DONE_KEY)->value('value') === '1';
    }

    /** @return array{done:bool, processed:int, remaining:int} */
    public function runBatch(): array
    {
        if ($this->isDone() || ! Schema::hasTable('svp_services')) {
            return ['done' => true, 'processed' => 0, 'remaining' => 0];
        }

        $afterId = max(0, (int) $this->settingValue(self::CURSOR_KEY, '0'));
        $limit = max(1, min(50, self::BATCH_SIZE));

        $rows = DB::table('svp_services')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('panel_limit_ip')->orWhere('panel_limit_ip', '<', 1);
            })
            ->where('email', '!=', '')
            ->where(function ($q) {
                $q->whereNull('provision_type')->orWhere('provision_type', '!=', 'l2tp');
            })
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'panel_id', 'inbound_id', 'email']);

        if ($rows->isEmpty()) {
            $this->markDone();

            return ['done' => true, 'processed' => 0, 'remaining' => 0];
        }

        $defLim = max(1, (int) (DB::table('svp_settings')->where('key_name', 'default_concurrent_users')->value('value') ?? 2));
        $byPanel = [];
        foreach ($rows as $row) {
            $pid = max(1, (int) ($row->panel_id ?? 1));
            $byPanel[$pid] ??= [];
            $byPanel[$pid][] = $row;
        }

        foreach ($byPanel as $panelId => $svcRows) {
            $this->panels->runWithPanel((int) $panelId, function ($client) use ($svcRows, $defLim) {
                $loggedIn = $client->loginWithRetries(4, 200000);
                foreach ($svcRows as $svc) {
                    $lim = 0;
                    if ($loggedIn && method_exists($client, 'clientGetV3')) {
                        $cl = $client->clientGetV3((string) $svc->email);
                        if (is_array($cl)) {
                            $lim = (int) ($cl['limitIp'] ?? 0);
                        }
                    }
                    if ($lim < 1) {
                        $lim = $defLim;
                    }
                    if ($lim > 0) {
                        DB::table('svp_services')->where('id', (int) $svc->id)->update(['panel_limit_ip' => $lim]);
                    }
                }
            });
        }

        $lastId = (int) ($rows->last()->id ?? 0);
        if ($lastId > 0) {
            $this->setSetting(self::CURSOR_KEY, (string) $lastId);
        }

        $remaining = (int) DB::table('svp_services')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('panel_limit_ip')->orWhere('panel_limit_ip', '<', 1);
            })
            ->where('email', '!=', '')
            ->where(function ($q) {
                $q->whereNull('provision_type')->orWhere('provision_type', '!=', 'l2tp');
            })
            ->count();

        if ($remaining < 1) {
            $this->markDone();

            return ['done' => true, 'processed' => $rows->count(), 'remaining' => 0];
        }

        return ['done' => false, 'processed' => $rows->count(), 'remaining' => $remaining];
    }

    protected function markDone(): void
    {
        $this->setSetting(self::DONE_KEY, '1');
        if (Schema::hasTable('svp_settings')) {
            DB::table('svp_settings')->where('key_name', self::CURSOR_KEY)->delete();
        }
    }

    protected function settingValue(string $key, string $default = ''): string
    {
        if (! Schema::hasTable('svp_settings')) {
            return $default;
        }

        return (string) (DB::table('svp_settings')->where('key_name', $key)->value('value') ?? $default);
    }

    protected function setSetting(string $key, string $value): void
    {
        if (! Schema::hasTable('svp_settings')) {
            return;
        }
        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => $key],
            ['value' => $value, 'updated_at' => now()]
        );
    }
}
