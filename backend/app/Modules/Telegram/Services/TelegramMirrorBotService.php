<?php

namespace App\Modules\Telegram\Services;

use App\Models\SvpTelegramMirrorBot;
use App\Modules\Core\Bot\Clients\TelegramApiClient;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TelegramMirrorBotService
{
    public function __construct(
        protected SettingsStore $settings,
        protected BotRuntime $runtime,
    ) {}

    public function find(int $id): ?SvpTelegramMirrorBot
    {
        if ($id < 1 || ! Schema::hasTable('svp_telegram_mirror_bots')) {
            return null;
        }

        return SvpTelegramMirrorBot::query()->find($id);
    }

    /** @return array{rows: list<array<string, mixed>>, total: int} */
    public function listPaginated(int $page = 1, int $perPage = 25): array
    {
        if (! Schema::hasTable('svp_telegram_mirror_bots')) {
            return ['rows' => [], 'total' => 0];
        }

        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $q = SvpTelegramMirrorBot::query()->orderBy('sort_order')->orderBy('id');
        $total = (clone $q)->count();
        $rows = $q->offset(($page - 1) * $perPage)->limit($perPage)->get()
            ->map(fn (SvpTelegramMirrorBot $row) => $this->toAdminRow($row))
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return list<SvpTelegramMirrorBot> */
    public function listEnabled(): array
    {
        if (! Schema::hasTable('svp_telegram_mirror_bots')) {
            return [];
        }

        return SvpTelegramMirrorBot::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function tokenFor(?SvpTelegramMirrorBot $bot): string
    {
        if (! $bot) {
            return '';
        }

        return $this->decryptField((string) ($bot->telegram_token ?? ''));
    }

    public function webhookSecretPlaintext(?SvpTelegramMirrorBot $bot): string
    {
        if (! $bot) {
            return '';
        }

        return $this->decryptField((string) ($bot->webhook_secret ?? ''));
    }

    public function ensureWebhookSecret(int $id): string
    {
        $bot = $this->find($id);
        if (! $bot) {
            return '';
        }

        $secret = $this->webhookSecretPlaintext($bot);
        if ($secret !== '') {
            return $secret;
        }

        $secret = Str::random(32);
        $bot->webhook_secret = Crypt::encryptString($secret);
        $bot->updated_at = now();
        $bot->save();

        return $secret;
    }

    /** @param  array<string, mixed>  $fields */
    public function insert(array $fields): int|false
    {
        if (! Schema::hasTable('svp_telegram_mirror_bots')) {
            return false;
        }

        $label = mb_substr(trim((string) ($fields['label'] ?? '')), 0, 255);
        if ($label === '') {
            $label = 'Mirror bot';
        }

        $tokenPlain = array_key_exists('telegram_token', $fields)
            ? trim((string) $fields['telegram_token'])
            : '';
        $whSecret = $tokenPlain !== '' ? Str::random(32) : '';

        $bot = SvpTelegramMirrorBot::query()->create([
            'label' => $label,
            'telegram_token' => $tokenPlain !== '' ? Crypt::encryptString($tokenPlain) : null,
            'telegram_bot_username' => '',
            'webhook_secret' => $whSecret !== '' ? Crypt::encryptString($whSecret) : '',
            'telegram_secret_token' => array_key_exists('telegram_secret_token', $fields)
                ? trim((string) $fields['telegram_secret_token'])
                : '',
            'enabled' => array_key_exists('enabled', $fields) ? (bool) $fields['enabled'] : true,
            'sort_order' => array_key_exists('sort_order', $fields) ? (int) $fields['sort_order'] : 0,
            'updated_at' => now(),
        ]);

        return (int) $bot->id;
    }

    /** @param  array<string, mixed>  $fields */
    public function update(int $id, array $fields): bool
    {
        $bot = $this->find($id);
        if (! $bot) {
            return false;
        }

        if (array_key_exists('label', $fields)) {
            $bot->label = mb_substr(trim((string) $fields['label']), 0, 255);
        }
        if (array_key_exists('telegram_token', $fields)) {
            $plain = trim((string) $fields['telegram_token']);
            if ($plain !== '') {
                $bot->telegram_token = Crypt::encryptString($plain);
                if ($this->webhookSecretPlaintext($bot) === '') {
                    $bot->webhook_secret = Crypt::encryptString(Str::random(32));
                }
            }
        }
        if (array_key_exists('telegram_secret_token', $fields)) {
            $bot->telegram_secret_token = trim((string) $fields['telegram_secret_token']);
        }
        if (array_key_exists('enabled', $fields)) {
            $bot->enabled = (bool) $fields['enabled'];
        }
        if (array_key_exists('sort_order', $fields)) {
            $bot->sort_order = (int) $fields['sort_order'];
        }

        $bot->updated_at = now();
        $bot->save();

        return true;
    }

    public function saveBotUsername(int $id, string $username): void
    {
        $bot = $this->find($id);
        if (! $bot) {
            return;
        }

        $bot->telegram_bot_username = mb_substr(ltrim(trim($username), '@'), 0, 128);
        $bot->updated_at = now();
        $bot->save();
    }

    public function syncBotUsername(int $id): void
    {
        $bot = $this->find($id);
        $token = $this->tokenFor($bot);
        if ($token === '') {
            return;
        }

        $me = (new TelegramApiClient($token))->getMe();
        $username = is_array($me) ? (string) ($me['result']['username'] ?? '') : '';
        if ($username !== '') {
            $this->saveBotUsername($id, $username);
        }
    }

    public function delete(int $id): bool
    {
        $bot = $this->find($id);
        if (! $bot) {
            return false;
        }

        return (bool) $bot->delete();
    }

    public function expectedWebhookUrl(int $id): string
    {
        $bot = $this->find($id);
        if (! $bot) {
            return '';
        }

        $secret = $this->webhookSecretPlaintext($bot);
        if ($secret === '') {
            $secret = $this->ensureWebhookSecret($id);
        }
        if ($secret === '') {
            return '';
        }

        return $this->runtime->mirrorWebhookUrl($id, $secret);
    }

    /** @return array<string, mixed>|null */
    public function profileArrayForRuntime(int $id): ?array
    {
        $bot = $this->find($id);
        if (! $bot) {
            return null;
        }

        $arr = $bot->toArray();
        $arr['telegram_token'] = $this->tokenFor($bot);
        $arr['webhook_secret'] = $this->webhookSecretPlaintext($bot);

        return $arr;
    }

    /** @return array<string, mixed> */
    public function toAdminRow(SvpTelegramMirrorBot $bot): array
    {
        $mid = (int) $bot->id;

        return [
            'mirror_id' => $mid,
            'id' => $mid,
            'label' => (string) $bot->label,
            'telegram_bot_username' => (string) $bot->telegram_bot_username,
            'enabled' => (bool) $bot->enabled,
            'has_telegram_token' => $this->tokenFor($bot) !== '',
            'telegram_secret_token_set' => trim((string) $bot->telegram_secret_token) !== '',
            'webhook_telegram_url' => $this->expectedWebhookUrl($mid),
            'sort_order' => (int) $bot->sort_order,
        ];
    }

    protected function decryptField(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return $stored;
        }
    }
}
