<?php

namespace App\Modules\Core\Bot\Jobs;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\BuyHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DeferredC2cSweepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BuyHandler $buy, BotStateService $state): void
    {
        $keys = $this->listCacheKeys('bot_deferred_c2c:*');
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
            $buy->deliverCardPayment(
                $ctx,
                $user,
                (int) ($payload['chat_id'] ?? 0),
                (int) ($payload['transaction_id'] ?? 0),
                (int) ($payload['card_id'] ?? 0),
                isset($payload['cb_id']) ? (string) $payload['cb_id'] : null,
            );
            Cache::forget($key);
        }

        $staleMinutes = max(5, (int) config('svp.bot_c2c_stale_minutes', 30));
        $cutoff = now()->subMinutes($staleMinutes);
        $rows = DB::table('svp_transactions')
            ->where('status', 'pending')
            ->whereIn('type', ['purchase', 'wallet_topup', 'service_renew', 'service_add_volume', 'service_add_slots'])
            ->where('created_at', '<', $cutoff)
            ->whereNotNull('meta_json')
            ->limit(50)
            ->get(['id', 'user_id', 'meta_json']);
        foreach ($rows as $row) {
            $meta = json_decode((string) $row->meta_json, true);
            if (! is_array($meta) || empty($meta['awaiting_c2c'])) {
                continue;
            }
            $u = SvpUser::query()->find((int) $row->user_id);
            if (! $u || $state->get($u) !== 'awaiting_receipt_photo') {
                continue;
            }
            $data = $state->data($u);
            if ((int) ($data['transaction_id'] ?? 0) !== (int) $row->id) {
                continue;
            }
            $state->clear($u);
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
