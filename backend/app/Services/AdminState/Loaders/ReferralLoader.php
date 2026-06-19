<?php

namespace App\Services\AdminState\Loaders;

use App\Models\SvpReferralEvent;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use Illuminate\Support\Facades\DB;

class ReferralLoader extends AbstractLoader
{
    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsReferral();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        if (! $this->tableExists('svp_referral_events')) {
            return;
        }

        $p = $ctx->page('referralEvents');
        $q = SvpReferralEvent::query()->orderByDesc('id');
        $total = (clone $q)->count();
        $result->setTotal('referralEvents', $total);

        $stats = DB::table('svp_referral_events')
            ->selectRaw('COUNT(*) as events_total, COALESCE(SUM(amount),0) as amount_total')
            ->first();

        $result->merge([
            'referralEvents' => $this->fetchRows((clone $q)->offset($p['offset'])->limit($p['per_page'])),
            'referralStats' => [
                'events_total' => (int) ($stats->events_total ?? 0),
                'amount_total' => (float) ($stats->amount_total ?? 0),
            ],
        ]);
    }
}
