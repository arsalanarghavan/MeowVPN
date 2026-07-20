<?php

namespace App\Services\AdminState\Loaders;

use App\Modules\Telegram\Services\TelegramMirrorBotService;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use Illuminate\Support\Facades\DB;

class BotsLoader extends AbstractLoader
{
    public function __construct(protected TelegramMirrorBotService $mirrors) {}

    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsBots();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        if ($this->tableExists('svp_reseller_bot_profiles')) {
            $p = $ctx->page('bots');
            $q = DB::table('svp_reseller_bot_profiles')->orderByDesc('id');
            if ($ctx->isReseller && $ctx->actorSvpUserId > 0) {
                $q->where('reseller_svp_user_id', $ctx->actorSvpUserId);
            }

            $total = (clone $q)->count();
            $result->setTotal('bots', $total);
            $rows = (clone $q)->offset($p['offset'])->limit($p['per_page'])->get()
                ->map(fn ($r) => (array) $r)
                ->all();

            $result->merge(['botsList' => $rows]);
        }

        if (! $ctx->isReseller && $ctx->activeTab === 'bots') {
            $pMirrors = $ctx->page('mirrors');
            $mirrors = $this->mirrors->listPaginated($pMirrors['page'], $pMirrors['per_page']);
            $result->setTotal('mirrors', (int) ($mirrors['total'] ?? 0));
            $result->merge(['telegramMirrorsList' => $mirrors['rows'] ?? []]);
        }
    }
}
