<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Mandatory channel membership gate (Telegram / Bale) — WP Required_Channel parity.
 */
class RequiredChannelService
{
    public function __construct(
        protected SettingsStore $settings,
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected KeyboardBuilder $keyboards,
    ) {}

    public function normalizePlatform(string $platform): string
    {
        return $platform === 'bale' ? 'bale' : 'telegram';
    }

    public function prefix(string $platform): string
    {
        return $this->normalizePlatform($platform) === 'telegram'
            ? 'force_join_telegram'
            : 'force_join_bale';
    }

    /** @return array{enabled:bool,chat_id:int,username:string,invite_link:string,prompt_text:string,announce_text:string} */
    public function config(string $platform): array
    {
        $plat = $this->normalizePlatform($platform);
        $p = $this->prefix($plat);
        $enabled = (bool) $this->settings->get("{$p}_enabled", false);
        $chatId = (int) $this->settings->get("{$p}_chat_id", 0);
        $username = $this->normalizeUsername((string) $this->settings->get("{$p}_username", ''));
        $invite = trim((string) $this->settings->get("{$p}_invite_link", ''));
        $prompt = (string) $this->settings->get("{$p}_prompt_text", '');
        $announce = (string) $this->settings->get("{$p}_announce_text", '');

        // Legacy single-platform keys (telegram) when new keys unset.
        if ($plat === 'telegram' && ! $enabled && $chatId === 0) {
            $legacyEnabled = (bool) $this->settings->get('force_join_enabled', false);
            $legacyChat = (int) $this->settings->get('force_join_channel_id', 0);
            if ($legacyEnabled || $legacyChat !== 0) {
                $enabled = $legacyEnabled;
                $chatId = $legacyChat;
                if ($invite === '') {
                    $invite = trim((string) $this->settings->get('force_join_channel_url', ''));
                }
                if ($prompt === '') {
                    $prompt = (string) $this->settings->get('force_join_prompt', '');
                }
            }
        }

        return [
            'enabled' => $enabled,
            'chat_id' => $chatId,
            'username' => $username,
            'invite_link' => $invite,
            'prompt_text' => $prompt,
            'announce_text' => $announce,
        ];
    }

    public function isEnabled(string $platform): bool
    {
        $cfg = $this->config($platform);

        return $cfg['enabled'] && $cfg['chat_id'] !== 0;
    }

    public function shouldGate(string $platform): bool
    {
        if (! $this->isEnabled($platform)) {
            return false;
        }

        return $this->joinUrl($platform) !== '';
    }

    public function normalizeUsername(string $username): string
    {
        return ltrim(trim($username), '@');
    }

    public function joinUrl(string $platform): string
    {
        $cfg = $this->config($platform);
        $link = (string) ($cfg['invite_link'] ?? '');
        if ($link !== '') {
            return $link;
        }
        $user = (string) ($cfg['username'] ?? '');
        if ($user === '') {
            return '';
        }
        if ($this->normalizePlatform($platform) === 'bale') {
            return 'https://ble.ir/'.rawurlencode($user);
        }

        return 'https://t.me/'.rawurlencode($user);
    }

    public function promptMessage(string $platform, ?SvpUser $user = null): string
    {
        $cfg = $this->config($platform);
        $text = trim((string) ($cfg['prompt_text'] ?? ''));
        if ($text !== '') {
            return $text;
        }
        if ($user) {
            return $this->texts->getForUser('msg.force_join.prompt', $user, 'Join our channel to continue');
        }

        return $this->texts->get('msg.force_join.prompt', 'Join our channel to continue');
    }

    /** @return array{inline_keyboard: list<list<array<string, string>>>}|null */
    public function promptKeyboard(string $platform, ?SvpUser $user = null): ?array
    {
        $url = $this->joinUrl($platform);
        if ($url === '') {
            return null;
        }
        $joinLbl = $user
            ? $this->texts->getForUser('btn.force_join.channel', $user, 'Join')
            : $this->texts->get('btn.force_join.channel', 'Join');
        $verifyLbl = $user
            ? $this->texts->getForUser('btn.force_join.verify', $user, 'Verify')
            : $this->texts->get('btn.force_join.verify', 'Verify');

        return [
            'inline_keyboard' => [
                [['text' => $joinLbl, 'url' => $url]],
                [['text' => $verifyLbl, 'callback_data' => 'chjoin:verify']],
            ],
        ];
    }

