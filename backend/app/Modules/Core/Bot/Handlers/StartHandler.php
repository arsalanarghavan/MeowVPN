<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpPendingApproval;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\AdminGuard;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\KeyboardBuilder;
use App\Modules\Core\Bot\Services\TextService;
use App\Modules\Core\Bot\Services\UserResolver;
use App\Services\Auth\DashboardMagicLinkService;
use App\Services\Auth\DashboardTelegramAuth;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StartHandler
{
    public function __construct(
        protected UserResolver $users,
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected KeyboardBuilder $keyboards,
        protected SettingsStore $settings,
        protected AdminGuard $adminGuard,
        protected DashboardTelegramAuth $dashboardAuth,
        protected DashboardMagicLinkService $magicLink,
    ) {}

    /** @param  array<string, mixed>  $payload */
    public function handle(BotContext $ctx, array $payload): void
    {
        $chatId = (int) ($payload['chat_id'] ?? 0);
        $from = (array) ($payload['from'] ?? []);
        $text = (string) ($payload['text'] ?? '');
        $user = $payload['user'] ?? null;

        if (! $user instanceof SvpUser) {
            $user = $this->users->findOrCreateFromStart($ctx, $from, $text);
        }

        if ($this->maybeSendDashboardMagicLink($ctx, $user, $text, $chatId, (int) ($from['id'] ?? 0))) {
            return;
        }

        if (preg_match('#ref_(\d+)#i', $text, $refMatch)) {
            $inviterId = (int) $refMatch[1];
            if ($inviterId > 0 && (int) ($user->invited_by ?? 0) < 1) {
                $user->invited_by = $inviterId;
                $user->save();
            }
        }

        if (in_array((string) $user->status, ['pending', 'rejected'], true)) {
            if ($ctx->platform === 'bale' && Schema::hasTable('svp_pending_approvals')) {
                $exists = DB::table('svp_pending_approvals')->where('user_id', $user->id)->where('status', 'pending')->exists();
                if (! $exists) {
                    SvpPendingApproval::query()->create([
                        'user_id' => $user->id,
                        'platform' => 'bale',
                        'status' => 'pending',
                        'created_at' => now(),
                    ]);
                    $this->notifyAdminsPending($ctx, $user);
                }
            }
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.approval_wait', $user));

            return;
        }

        $welcomeKey = $ctx->isResellerBot() ? 'msg.welcome.reseller' : 'msg.welcome';
        $welcome = $this->texts->getForUser($welcomeKey, $user, 'Welcome!');
        if ((int) ($user->invited_by ?? 0) > 0) {
            $welcome .= "\n".$this->texts->format(
                $this->texts->getForUser('msg.referral.invited_by', $user, 'Invited by #{id}'),
                ['id' => (int) $user->invited_by]
            );
        }
        $this->runtime->sendMessage($ctx, $chatId, $welcome, [
            'reply_markup' => $this->keyboards->userMainReply($user),
        ]);
    }

    public function handleLang(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $parts = preg_split('/\s+/u', trim($text), 2);
        $sub = strtolower(trim((string) ($parts[1] ?? '')));
        if (! in_array($sub, ['fa', 'en', 'persian', 'english'], true)) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.lang_usage', $user));

            return;
        }
        $user->bot_locale = in_array($sub, ['en', 'english'], true) ? 'en' : 'fa';
        $user->save();
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.lang_changed', $user));
    }

    public function handlePanel(BotContext $ctx, ?SvpUser $user, int $fromId, int $chatId): void
    {
        if (! $user) {
            return;
        }

        $adminIds = app(\App\Modules\Core\Bot\Services\AdminGuard::class);
        if (! $adminIds->isPlatformAdmin($ctx->platform, $fromId)) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.admin.panel_denied', $user));

            return;
        }

        app(\App\Modules\Core\Bot\Handlers\Admin\AdminPanelHandler::class)->sendPanelEntry($ctx, $chatId, $user);
        $user->admin_mode = true;
        $user->save();
    }

    protected function maybeSendDashboardMagicLink(BotContext $ctx, SvpUser $user, string $startText, int $chatId, int $fromId): bool
    {
        $payload = trim((string) preg_replace('#^/start(?:@\S+)?#iu', '', trim($startText)));
        if (strtolower($payload) !== 'dlogin') {
            return false;
        }
        if ($chatId < 1 || $fromId < 1) {
            return true;
        }
        $platform = $ctx->platform;
        $dashUser = $this->dashboardAuth->resolveDashboardUserForPlatform($platform, $fromId);
        if (! $dashUser) {
            $this->runtime->sendMessage(
                $ctx,
                $chatId,
                $this->texts->getForUser('msg.dashboard_login_not_linked', $user, 'Dashboard account not linked.')
            );

            return true;
        }
        $url = $this->magicLink->buildUrl($platform, $fromId);
        $msg = $this->texts->format(
            $this->texts->getForUser(
                'msg.dashboard_login_magic_link',
                $user,
                "One-time dashboard login link (5 min):\n{link}"
            ),
            ['link' => $url]
        );
        $this->runtime->sendMessage($ctx, $chatId, $msg);

        return true;
    }

    protected function notifyAdminsPending(BotContext $ctx, SvpUser $user): void
    {
        $ids = $ctx->isResellerBot()
            ? (array) $this->settings->get('reseller_admin_bale_ids.'.$ctx->resellerSvpUserId, [])
            : $this->adminGuard->platformAdminIds('bale');
        $body = $this->texts->format(
            $this->texts->get('msg.admin.pending_user_notify', 'New Bale user pending: #{id}'),
            ['id' => $user->id]
        );
        foreach ($ids as $adminId) {
            $admin = SvpUser::query()->where('bale_user_id', (int) $adminId)->first();
            if (! $admin) {
                continue;
            }
            $this->runtime->sendMessage($ctx, (int) $adminId, $body, [
                'reply_markup' => [
                    'inline_keyboard' => [[
                        ['text' => $this->texts->getForUser('btn.admin.approve', $admin, 'Approve'), 'callback_data' => 'reg:a:'.$user->id],
                        ['text' => $this->texts->getForUser('btn.admin.reject', $admin, 'Reject'), 'callback_data' => 'reg:r:'.$user->id],
                    ]],
                ],
            ]);
        }
    }
}
