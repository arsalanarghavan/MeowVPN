<?php

namespace App\Modules\Core\Bot\Services;

use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Clients\BaleApiClient;
use App\Modules\Core\Bot\Clients\TelegramApiClient;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Crypt;

class BotRuntime
{
    public function __construct(protected SettingsStore $settings) {}

    public function client(BotContext $ctx): TelegramApiClient|BaleApiClient|null
    {
        $token = $this->tokenForContext($ctx);
        if ($token === '') {
            return null;
        }

        if ($ctx->platform === 'bale') {
            return new BaleApiClient($token);
        }

        $proxy = trim((string) $this->settings->get('telegram_http_proxy', ''));

        return new TelegramApiClient($token, $proxy !== '' ? $proxy : null);
    }

    public function sendMessage(BotContext $ctx, int $chatId, string $text, array $extra = []): ?array
    {
        $client = $this->client($ctx);
        if (! $client) {
            return null;
        }

        if ($ctx->platform === 'bale') {
            $text = str_replace(['**', '__'], '', $text);
        }

        return $client->sendMessage(array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $extra['parse_mode'] ?? 'HTML',
        ], $extra));
    }

    /** @param  array<string, mixed>  $params */
    public function answerCallbackQuery(BotContext $ctx, array $params): ?array
    {
        return $this->client($ctx)?->answerCallbackQuery($params);
    }

    /** @param  array<string, mixed>  $params */
    public function editMessageText(BotContext $ctx, array $params): ?array
    {
        return $this->client($ctx)?->editMessageText($params);
    }

    public function tokenForContext(BotContext $ctx): string
    {
        if ($ctx->isMirrorBot()) {
            $stored = trim((string) ($ctx->mirrorProfile['telegram_token'] ?? ''));
            if ($stored === '') {
                return '';
            }

            try {
                return Crypt::decryptString($stored);
            } catch (\Throwable) {
                return $stored;
            }
        }

        if ($ctx->isResellerBot()) {
            $profile = $ctx->resellerProfile ?? [];
            $key = $ctx->platform === 'bale' ? 'bale_token' : 'telegram_token';
            $stored = trim((string) ($profile[$key] ?? $profile['token'] ?? ''));
            if ($stored === '') {
                return '';
            }

            try {
                return Crypt::decryptString($stored);
            } catch (\Throwable) {
                return $stored;
            }
        }

        if ($ctx->platform === 'bale') {
            return (string) $this->settings->get('bale_token', '');
        }

        return (string) $this->settings->get('telegram_bot_token', $this->settings->get('telegram_token', ''));
    }

    /** @param  array<string, mixed>  $params */
    public function editMessageReplyMarkup(BotContext $ctx, array $params): ?array
    {
        return $this->client($ctx)?->editMessageReplyMarkup($params);
    }

    /** @param  array<string, mixed>  $params */
    public function sendPhoto(BotContext $ctx, array $params): ?array
    {
        return $this->client($ctx)?->sendPhoto($params);
    }

    public function sendLocalPhoto(BotContext $ctx, int $chatId, string $path, string $caption = ''): ?array
    {
        $client = $this->client($ctx);
        if (! $client) {
            return null;
        }

        return $client->sendPhotoFile($chatId, $path, $caption);
    }

    /** @return array<string, mixed>|null */
    public function getFile(BotContext $ctx, string $fileId): ?array
    {
        $r = $this->client($ctx)?->getFile(['file_id' => $fileId]);

        return is_array($r) && ! empty($r['ok']) ? ($r['result'] ?? null) : null;
    }

    public function downloadFileToPath(BotContext $ctx, string $fileId, string $destPath): bool
    {
        $meta = $this->getFile($ctx, $fileId);
        if (! is_array($meta)) {
            return false;
        }
        $path = (string) ($meta['file_path'] ?? '');
        if ($path === '') {
            return false;
        }
        $token = $this->tokenForContext($ctx);
        if ($token === '') {
            return false;
        }
        $base = $ctx->platform === 'bale'
            ? 'https://tapi.bale.ai/file/bot'.rawurlencode($token).'/'
            : 'https://api.telegram.org/file/bot'.rawurlencode($token).'/';

        try {
            $bytes = \Illuminate\Support\Facades\Http::timeout(120)->get($base.$path)->body();
            if ($bytes === '') {
                return false;
            }
            $dir = dirname($destPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            return file_put_contents($destPath, $bytes) !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param  array<string, mixed>  $params */
    public function sendInvoice(BotContext $ctx, array $params): ?array
    {
        return $this->client($ctx)?->sendInvoice($params);
    }

    /** @param  array<string, mixed>  $params */
    public function answerPreCheckoutQuery(BotContext $ctx, array $params): ?array
    {
        return $this->client($ctx)?->answerPreCheckoutQuery($params);
    }

    public function webhookUrl(string $platform, string $secret, int $resellerId = 0): string
    {
        $base = $this->publicWebhookBase($platform);
        if ($resellerId > 0) {
            return "{$base}/api/v1/webhook/{$platform}/reseller/{$resellerId}/".rawurlencode($secret);
        }

        return "{$base}/api/v1/webhook/{$platform}/".rawurlencode($secret);
    }

    public function mirrorWebhookUrl(int $mirrorId, string $secret): string
    {
        $base = $this->publicWebhookBase('telegram');

        return "{$base}/api/v1/webhook/telegram/mirror/{$mirrorId}/".rawurlencode($secret);
    }

    protected function publicWebhookBase(string $platform): string
    {
        $overrideKey = match ($platform) {
            'bale' => 'bale_public_webhook_base',
            'telegram' => 'telegram_public_webhook_base',
            default => '',
        };
        $base = $overrideKey !== ''
            ? trim((string) $this->settings->get($overrideKey, ''))
            : '';
        if ($base === '') {
            $base = rtrim((string) $this->settings->get('public_site_url', config('app.url')), '/');
        } else {
            $base = rtrim($base, '/');
        }

        return $base;
    }
}