    public function sendPrompt(BotContext $ctx, int $chatId, ?SvpUser $user = null): void
    {
        $plat = $this->normalizePlatform($ctx->platform);
        if (! $this->shouldGate($plat)) {
            $msg = $user
                ? $this->texts->getForUser('msg.force_join.misconfigured', $user, 'Force join is not configured.')
                : $this->texts->get('msg.force_join.misconfigured', 'Force join is not configured.');
            $this->runtime->sendMessage($ctx, $chatId, $msg);
            Log::warning('force_join misconfigured: missing join url', ['platform' => $plat]);

            return;
        }
        $extra = [];
        $mk = $this->promptKeyboard($plat, $user);
        if ($mk) {
            $extra['reply_markup'] = $mk;
        }
        $this->runtime->sendMessage($ctx, $chatId, $this->promptMessage($plat, $user), $extra);
    }

    public function membershipCacheKey(string $platform, int $chatId, int $userId): string
    {
        return 'svp_chjoin_'.$this->normalizePlatform($platform).'_'.(int) $chatId.'_'.(int) $userId;
    }

    public function positiveCacheTtl(): int
    {
        return max(30, (int) $this->settings->get('force_join_cache_ttl_sec', 180));
    }

    public function negativeCacheTtl(): int
    {
        return max(10, (int) $this->settings->get('force_join_negative_cache_ttl_sec', 45));
    }

    /**
     * Fetch membership via Bot API with retry and cache (WP fetch_member_status).
     *
     * @param  array{force_refresh?:bool, retries?:int, api_timeout_sec?:int}  $opts
     * @return array{passes:bool, api_error:bool, from_cache?:bool, status:string}
     */
    public function fetchMemberStatus(BotContext $ctx, int $userId, array $opts = []): array
    {
        $plat = $this->normalizePlatform($ctx->platform);
        $forceRefresh = ! empty($opts['force_refresh']);
        $retries = max(1, min(4, (int) ($opts['retries'] ?? 2)));
        $apiTimeout = max(3, min(25, (int) ($opts['api_timeout_sec'] ?? 25)));
        $cfg = $this->config($plat);
        $cid = (int) ($cfg['chat_id'] ?? 0);
        $uid = (int) $userId;
        if ($cid === 0 || $uid < 1) {
            return [
                'passes' => true,
                'api_error' => false,
                'status' => 'skipped',
            ];
        }
        $cacheKey = $this->membershipCacheKey($plat, $cid, $uid);
        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if ($cached === '1' || $cached === 1 || $cached === true) {
                return [
                    'passes' => true,
                    'api_error' => false,
                    'from_cache' => true,
                    'status' => 'cached_member',
                ];
            }
            if ($cached === '0' || $cached === 0 || $cached === false) {
                return [
                    'passes' => false,
                    'api_error' => false,
                    'from_cache' => true,
                    'status' => 'cached_not_member',
                ];
            }
        }
        $client = $this->runtime->client($ctx);
        if (! $client) {
            return [
                'passes' => true,
                'api_error' => false,
                'status' => 'no_client',
            ];
        }
        $lastDesc = '';
        for ($i = 0; $i < $retries; $i++) {
            if ($i > 0) {
                usleep(80000 + $i * 40000);
            }
            $res = $client->getChatMember([
                'chat_id' => $cid,
                'user_id' => $uid,
            ], $apiTimeout);
            if (is_array($res) && ! empty($res['ok'])) {
                $member = isset($res['result']) && is_array($res['result']) ? $res['result'] : [];
                $passes = $this->memberStatusOk($member);
                $ttl = $passes ? $this->positiveCacheTtl() : $this->negativeCacheTtl();
                $status = isset($member['status']) ? (string) $member['status'] : '';
                Cache::put($cacheKey, $passes ? '1' : '0', $ttl);

                return [
                    'passes' => $passes,
                    'api_error' => false,
                    'status' => $status,
                ];
            }
            $lastDesc = is_array($res) && isset($res['description']) ? (string) $res['description'] : '';
        }
        Log::warning('getChatMember failed after retries (fail-open)', [
            'platform' => $plat,
            'chat_id' => $cid,
            'user_id' => $uid,
            'description' => $lastDesc,
            'retries' => $retries,
        ]);

