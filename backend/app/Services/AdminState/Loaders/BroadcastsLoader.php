<?php

namespace App\Services\AdminState\Loaders;

use App\Models\SvpBroadcast;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use Illuminate\Support\Facades\DB;

class BroadcastsLoader extends AbstractLoader
{
    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsBroadcasts();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        if (! $this->tableExists('svp_broadcasts')) {
            return;
        }

        $p = $ctx->page('broadcasts');
        $q = SvpBroadcast::query()->orderByDesc('id');
        if ($ctx->isReseller && $ctx->actorSvpUserId > 0) {
            $q->where('owner_svp_user_id', $ctx->actorSvpUserId);
        } else {
            $q->where('owner_svp_user_id', 0);
        }

        $total = (clone $q)->count();
        $result->setTotal('broadcasts', $total);

        $aggregates = [];
        if ($this->tableExists('svp_broadcast_queue')) {
            $aggregates = DB::table('svp_broadcast_queue')
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status')
                ->all();
        }

        $result->merge([
            'broadcasts' => $this->fetchRows((clone $q)->offset($p['offset'])->limit($p['per_page'])),
            'broadcastQueueAggregates' => $aggregates,
        ]);
    }
}
