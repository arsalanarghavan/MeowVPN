<?php

namespace App\Modules\Core\Bot\Services;

use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Jobs\ProcessInboundUpdateJob;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * WP SimpleVPBot_Bot_Polling parity for main telegram/bale bots.
 */
class BotPollingService
{
    public function __construct(
        protected SettingsStore $settings,
        protected BotRuntime $runtime,
    ) {}

    public function anyPlatformNeedsPoll(): bool
    {
        foreach (['telegram', 'bale'] as $platform) {
            if ($this->platformShouldPoll($platform)) {
                return true;
            }
        }

        return false;
    }

    public function platformShouldPoll(string $platform): bool
    {
        $platform = $platform === 'bale' ? 'bale' : 'telegram';
        if (! $this->isPollingMode($platform)) {
            return false;
        }
        // MeowVPN master switch is bot_enabled (WP used `enabled`).
        if (! $this->settings->get('bot_enabled', $this->settings->get('enabled', true))) {
            return false;
        }
        if ($platform === 'bale' && ! $this->settings->get('bale_enabled', false)) {
            return false;
        }
        if ($platform === 'telegram' && ! $this->settings->get('telegram_enabled', true)) {
            return false;
        }

        return trim($this->runtime->tokenForContext(new BotContext($platform))) !== '';
    }

    public function isPollingMode(string $platform): bool
    {
        $key = $platform === 'bale' ? 'bale_update_mode' : 'telegram_update_mode';

        return $this->normalizeMode((string) $this->settings->get($key, 'webhook')) === 'polling';
    }

    public function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return $mode === 'polling' ? 'polling' : 'webhook';
    }

    public function pollAll(): void
    {
        if (! Cache::add('svp_bot_poll_lock', 1, 25)) {
            return;
        }
        try {
            foreach (['telegram', 'bale'] as $platform) {
                if ($this->platformShouldPoll($platform)) {
                    $this->pollPlatform($platform);
                }
            }
        } finally {
            Cache::forget('svp_bot_poll_lock');
        }
    }

    public function pollPlatform(string $platform): void
    {
        $platform = $platform === 'bale' ? 'bale' : 'telegram';
        $ctx = new BotContext($platform);
        $client = $this->runtime->client($ctx);
        if (! $client) {
            return;
        }

        $offsetKey = $platform === 'bale' ? 'simplevpbot_bale_update_offset' : 'simplevpbot_tg_update_offset';
        $lastKey = $platform === 'bale' ? 'simplevpbot_bale_last_poll_at' : 'simplevpbot_tg_last_poll_at';
        $offset = (int) $this->settings->get($offsetKey, 0);
        $res = $client->getUpdates([
            'offset' => $offset,
            'limit' => 50,
            'timeout' => 0,
        ]);
        $this->settings->set($lastKey, time());

        if (! is_array($res) || empty($res['ok']) || ! is_array($res['result'] ?? null)) {
            Log::warning('bot_poll_getUpdates_failed', [
                'platform' => $platform,
                'desc' => (string) ($res['description'] ?? ''),
            ]);

            return;
        }

        $nextOffset = $offset;
        foreach ($res['result'] as $update) {
            if (! is_array($update)) {
                continue;
            }
            $uid = (int) ($update['update_id'] ?? 0);
            if ($uid > 0 && $uid + 1 > $nextOffset) {
                $nextOffset = $uid + 1;
            }
            ProcessInboundUpdateJob::dispatch($platform, $update);
        }
        if ($nextOffset !== $offset) {
            $this->settings->set($offsetKey, $nextOffset);
        }
    }
}