        return [
            'passes' => true,
            'api_error' => true,
            'status' => 'api_error',
        ];
    }

    public function userPasses(BotContext $ctx, int $userId, bool $forceRefresh = false): bool
    {
        $plat = $this->normalizePlatform($ctx->platform);
        if (! $this->isEnabled($plat)) {
            return true;
        }
        $result = $this->fetchMemberStatus($ctx, $userId, [
            'force_refresh' => $forceRefresh,
        ]);

        return ! empty($result['passes']);
    }

    /**
     * Fast interactive gate: cache hit only; cache miss fail-open + background refresh.
     */
    public function gateAllowsInteractive(BotContext $ctx, int $userId): bool
    {
        $plat = $this->normalizePlatform($ctx->platform);
        if (! $this->shouldGate($plat)) {
            return true;
        }
        $cfg = $this->config($plat);
        $cid = (int) ($cfg['chat_id'] ?? 0);
        $uid = (int) $userId;
        if ($cid === 0 || $uid < 1) {
            return true;
        }
        $cacheKey = $this->membershipCacheKey($plat, $cid, $uid);
        $cached = Cache::get($cacheKey);
        if ($cached === '1' || $cached === 1 || $cached === true) {
            return true;
        }
        if ($cached === '0' || $cached === 0 || $cached === false) {
            return false;
        }
        $this->scheduleMembershipCacheRefresh($ctx, $uid);

        return true;
    }

    /**
     * Refresh membership cache after response (non-blocking for interactive paths).
     */
    public function scheduleMembershipCacheRefresh(BotContext $ctx, int $userId): void
    {
        $plat = $this->normalizePlatform($ctx->platform);
        $uid = (int) $userId;
        if ($uid < 1 || ! $this->shouldGate($plat)) {
            return;
        }
        $platform = $ctx->platform;
        $resellerSvpUserId = $ctx->resellerSvpUserId;
        $resellerProfile = $ctx->resellerProfile;
        $mirrorBotId = $ctx->mirrorBotId;
        $mirrorProfile = $ctx->mirrorProfile;

        dispatch(function () use ($platform, $resellerSvpUserId, $resellerProfile, $mirrorBotId, $mirrorProfile, $uid) {
            $refreshCtx = new BotContext(
                $platform,
                $resellerSvpUserId,
                $resellerProfile,
                $mirrorBotId,
                $mirrorProfile,
            );
            app(RequiredChannelService::class)->fetchMemberStatus($refreshCtx, $uid, [
                'api_timeout_sec' => 5,
                'retries' => 1,
            ]);
        })->afterResponse();
    }

    /** @param  array<string, mixed>  $member */
    public function memberStatusOk(array $member): bool
    {
        $status = (string) ($member['status'] ?? '');
        if (in_array($status, ['creator', 'administrator', 'member'], true)) {
            return true;
        }
        if ($status === 'restricted' && ! empty($member['is_member'])) {
            return true;
        }

        return false;
    }

    public function onVerifySuccess(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $msg = $this->texts->getForUser('msg.force_join.success', $user, 'Membership verified.');
        $this->runtime->sendMessage($ctx, $chatId, $msg);
        if ((string) $user->status === 'approved') {
            $welcomeKey = $ctx->isResellerBot() ? 'msg.welcome.reseller' : 'msg.welcome';
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser($welcomeKey, $user, 'Welcome!'), [
                'reply_markup' => $this->keyboards->userMainReply($user),
            ]);
        }
    }

    /** @return array{ok:bool, message?:string, message_id?:int} */
    public function publishAnnouncement(string $platform): array
    {
        $plat = $this->normalizePlatform($platform);
        $cfg = $this->config($plat);
        $cid = (int) ($cfg['chat_id'] ?? 0);
        $text = trim((string) ($cfg['announce_text'] ?? ''));
        if ($cid === 0) {
            return ['ok' => false, 'message' => 'missing_chat_id'];
        }
        if ($text === '') {
            return ['ok' => false, 'message' => 'missing_announce_text'];
        }
        $ctx = new BotContext($plat);
        $client = $this->runtime->client($ctx);
        if (! $client) {
            return ['ok' => false, 'message' => 'no_bot_client'];
        }
        $send = $client->sendMessage([
            'chat_id' => $cid,
            'text' => $text,
        ]);
        if (! is_array($send) || empty($send['ok']) || ! is_array($send['result'] ?? null)) {
            return [
                'ok' => false,
                'message' => isset($send['description']) ? (string) $send['description'] : 'send_failed',
            ];
        }
        $mid = (int) ($send['result']['message_id'] ?? 0);
        if ($mid < 1) {
            return ['ok' => false, 'message' => 'no_message_id'];
        }
        $pin = $client->pinChatMessage([
            'chat_id' => $cid,
            'message_id' => $mid,
            'disable_notification' => true,
        ]);
        if (! is_array($pin) || empty($pin['ok'])) {
            return [
                'ok' => false,
                'message' => isset($pin['description']) ? (string) $pin['description'] : 'pin_failed',
                'message_id' => $mid,
            ];
        }

        return [
            'ok' => true,
            'message' => 'pinned',
            'message_id' => $mid,
        ];
    }
}
