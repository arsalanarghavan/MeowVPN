<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\AdminBotScopeService;
use App\Modules\Core\Bot\Services\AdminKeyboardBuilder;
use App\Modules\Core\Bot\Services\AdminPermissionService;
use App\Modules\Core\Bot\Services\AdminReceiptReviewService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\TextService;
use Illuminate\Support\Facades\DB;

class AdminReceiptsHandler extends AbstractAdminHandler
{
    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected AdminReceiptReviewService $review,
        protected AdminPermissionService $permissions,
        protected AdminBotScopeService $scope,
    ) {
        parent::__construct($runtime, $texts);
    }

    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.receipts', $user, '🧾 Receipts');
    }

    public function sendPendingReviewPaged(BotContext $ctx, SvpUser $user, int $chatId, int $offset = 0): void
    {
        $this->review->sendPendingReviewPaged($ctx, $user, $chatId, $offset);
    }

    protected function sectionIntro(SvpUser $user): string
    {
        $scopeIds = $this->scope->botAdminScopeUserIds($user);
        $q = DB::table('svp_receipts')->where('status', 'pending');
        if (is_array($scopeIds)) {
            $q->whereIn('user_id', $scopeIds !== [] ? $scopeIds : [-1]);
        }
        $pending = (int) $q->count();

        return $this->texts->getForUser('msg.admin.tutorial.receipts', $user, "Receipts\nPending: {$pending}");
    }

    public function handleNav(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        if (! $this->matchesNavText($text, $user)) {
            return false;
        }
        if ($this->permissions->mayCallOp($user, 'receipt_review')) {
            $this->sendPendingReviewPaged($ctx, $user, $chatId, 0);
        } else {
            $this->send($ctx, $chatId, $this->sectionIntro($user));
        }

        return true;
    }
}
