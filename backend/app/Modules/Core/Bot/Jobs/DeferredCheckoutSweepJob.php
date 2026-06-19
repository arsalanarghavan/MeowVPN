<?php

namespace App\Modules\Core\Bot\Jobs;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\BuyHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DeferredCheckoutSweepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BuyHandler $buy): void
    {
        $keys = $this->listCacheKeys('bot_deferred_checkout:*');
        foreach ($keys as $key) {
            $payload = Cache::get($key);
            if (! is_array($payload)) {
                Cache::forget($key);

                continue;
            }
            $user = SvpUser::query()->find((int) ($payload['user_id'] ?? 0));
            if (! $user) {
                Cache::forget($key);

                continue;
            }
            $ctx = new BotContext((string) ($payload['platform'] ?? 'telegram'));
            $buy->sendDeferredCheckout(
                $ctx,
                $user,
                (int) ($payload['chat_id'] ?? 0),
                (int) ($payload['plan_id'] ?? 0),
                (int) ($payload['extra_gb'] ?? 0),
                (int) ($payload['clients_count'] ?? 1),
            );
            Cache::forget($key);
        }
    }

    /** @return list<string> */
    protected function listCacheKeys(string $pattern): array
    {
        if (config('cache.default') === 'redis') {
            try {
                $redis = Cache::getStore()->connection();
                $keys = $redis->keys($pattern);

                return is_array($keys) ? array_map('strval', $keys) : [];
            } catch (\Throwable) {
                return [];
            }
        }

        return DB::table('cache')
            ->where('key', 'like', str_replace('*', '%', $pattern))
            ->pluck('key')
            ->map(fn ($k) => (string) $k)
            ->all();
    }
}
