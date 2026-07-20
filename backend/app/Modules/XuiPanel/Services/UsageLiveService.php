<?php

namespace App\Modules\XuiPanel\Services;

use App\Modules\PasarGuard\Services\PanelClientFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UsageLiveService
{
    public const MAX_BATCH_ITEMS = 50;

    public function __construct(
        protected PanelClientFactory $panels,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{ok:bool, items:array<int,array<string,mixed>>, message?:string}
     */
    public function refreshTrafficBatch(int $panelId, array $items): array
    {
        if ($panelId < 1) {
            return ['ok' => false, 'message' => 'bad_panel', 'items' => []];
        }
        $items = array_slice($items, 0, self::MAX_BATCH_ITEMS);
        if ($items === []) {
            return ['ok' => true, 'items' => []];
        }

        $out = $this->panels->runWithPanel($panelId, function ($client) use ($panelId, $items) {
            if (! $client->loginWithRetries(6, 300000)) {
                return ['ok' => false, 'message' => 'login_failed', 'items' => []];
            }
            $results = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $iid = (int) ($item['inbound_id'] ?? 0);
                $em = trim((string) ($item['email'] ?? ''));
                $ls = (int) ($item['linked_service_id'] ?? 0);
                if ($iid < 1 || $em === '') {
                    continue;
                }
                $tr = $this->fetchTrafficOnlyLoggedIn($client, $em);
                if (empty($tr['ok'])) {
                    $results[] = ['inbound_id' => $iid, 'email' => $em, 'usage_live' => false];

                    continue;
                }
                $this->persistTrafficRow($panelId, $iid, $em, $tr, $ls);
                $results[] = [
                    'inbound_id' => $iid,
                    'email' => $em,
                    'used_bytes' => (int) ($tr['used_bytes'] ?? 0),
                    'limit_bytes' => (int) ($tr['limit_bytes'] ?? 0),
                    'usage_live' => true,
                ];
            }

            return ['ok' => true, 'items' => $results];
        });

        return is_array($out) ? $out : ['ok' => false, 'message' => 'unknown', 'items' => []];
    }

    /** @return array{ok:bool, used_bytes?:int, limit_bytes?:int, up_bytes?:int, down_bytes?:int} */
    protected function fetchTrafficOnlyLoggedIn(mixed $client, string $email): array
    {
        $em = trim($email);
        if ($em === '') {
            return ['ok' => false];
        }
        $tr = $client->getClientTraffics($em);
        $obj = is_array($tr) && isset($tr['obj']) && is_array($tr['obj']) ? $tr['obj'] : (is_array($tr) ? $tr : []);
        if ($obj === []) {
            return ['ok' => false];
        }
        $up = (float) ($obj['up'] ?? 0);
        $down = (float) ($obj['down'] ?? 0);
        $used = (int) round($up + $down);
        $lim = (int) ($obj['total'] ?? 0);
        if ($lim < 1 && isset($obj['totalGB'])) {
            $gb = (float) $obj['totalGB'];
            if ($gb > 0) {
                $lim = (int) round($gb * 1073741824);
            }
        }

        return [
            'ok' => true,
            'used_bytes' => $used,
            'limit_bytes' => max(0, $lim),
            'up_bytes' => (int) round($up),
            'down_bytes' => (int) round($down),
        ];
    }

    /** @param  array<string, mixed>  $tr */
    protected function persistTrafficRow(int $panelId, int $inboundId, string $email, array $tr, int $linkedServiceId): void
    {
        if (Schema::hasTable('svp_panel_inbound_clients')) {
            DB::table('svp_panel_inbound_clients')->updateOrInsert(
                ['panel_id' => $panelId, 'inbound_id' => $inboundId, 'email' => $email],
                [
                    'used_bytes' => (int) ($tr['used_bytes'] ?? 0),
                    'limit_bytes' => (int) ($tr['limit_bytes'] ?? 0),
                    'updated_at' => now(),
                ]
            );
        }
        if ($linkedServiceId > 0) {
            DB::table('svp_services')->where('id', $linkedServiceId)->update([
                'used_traffic' => (int) ($tr['used_bytes'] ?? 0),
            ]);
        }
    }
}
