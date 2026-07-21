<?php

namespace App\Modules\Core\Http;

use App\Http\Controllers\Controller;
use App\Modules\Reseller\Services\ResellerBotProfileService;
use App\Modules\Telegram\Services\TelegramMirrorBotService;
use App\Services\Bot\InboundQueueService;
use App\Services\SettingsStore;
use App\Support\Metrics\SvpMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WebhookController extends Controller
{
    public function __construct(
        protected SettingsStore $settings,
        protected InboundQueueService $queue,
        protected ResellerBotProfileService $resellerProfiles,
        protected TelegramMirrorBotService $mirrorBots,
    ) {}

    public function platform(Request $request, string $platform, string $secret): JsonResponse
    {
        if (! config('svp.legacy_webhook_on_backend', true)) {
            return response()->json(['ok' => false, 'message' => 'webhook_moved_to_bot_worker'], 410);
        }

        $platform = $this->normalizePlatform($platform);
        if ($platform === null) {
            return response()->json(['ok' => false, 'message' => 'not_found'], 404);
        }

        if (! svp_modules()->isEnabled($platform)) {
            return response()->json(['ok' => false, 'message' => 'module_missing'], 503);
        }

        if (! $this->settings->get('bot_enabled', true)) {
            return response()->json(['ok' => true, 'disabled' => true]);
        }

        if (! $this->platformEnabled($platform)) {
            return response()->json(['ok' => true, 'disabled' => true]);
        }

        if (app(\App\Modules\Core\Bot\Services\BotPollingService::class)->isPollingMode($platform)) {
            return response()->json(['ok' => true, 'polling' => true]);
        }

        $expected = (string) $this->settings->get("{$platform}_webhook_secret", '');
        if ($expected === '' || ! hash_equals($expected, $secret)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        if ($platform === 'telegram' && ! $this->validateTelegramHeader($request)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        return $this->acceptUpdate($request, $platform, 0, 0);
    }

    public function reseller(Request $request, string $platform, int $resellerId, string $secret): JsonResponse
    {
        if (! config('svp.legacy_webhook_on_backend', true)) {
            return response()->json(['ok' => false, 'message' => 'webhook_moved_to_bot_worker'], 410);
        }

        $platform = $this->normalizePlatform($platform);
        if ($platform === null || $resellerId < 1) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        if (! svp_modules()->isEnabled($platform) || ! svp_modules()->isEnabled('reseller')) {
            return response()->json(['ok' => false, 'message' => 'module_missing'], 503);
        }

        $headerSecret = trim((string) $request->header('X-SVP-Webhook-Secret', ''));
        $candidate = $headerSecret !== '' ? $headerSecret : $secret;

        $user = DB::table('svp_users')->where('id', $resellerId)->first();
        if (! $user || (string) $user->role !== 'reseller' || (string) $user->status !== 'approved') {
            return response()->json(['ok' => false], 403);
        }

        $profile = Schema::hasTable('svp_reseller_bot_profiles')
            ? DB::table('svp_reseller_bot_profiles')->where('reseller_svp_user_id', $resellerId)->first()
            : null;

        $webhookSecret = $this->resellerProfiles->webhookSecretPlaintext($profile);
        if (! $profile || $webhookSecret === '' || ! hash_equals($webhookSecret, $candidate)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        if (isset($profile->enabled) && ! (int) $profile->enabled) {
            return response()->json(['ok' => true, 'disabled' => true]);
        }

        $platformEnabled = $platform === 'bale'
            ? (int) ($profile->bale_enabled ?? 1)
            : (int) ($profile->telegram_enabled ?? 1);
        if (! $platformEnabled) {
            return response()->json(['ok' => true, 'disabled' => true]);
        }

        if ($platform === 'telegram' && ! $this->validateTelegramHeader($request, $profile)) {
            return response()->json(['ok' => false], 403);
        }

        return $this->acceptUpdate($request, $platform, $resellerId, 0);
    }

    public function mirror(Request $request, int $mirrorId, string $secret): JsonResponse
    {
        if (in_array(strtoupper($request->method()), ['GET', 'HEAD'], true)) {
            return response()->json([
                'ok' => true,
                'alive' => true,
                'scope' => 'mirror',
                'note' => 'POST JSON updates only',
            ]);
        }

        if (! config('svp.legacy_webhook_on_backend', true)) {
            return response()->json(['ok' => false, 'message' => 'webhook_moved_to_bot_worker'], 410);
        }

        if ($mirrorId < 1 || ! svp_modules()->isEnabled('telegram')) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        if (! $this->settings->get('bot_enabled', true)) {
            return response()->json(['ok' => true, 'disabled' => true]);
        }

        if (! $this->platformEnabled('telegram')) {
            return response()->json(['ok' => true, 'disabled' => true]);
        }

        $profile = $this->mirrorBots->find($mirrorId);
        $expectedSecret = $this->mirrorBots->webhookSecretPlaintext($profile);
        if (! $profile || $expectedSecret === '') {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        if (! $profile->enabled) {
            return response()->json(['ok' => true, 'disabled' => true]);
        }

        if (! hash_equals($expectedSecret, $secret)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        if (! $this->validateTelegramHeader($request, $profile)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        return $this->acceptUpdate($request, 'telegram', 0, $mirrorId);
    }

    public function drain(Request $request): JsonResponse
    {
        $key = (string) $request->header('X-SVP-QUEUE-KEY', '');
        $expected = $this->queue->internalQueueKey();
        if ($expected === '' || ! hash_equals($expected, $key)) {
            return response()->json(['ok' => false], 403);
        }

        $processed = $this->queue->drainBatch();

        return response()->json(['ok' => true, 'processed' => $processed]);
    }

    protected function acceptUpdate(Request $request, string $platform, int $resellerId, int $mirrorBotId = 0): JsonResponse
    {
        $json = $request->json()->all();
        if ($json === []) {
            $json = json_decode($request->getContent(), true);
        }
        if (! is_array($json) || $json === []) {
            return response()->json(['ok' => true]);
        }

        $updateId = (int) ($json['update_id'] ?? 0);
        Log::channel('svp-webhook')->info('webhook.received', [
            'platform' => $platform,
            'update_id' => $updateId,
            'reseller_id' => $resellerId,
            'mirror_bot_id' => $mirrorBotId,
        ]);

        $this->queue->enqueue($platform, $json, $resellerId, $mirrorBotId);
        $this->queue->kickAsyncDrain();
        SvpMetrics::inc('webhook_received_total');

        return response()->json(['ok' => true]);
    }

    protected function normalizePlatform(string $platform): ?string
    {
        return in_array($platform, ['telegram', 'bale'], true) ? $platform : null;
    }

    protected function platformEnabled(string $platform): bool
    {
        return (bool) $this->settings->get($platform === 'telegram' ? 'telegram_enabled' : 'bale_enabled', true);
    }

    protected function validateTelegramHeader(Request $request, ?object $profile = null): bool
    {
        $exp = (string) ($profile?->telegram_secret_token ?? $profile?->telegram_secret_header ?? $this->settings->get('telegram_secret_header', ''));
        if ($exp === '') {
            return true;
        }
        $hdr = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        return hash_equals($exp, $hdr);
    }
}
