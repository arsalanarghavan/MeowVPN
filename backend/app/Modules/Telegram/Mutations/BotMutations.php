<?php

namespace App\Modules\Telegram\Mutations;

use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Clients\BaleApiClient;
use App\Modules\Core\Bot\Clients\TelegramApiClient;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\UiActionRegistryService;
use App\Modules\Core\Bot\Services\UiCustomGroupsService;
use App\Modules\Core\Bot\Services\UiLayoutStudioService;
use App\Modules\Relay\Services\TelegramRelayService;
use App\Models\DashboardUser;
use App\Services\Bot\WebhookDiagnosticsService;
use App\Services\SettingsStore;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BotMutations
{
    public function __construct(
        protected SettingsStore $settings,
        protected BotRuntime $runtime,
        protected WebhookDiagnosticsService $diagnostics,
        protected TelegramRelayService $relay,
        protected UiCustomGroupsService $uiGroups,
        protected UiLayoutStudioService $uiLayoutStudio,
    ) {}

    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlersForPlatform(string $platform): array
    {
        if ($platform !== 'telegram') {
            return [];
        }

        return [
            'bot_toggle_enabled' => [self::class, 'botToggleEnabled'],
            'bot_toggle_platform_enabled' => [self::class, 'botTogglePlatformEnabled'],
            'bot_test_telegram' => [self::class, 'botTestTelegram'],
            'bot_diagnostics' => [self::class, 'botDiagnostics'],
            'bot_set_webhook' => [self::class, 'botSetWebhook'],
            'bot_delete_webhook' => [self::class, 'botDeleteWebhook'],
            'bot_set_update_mode' => [self::class, 'botSetUpdateMode'],
            'bot_admin_id_add' => [self::class, 'botAdminIdAdd'],
            'bot_admin_id_remove' => [self::class, 'botAdminIdRemove'],
            'force_join_publish' => [self::class, 'forceJoinPublish'],
            'telegram_proxy_test' => [self::class, 'telegramProxyTest'],
            'texts_save' => [self::class, 'textsSave'],
            'text_reset_one' => [self::class, 'textResetOne'],
            'texts_reset' => [self::class, 'textsReset'],
            'bot_ui_layout_save' => [self::class, 'botUiLayoutSave'],
            'bot_ui_layout_reset' => [self::class, 'botUiLayoutReset'],
            'bot_ui_group_create' => [self::class, 'botUiGroupCreate'],
            'bot_ui_group_delete' => [self::class, 'botUiGroupDelete'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function botToggleEnabled(array $payload, ?Authenticatable $actor): array
    {
        $this->settings->set('bot_enabled', (bool) ($payload['enabled'] ?? false));

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function botTogglePlatformEnabled(array $payload, ?Authenticatable $actor): array
    {
        $platform = (string) ($payload['platform'] ?? 'telegram');
        $key = $platform === 'bale' ? 'bale_enabled' : 'telegram_enabled';
        $this->settings->set($key, (bool) ($payload['enabled'] ?? false));

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function botTestTelegram(array $payload, ?Authenticatable $actor): array
    {
        $token = (string) $this->settings->get('telegram_bot_token', '');
        if ($token === '') {
            return svp_err('Telegram token not configured');
        }
        $r = Http::get("https://api.telegram.org/bot{$token}/getMe");

        return svp_ok(['telegram' => $r->json()]);
    }

    /** @param  array<string, mixed>  $payload */
    public function botDiagnostics(array $payload, ?Authenticatable $actor): array
    {
        $platform = (string) ($payload['platform'] ?? 'telegram');

        return svp_ok(['checks' => $this->diagnostics->run($platform === 'bale' ? 'bale' : 'telegram')]);
    }

    /** @param  array<string, mixed>  $payload */
    public function botSetWebhook(array $payload, ?Authenticatable $actor): array
    {
        $platform = (string) ($payload['platform'] ?? 'telegram');
        if ($platform === 'bale') {
            return $this->botSetWebhookBale();
        }

        if ($this->relay->isEnabled()) {
            return $this->relay->setWebhookViaRelay('main', 0, true);
        }

        $secret = (string) $this->settings->get('telegram_webhook_secret', '');
        if ($secret === '') {
            $secret = Str::random(32);
            $this->settings->set('telegram_webhook_secret', $secret);
        }

        $url = $this->runtime->webhookUrl('telegram', $secret);
        $ctx = new BotContext('telegram');
        $token = $this->runtime->tokenForContext($ctx);
        if ($token === '') {
            return svp_err('token_missing');
        }

        $client = new TelegramApiClient($token);
        $params = ['url' => $url];
        $hdr = (string) $this->settings->get('telegram_secret_header', '');
        if ($hdr !== '') {
            $params['secret_token'] = $hdr;
        }
        $result = $client->setWebhook($params);
        $this->settings->set('telegram_webhook_url', $url);

        return svp_ok(['url' => $url, 'result' => $result]);
    }

    /** @param  array<string, mixed>  $payload */
    public function botSetUpdateMode(array $payload, ?Authenticatable $actor): array
    {
        $platform = strtolower(trim((string) ($payload['platform'] ?? 'telegram')));
        if (! in_array($platform, ['telegram', 'bale'], true)) {
            $platform = 'telegram';
        }
        $mode = app(\App\Modules\Core\Bot\Services\BotPollingService::class)
            ->normalizeMode((string) ($payload['mode'] ?? $payload['update_mode'] ?? 'webhook'));
        $key = $platform === 'bale' ? 'bale_update_mode' : 'telegram_update_mode';
        $this->settings->set($key, $mode);

        $token = trim($this->runtime->tokenForContext(new BotContext($platform)));
        $webhookStep = null;
        if ($mode === 'polling') {
            // Drop webhook so getUpdates can receive messages (WP parity).
            if ($token === '') {
                $webhookStep = ['ok' => true, 'skipped' => 'no_token'];
            } else {
                try {
                    $webhookStep = $this->botDeleteWebhook(['platform' => $platform], $actor);
                } catch (\Throwable $e) {
                    $webhookStep = ['ok' => false, 'message' => $e->getMessage()];
                }
            }
        } else {
            // Restore webhook when leaving polling (default mode).
            if ($token === '') {
                $webhookStep = ['ok' => false, 'message' => 'token_missing'];
            } else {
                try {
                    $webhookStep = $this->botSetWebhook(['platform' => $platform], $actor);
                } catch (\Throwable $e) {
                    $webhookStep = ['ok' => false, 'message' => $e->getMessage()];
                }
            }
        }

        $ok = is_array($webhookStep) ? ! empty($webhookStep['ok']) : true;
        $data = ['platform' => $platform, 'mode' => $mode, 'webhook_step' => $webhookStep];
        if (! $ok) {
            return svp_err((string) ($webhookStep['message'] ?? 'webhook_step_failed'), $data);
        }

        return svp_ok($data);
    }

    /** @param  array<string, mixed>  $payload */
    public function botDeleteWebhook(array $payload, ?Authenticatable $actor): array
    {
        $platform = (string) ($payload['platform'] ?? 'telegram');
        if ($platform === 'bale') {
            return $this->botDeleteWebhookBale();
        }

        if ($this->relay->isEnabled()) {
            return $this->relay->deleteWebhookViaRelay('main', 0);
        }

        $ctx = new BotContext('telegram');
        $token = $this->runtime->tokenForContext($ctx);
        if ($token === '') {
            return svp_err('token_missing');
        }
        $result = (new TelegramApiClient($token))->deleteWebhook();
        $this->settings->set('telegram_webhook_url', '');

        return svp_ok(['result' => $result]);
    }

    /** @param  array<string, mixed>  $payload */
    public function botAdminIdAdd(array $payload, ?Authenticatable $actor): array
    {
        $id = (int) ($payload['id'] ?? $payload['admin_id'] ?? 0);
        if ($id < 1) {
            return svp_err('invalid');
        }
        $settingKey = (string) ($payload['platform'] ?? 'telegram') === 'bale' ? 'admin_bale_ids' : 'admin_telegram_ids';
        $ids = (array) $this->settings->get($settingKey, []);
        $ids[] = $id;
        $this->settings->set($settingKey, array_values(array_unique($ids)));

        return svp_ok(['ids' => $ids]);
    }

    /** @param  array<string, mixed>  $payload */
    public function botAdminIdRemove(array $payload, ?Authenticatable $actor): array
    {
        $id = (int) ($payload['id'] ?? $payload['admin_id'] ?? 0);
        $settingKey = (string) ($payload['platform'] ?? 'telegram') === 'bale' ? 'admin_bale_ids' : 'admin_telegram_ids';
        $ids = array_values(array_filter(
            (array) $this->settings->get($settingKey, []),
            fn ($v) => (int) $v !== $id
        ));
        $this->settings->set($settingKey, $ids);

        return svp_ok(['ids' => $ids]);
    }

    /** @param  array<string, mixed>  $payload */
    public function forceJoinPublish(array $payload, ?Authenticatable $actor): array
    {
        $platform = strtolower(trim((string) ($payload['platform'] ?? '')));
        if (! in_array($platform, ['telegram', 'bale'], true)) {
            // Legacy payload: text + force_join_channel_id (no platform).
            $channelId = (string) $this->settings->get('force_join_channel_id', '');
            $text = (string) ($payload['text'] ?? $this->settings->get('force_join_prompt', ''));
            if ($channelId === '' || $text === '') {
                return svp_err('not_configured');
            }
            $ctx = new BotContext('telegram');
            $this->runtime->sendMessage($ctx, (int) $channelId, $text);

            return svp_ok();
        }

        $result = app(\App\Modules\Core\Bot\Services\RequiredChannelService::class)
            ->publishAnnouncement($platform);
        if (empty($result['ok'])) {
            return svp_err((string) ($result['message'] ?? 'publish_failed'), array_filter([
                'message_id' => $result['message_id'] ?? null,
            ]));
        }

        return svp_ok([
            'message' => (string) ($result['message'] ?? 'pinned'),
            'message_id' => (int) ($result['message_id'] ?? 0),
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    public function telegramProxyTest(array $payload, ?Authenticatable $actor): array
    {
        $proxy = (string) $this->settings->get('telegram_http_proxy', '');
        if ($proxy === '') {
            return svp_err('no_proxy');
        }
        try {
            $res = Http::withOptions(['proxy' => $proxy, 'timeout' => 15])
                ->get('https://api.telegram.org/bot'.(string) $this->settings->get('telegram_bot_token', '0').'/getMe');
            if (! $res->successful()) {
                return svp_err('proxy_fail', ['status' => $res->status()]);
            }

            return svp_ok(['ok' => true]);
        } catch (\Throwable $e) {
            return svp_err('proxy_fail', ['message' => $e->getMessage()]);
        }
    }

    /** @param  array<string, mixed>  $payload */
    public function textsSave(array $payload, ?Authenticatable $actor): array
    {
        $saved = [];
        if (isset($payload['texts']) && is_array($payload['texts'])) {
            foreach ($payload['texts'] as $key => $bundle) {
                $key = trim((string) $key);
                if ($key === '') {
                    continue;
                }
                if (! preg_match('/^[a-zA-Z0-9._-]+$/', $key)) {
                    continue;
                }
                $this->upsertTextLocales($key, $bundle);
                $saved[] = $key;
            }
            app(\App\Modules\Core\Bot\Services\TextService::class)->clearCache();

            return $saved === [] ? svp_err('invalid') : svp_ok(['keys' => $saved]);
        }

        $key = trim((string) ($payload['key'] ?? $payload['text_key'] ?? ''));
        if ($key === '' || ! preg_match('/^[a-zA-Z0-9._-]+$/', $key)) {
            return svp_err('invalid');
        }
        if (isset($payload['fa']) || isset($payload['en']) || isset($payload['value_fa']) || isset($payload['value_en'])) {
            $this->upsertTextLocales($key, [
                'fa' => $payload['fa'] ?? $payload['value_fa'] ?? null,
                'en' => $payload['en'] ?? $payload['value_en'] ?? null,
            ]);
        } else {
            $locale = strtolower(trim((string) ($payload['locale'] ?? 'fa')));
            if (! in_array($locale, ['fa', 'en'], true)) {
                $locale = 'fa';
            }
            $value = (string) ($payload['value'] ?? $payload['text'] ?? '');
            $this->upsertTextLocales($key, [$locale => $value]);
        }
        app(\App\Modules\Core\Bot\Services\TextService::class)->clearCache();

        return svp_ok(['key' => $key]);
    }

    /** @param  array<string, mixed>|string|null  $bundle */
    protected function upsertTextLocales(string $key, mixed $bundle): void
    {
        $map = [];
        if (is_string($bundle)) {
            $map['fa'] = $this->sanitizeBotText($bundle);
        } elseif (is_array($bundle)) {
            if (array_key_exists('fa', $bundle) || array_key_exists('value_fa', $bundle)) {
                $map['fa'] = $this->sanitizeBotText((string) ($bundle['fa'] ?? $bundle['value_fa'] ?? ''));
            }
            if (array_key_exists('en', $bundle) || array_key_exists('value_en', $bundle)) {
                $map['en'] = $this->sanitizeBotText((string) ($bundle['en'] ?? $bundle['value_en'] ?? ''));
            }
            if ($map === [] && isset($bundle['value'])) {
                $map['fa'] = $this->sanitizeBotText((string) $bundle['value']);
            }
        }
        $category = \App\Modules\Core\Bot\Services\BotTextDefaults::categoryForKey($key);
        $existingCat = \Illuminate\Support\Facades\DB::table('svp_texts')->where('key_name', $key)->value('category');
        if (is_string($existingCat) && $existingCat !== '') {
            $category = $existingCat;
        }
        foreach ($map as $locale => $value) {
            \Illuminate\Support\Facades\DB::table('svp_texts')->updateOrInsert(
                ['key_name' => $key, 'locale' => $locale],
                ['value' => $value, 'category' => $category, 'updated_at' => now()]
            );
        }
    }

    protected function sanitizeBotText(string $text): string
    {
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text);
    }

    /** @param  array<string, mixed>  $payload */
    public function textResetOne(array $payload, ?Authenticatable $actor): array
    {
        $key = trim((string) ($payload['key'] ?? $payload['text_key'] ?? ''));
        if ($key === '' || ! preg_match('/^[a-zA-Z0-9._-]+$/', $key)) {
            return svp_err('bad_key');
        }
        $pair = \App\Modules\Core\Bot\Services\BotTextDefaults::defaultPairForKey($key);
        if ($pair['fa'] === '' && $pair['en'] === '') {
            \Illuminate\Support\Facades\DB::table('svp_texts')->where('key_name', $key)->delete();
            app(\App\Modules\Core\Bot\Services\TextService::class)->clearCache();

            return svp_err('unknown_key');
        }
        $this->upsertTextLocales($key, $pair);
        app(\App\Modules\Core\Bot\Services\TextService::class)->clearCache();

        return svp_ok(['key' => $key]);
    }

    /** @param  array<string, mixed>  $payload */
    public function textsReset(array $payload, ?Authenticatable $actor): array
    {
        \Illuminate\Support\Facades\DB::table('svp_texts')->delete();
        $now = now();
        $chunk = [];
        $n = 0;
        $seen = [];
        foreach (\App\Modules\Core\Bot\Services\BotTextDefaults::allRows() as $row) {
            $kn = (string) ($row['key_name'] ?? '');
            if ($kn === '') {
                continue;
            }
            $locale = ((string) ($row['locale'] ?? 'fa')) === 'en' ? 'en' : 'fa';
            $uniq = $kn."\x1e".$locale;
            if (isset($seen[$uniq])) {
                continue;
            }
            $seen[$uniq] = true;
            $chunk[] = [
                'key_name' => $kn,
                'category' => (string) ($row['category'] ?? 'general'),
                'locale' => $locale,
                'value' => (string) ($row['value'] ?? ''),
                'updated_at' => $now,
            ];
            $n++;
            if (count($chunk) >= 100) {
                \Illuminate\Support\Facades\DB::table('svp_texts')->insert($chunk);
                $chunk = [];
            }
        }
        if ($chunk !== []) {
            \Illuminate\Support\Facades\DB::table('svp_texts')->insert($chunk);
        }
        app(\App\Modules\Core\Bot\Services\TextService::class)->clearCache();

        return svp_ok(['reseeded' => $n, 'seeded' => $n]);
    }

    /** @param  array<string, mixed>  $payload */
    public function botUiLayoutSave(array $payload, ?Authenticatable $actor): array
    {
        if ($actor instanceof DashboardUser && $actor->role === 'reseller') {
            return svp_err('forbidden');
        }
        $surfacesIn = isset($payload['surfaces']) && is_array($payload['surfaces'])
            ? $payload['surfaces']
            : (isset($payload['layout']['surfaces']) && is_array($payload['layout']['surfaces']) ? $payload['layout']['surfaces'] : []);
        $validated = $this->uiLayoutStudio->validateSurfacesPayload($surfacesIn);
        if (empty($validated['ok'])) {
            return svp_err('validation_failed', ['errors' => $validated['errors'] ?? []]);
        }
        if (! empty($validated['surfaces'])) {
            $this->uiLayoutStudio->saveSurfaces($validated['surfaces']);
        }

        return svp_ok([
            'uiLayout' => $this->uiLayoutStudio->exportMergedForDashboard(),
            'uiRegistry' => UiActionRegistryService::export_for_dashboard(),
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    public function botUiLayoutReset(array $payload, ?Authenticatable $actor): array
    {
        if ($actor instanceof DashboardUser && $actor->role === 'reseller') {
            return svp_err('forbidden');
        }
        $this->uiLayoutStudio->resetAll();

        return svp_ok([
            'uiLayout' => $this->uiLayoutStudio->exportMergedForDashboard(),
            'uiRegistry' => UiActionRegistryService::export_for_dashboard(),
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    public function botUiGroupCreate(array $payload, ?Authenticatable $actor): array
    {
        if ($actor instanceof DashboardUser && $actor->role === 'reseller') {
            return svp_err('forbidden');
        }
        $res = $this->uiGroups->create($payload);
        if (empty($res['ok'])) {
            return $res;
        }
        $data = $res['data'] ?? [];
        $data['customGroups'] = $this->uiGroups->exportForDashboard();
        $data['uiRegistry'] = UiActionRegistryService::export_for_dashboard();

        return ['ok' => true, 'data' => $data];
    }

    /** @param  array<string, mixed>  $payload */
    public function botUiGroupDelete(array $payload, ?Authenticatable $actor): array
    {
        if ($actor instanceof DashboardUser && $actor->role === 'reseller') {
            return svp_err('forbidden');
        }
        $gid = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($payload['group_id'] ?? $payload['groupId'] ?? ''))) ?? '';
        if ($gid === '') {
            return svp_err('missing_group_id');
        }
        $restore = ! isset($payload['restore_to_parent']) || ! empty($payload['restore_to_parent']);
        $res = $this->uiGroups->delete($gid, $restore);
        if (empty($res['ok'])) {
            return $res;
        }
        $data = $res['data'] ?? [];
        $data['customGroups'] = $this->uiGroups->exportForDashboard();
        $data['uiRegistry'] = UiActionRegistryService::export_for_dashboard();

        return ['ok' => true, 'data' => $data];
    }

    protected function botSetWebhookBale(): array
    {
        $secret = (string) $this->settings->get('bale_webhook_secret', '');
        if ($secret === '') {
            $secret = Str::random(32);
            $this->settings->set('bale_webhook_secret', $secret);
        }
        $url = $this->runtime->webhookUrl('bale', $secret);
        $token = $this->runtime->tokenForContext(new BotContext('bale'));
        if ($token === '') {
            return svp_err('token_missing');
        }
        $result = (new BaleApiClient($token))->setWebhook([
            'url' => $url,
            'allowed_updates' => ['message', 'callback_query', 'pre_checkout_query'],
            'drop_pending_updates' => true,
        ]);
        $this->settings->set('bale_webhook_url', $url);

        return svp_ok(['url' => $url, 'result' => $result]);
    }

    protected function botDeleteWebhookBale(): array
    {
        $token = $this->runtime->tokenForContext(new BotContext('bale'));
        if ($token === '') {
            return svp_err('token_missing');
        }
        $result = (new BaleApiClient($token))->deleteWebhook();
        $this->settings->set('bale_webhook_url', '');

        return svp_ok(['result' => $result]);
    }
}
