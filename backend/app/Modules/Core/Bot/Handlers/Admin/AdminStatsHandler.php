<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\AdminPermissionService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\TextService;
use App\Modules\Core\Services\Portal\PortalDashboardStatsService;

class AdminStatsHandler extends AbstractAdminHandler
{
    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected PortalDashboardStatsService $stats,
        protected AdminPermissionService $permissions,
    ) {
        parent::__construct($runtime, $texts);
    }

    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.stats', $user, '📈 Stats');
    }

    public function openMonitoring(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $this->sendStats($ctx, $chatId, $user, 0);
    }

    public function handleStatsOffset(BotContext $ctx, SvpUser $user, int $chatId, int $offset): void
    {
        $this->sendStats($ctx, $chatId, $user, $offset);
    }

    protected function sendStats(BotContext $ctx, int $chatId, SvpUser $user, int $offset): void
    {
        $resellerId = $this->permissions->permissionActorId($user);
        $payload = $this->stats->buildPayload($offset, $resellerId);
        $body = (string) ($payload['text'] ?? 'Stats');
        $extra = [];
        $next = $offset + 1;
        $cb = 'pnl:stats:'.$next;
        if (strlen($cb) <= 64) {
            $extra['reply_markup'] = [
                'inline_keyboard' => [[
                    ['text' => '📅 Next day', 'callback_data' => $cb],
                ]],
            ];
        }
        $this->send($ctx, $chatId, $body, $extra);
    }

    protected function sectionIntro(SvpUser $user): string
    {
        $resellerId = $this->permissions->permissionActorId($user);
        $payload = $this->stats->buildPayload(0, $resellerId);

        return (string) ($payload['text'] ?? 'Stats');
    }
}
