<?php

namespace App\Services\AdminState\Loaders;

use App\Models\SvpDiscountCode;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use Illuminate\Support\Facades\DB;

class DiscountsLoader extends AbstractLoader
{
    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsDiscounts();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        if (! $this->tableExists('svp_discount_codes')) {
            return;
        }

        $p = $ctx->page('discounts');
        $q = SvpDiscountCode::query()->orderByDesc('id');
        $ownerId = $ctx->isReseller ? $ctx->actorSvpUserId : ($ctx->resellerContextId > 0 ? $ctx->resellerContextId : 0);
        $q->where('owner_svp_user_id', $ownerId);

        $total = (clone $q)->count();
        $result->setTotal('discounts', $total);
        $rows = $this->fetchRows((clone $q)->offset($p['offset'])->limit($p['per_page']));

        $summary = [
            'total_redemptions' => 0,
            'total_discount_toman' => 0.0,
            'active_codes' => (clone $q)->where('active', 1)->count(),
        ];
        if ($this->tableExists('svp_discount_redemptions')) {
            $agg = DB::table('svp_discount_redemptions as d')
                ->join('svp_discount_codes as c', 'c.id', '=', 'd.code_id')
                ->where('c.owner_svp_user_id', $ownerId)
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(d.discount_amount),0) as total')
                ->first();
            $summary['total_redemptions'] = (int) ($agg->cnt ?? 0);
            $summary['total_discount_toman'] = (float) ($agg->total ?? 0);
        }

        $result->merge([
            'discountCodes' => $rows,
            'discountUsageSummary' => $summary,
        ]);
    }
}
