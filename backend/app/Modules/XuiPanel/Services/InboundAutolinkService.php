<?php

namespace App\Modules\XuiPanel\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** WP parity: email exact match + remark fuzzy (contains / normalized). */
class InboundAutolinkService
{
    public function run(int $panelId, int $inboundId): array
    {
        if ($inboundId < 1) {
            return ['panel_id' => $panelId, 'inbound_id' => $inboundId, 'linked' => 0];
        }

        $linked = 0;

        if (Schema::hasTable('svp_panel_inbound_clients')) {
            $clients = DB::table('svp_panel_inbound_clients')
                ->where('panel_id', $panelId)
                ->where('inbound_id', $inboundId)
                ->get(['email', 'remark']);

            foreach ($clients as $client) {
                $email = trim((string) ($client->email ?? ''));
                if ($email === '') {
                    continue;
                }
                $remark = $this->normalizeRemark((string) ($client->remark ?? ''));
                $q = DB::table('svp_services')
                    ->whereNull('deleted_at')
                    ->where('panel_id', $panelId)
                    ->where(function ($sub) use ($email, $remark) {
                        $sub->where('email', $email);
                        if ($remark !== '') {
                            $sub->orWhere('remark', $remark);
                            $sub->orWhere('remark', 'like', '%'.$remark.'%');
                            $sub->orWhereRaw('LOWER(remark) LIKE ?', ['%'.mb_strtolower($remark).'%']);
                        }
                    })
                    ->where(function ($q) use ($inboundId) {
                        $q->where('inbound_id', 0)->orWhereNull('inbound_id')->orWhere('inbound_id', '!=', $inboundId);
                    });
                $linked += (int) $q->update(['inbound_id' => $inboundId]);
            }
        }

        if (Schema::hasTable('svp_plans')) {
            $linked += (int) DB::table('svp_plans')
                ->where('panel_id', $panelId)
                ->where(function ($q) {
                    $q->where('inbound_id', 0)->orWhereNull('inbound_id');
                })
                ->limit(50)
                ->update(['inbound_id' => $inboundId]);
        }

        return ['panel_id' => $panelId, 'inbound_id' => $inboundId, 'linked' => $linked];
    }

    private function normalizeRemark(string $remark): string
    {
        $remark = trim($remark);
        $remark = preg_replace('/\s+/u', ' ', $remark) ?? $remark;

        return $remark;
    }
}
