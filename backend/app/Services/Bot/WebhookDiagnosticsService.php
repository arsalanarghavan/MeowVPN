<?php

namespace App\Services\Bot;

use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Clients\TelegramApiClient;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WebhookDiagnosticsService
{
    public function __construct(
        protected SettingsStore $settings,
        protected BotRuntime $runtime,
        protected InboundQueueService $queue,
    ) {}

    /** @return array<string, mixed> */
    public function run(string $platform = 'telegram'): array
    {
        $ctx = new BotContext($platform);
        $token = $this->runtime->tokenForContext($ctx);
        $secret = (string) $this->settings->get("{$platform}_webhook_secret", '');

        $checks = [
            'token_set' => $token !== '',
            'webhook_secret_set' => $secret !== '',
            'expected_url' => $secret !== '' ? $this->runtime->webhookUrl($platform, $secret) : '',
            'pending_queue' => Schema::hasTable('svp_inbound_queue')
                ? (int) DB::table('svp_inbound_queue')->where('status', 'pending')->count()
                : 0,
        ];

        if ($platform === 'telegram' && $token !== '') {
            $me = (new TelegramApiClient($token))->getMe();
            $checks['getMe'] = $me;
        }

        return $checks;
    }
}
