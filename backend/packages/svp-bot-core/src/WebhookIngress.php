<?php

namespace Svp\Bot\Core;

use Svp\Bot\Core\Contracts\BotBackendClient;

final class WebhookIngress
{
    public function __construct(
        protected BotBackendClient $backend,
        protected string $platform,
        protected string $webhookSecret,
        protected string $telegramSecretHeader = '',
    ) {}

    /** @return array<string, mixed> */
    public function handle(string $pathSecret, string $rawBody, array $headers = []): array
    {
        if ($this->webhookSecret === '' || ! hash_equals($this->webhookSecret, $pathSecret)) {
            return ['ok' => false, 'status' => 403, 'body' => ['ok' => false, 'message' => 'forbidden']];
        }

        if ($this->platform === 'telegram' && $this->telegramSecretHeader !== '') {
            $hdr = (string) ($headers['x-telegram-bot-api-secret-token'] ?? $headers['X-Telegram-Bot-Api-Secret-Token'] ?? '');
            if (! hash_equals($this->telegramSecretHeader, $hdr)) {
                return ['ok' => false, 'status' => 403, 'body' => ['ok' => false, 'message' => 'forbidden']];
            }
        }

        $update = json_decode($rawBody, true);
        if (! is_array($update) || $update === []) {
            return ['ok' => true, 'status' => 200, 'body' => ['ok' => true]];
        }

        $result = $this->backend->processUpdate($this->platform, $update, 0, true);

        return ['ok' => true, 'status' => 200, 'body' => $result];
    }

    /** @return array<string, mixed> */
    public function handleReseller(int $resellerId, string $pathSecret, string $rawBody, array $headers = []): array
    {
        if ($this->webhookSecret === '' || ! hash_equals($this->webhookSecret, $pathSecret)) {
            return ['ok' => false, 'status' => 403, 'body' => ['ok' => false, 'message' => 'forbidden']];
        }

        if ($this->platform === 'telegram' && $this->telegramSecretHeader !== '') {
            $hdr = (string) ($headers['x-telegram-bot-api-secret-token'] ?? $headers['X-Telegram-Bot-Api-Secret-Token'] ?? '');
            if (! hash_equals($this->telegramSecretHeader, $hdr)) {
                return ['ok' => false, 'status' => 403, 'body' => ['ok' => false, 'message' => 'forbidden']];
            }
        }

        $update = json_decode($rawBody, true);
        if (! is_array($update) || $update === []) {
            return ['ok' => true, 'status' => 200, 'body' => ['ok' => true]];
        }

        $result = $this->backend->processUpdate($this->platform, $update, $resellerId, true);

        return ['ok' => true, 'status' => 200, 'body' => $result];
    }
}
