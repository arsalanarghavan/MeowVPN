<?php

namespace App\Modules\Core\Bot\Jobs;

use App\Modules\Core\Bot\Services\BotConfigDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DeferredConfigDeliveryCronJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BotConfigDeliveryService $delivery): void
    {
        $keys = $this->listCacheKeys('bot_config_delivery:*');
        foreach ($keys as $key) {
            $payload = Cache::get($key);
            if (! is_array($payload)) {
                Cache::forget($key);

                continue;
            }
            $userId = (int) ($payload['owner_id'] ?? $payload['user_id'] ?? 0);
            $serviceId = (int) ($payload['service_id'] ?? 0);
            if ($userId > 0 && $serviceId > 0) {
                $delivery->deliver($userId, $serviceId, (string) ($payload['cb_id'] ?? ''));
            }
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
