<?php

namespace App\Services\Bot;

use App\Modules\Core\Bot\Jobs\ProcessInboundUpdateJob;
use App\Modules\Core\Jobs\InboundQueueDrainJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InboundQueueService
{
    public function batchSize(): int
    {
        return max(1, min(20, (int) config('svp.inbound_queue_batch_size', 5)));
    }

    /** @param  array<string, mixed>  $update */
    public function enqueue(string $platform, array $update, int $resellerSvpUserId = 0): ?int
    {
        if (! Schema::hasTable('svp_inbound_queue')) {
            ProcessInboundUpdateJob::dispatch($platform, $update, $resellerSvpUserId);

            return null;
        }

        $encoded = json_encode($update, JSON_UNESCAPED_UNICODE);
        if ($encoded === false || $encoded === '') {
            return null;
        }

        return DB::table('svp_inbound_queue')->insertGetId([
            'platform' => $platform,
            'reseller_svp_user_id' => $resellerSvpUserId,
            'update_json' => $encoded,
            'status' => 'pending',
            'tries' => 0,
            'created_at' => now(),
        ]);
    }

    public function drainBatch(?int $limit = null): int
    {
        if (! Schema::hasTable('svp_inbound_queue')) {
            return 0;
        }

        $limit = $limit ?? $this->batchSize();
        $rows = DB::table('svp_inbound_queue')
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;
        foreach ($rows as $row) {
            if ($this->processRow($row)) {
                $processed++;
            }
        }

        return $processed;
    }

    public function processRow(object $row): bool
    {
        $id = (int) $row->id;
        DB::table('svp_inbound_queue')->where('id', $id)->update(['status' => 'processing']);

        try {
            $update = json_decode((string) $row->update_json, true);
            if (! is_array($update)) {
                throw new \RuntimeException('invalid_json');
            }

            ProcessInboundUpdateJob::dispatchSync(
                (string) $row->platform,
                $update,
                (int) $row->reseller_svp_user_id
            );

            DB::table('svp_inbound_queue')->where('id', $id)->update([
                'status' => 'done',
                'processed_at' => now(),
            ]);

            return true;
        } catch (\Throwable $e) {
            DB::table('svp_inbound_queue')->where('id', $id)->update([
                'status' => 'failed',
                'tries' => (int) $row->tries + 1,
                'last_error' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            return false;
        }
    }

    public function kickAsyncDrain(): void
    {
        if (Cache::has('svp_inbound_kick_lock')) {
            return;
        }
        Cache::put('svp_inbound_kick_lock', true, 5);

        if (! app()->runningUnitTests()) {
            InboundQueueDrainJob::dispatch()->afterResponse();

            return;
        }

        InboundQueueDrainJob::dispatchSync();
    }

    public function internalQueueKey(): string
    {
        $envKey = trim((string) config('svp.queue_drain_key', ''));
        if ($envKey !== '') {
            return $envKey;
        }

        if (! app()->environment(['local', 'testing'])) {
            return '';
        }

        $settings = app(\App\Services\SettingsStore::class);
        $sec = (string) $settings->get('telegram_webhook_secret', '');
        if ($sec === '') {
            $sec = (string) $settings->get('bale_webhook_secret', '');
        }
        if ($sec === '') {
            return '';
        }

        return hash_hmac('sha256', 'svp_inbound_drain', $sec);
    }
}
