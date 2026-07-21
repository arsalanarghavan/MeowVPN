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
            $platform = (string) ($payload['platform'] ?? 'telegram');
            $mode = (string) ($payload['mode'] ?? 'config');
            if ($userId > 0 && $serviceId > 0) {
                // deliver() schedules its own retry or clears cache — do not forget on retry.
                $result = $delivery->deliver($userId, $serviceId, (string) ($payload['cb_id'] ?? ''), $platform, $mode);
                if ($result === 'retry') {
                    continue;
                }
            } else {
                Cache::forget($key);
            }
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

        // Array/file cache: scan recent services as a best-effort sweep.
        $out = [];
        $ids = DB::table('svp_services')->orderByDesc('id')->limit(50)->pluck('id');
        foreach ($ids as $sid) {
            $sid = (int) $sid;
            $owner = (int) (DB::table('svp_services')->where('id', $sid)->value('user_id') ?? 0);
            if ($owner < 1) {
                continue;
            }
            foreach (['telegram', 'bale'] as $plat) {
                foreach (['config', 'subscription', 'link'] as $mode) {
                    $k = "bot_config_delivery:{$owner}:{$sid}:{$plat}:{$mode}";
                    if (Cache::has($k)) {
                        $out[] = $k;
                    }
                }
            }
            $legacy = "bot_config_delivery:{$owner}:{$sid}";
            if (Cache::has($legacy)) {
                $out[] = $legacy;
            }
        }

        return $out;
    }
}
