<?php

namespace App\Modules\Core\Bot\Jobs;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReceiptReminderCronJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BotStateService $state, BotRuntime $runtime, TextService $texts): void
    {
        $afterMinutes = max(5, (int) config('svp.bot_receipt_reminder_minutes', 15));
        $cutoff = now()->subMinutes($afterMinutes);

        $users = SvpUser::query()
            ->where('state', 'awaiting_receipt_photo')
            ->limit(200)
            ->get();

        foreach ($users as $user) {
            $data = $state->data($user);
            $waitingSince = $this->parseTimestamp($data['waiting_since'] ?? null);
            if ($waitingSince === null) {
                $txId = (int) ($data['transaction_id'] ?? 0);
                if ($txId > 0) {
                    $txAt = DB::table('svp_transactions')->where('id', $txId)->value('created_at');
                    $waitingSince = $txAt ? Carbon::parse((string) $txAt) : null;
                }
            }
            if ($waitingSince === null || $waitingSince->greaterThan($cutoff)) {
                continue;
            }
            if (! empty($data['reminded_at'])) {
                $remindedAt = $this->parseTimestamp($data['reminded_at']);
                if ($remindedAt !== null && $remindedAt->greaterThan($cutoff)) {
                    continue;
                }
            }

            $txId = (int) ($data['transaction_id'] ?? 0);
            if ($txId < 1) {
                continue;
            }
            $tx = DB::table('svp_transactions')->where('id', $txId)->first();
            if (! $tx || (string) $tx->status !== 'pending') {
                continue;
            }
            $platform = (string) ($data['platform'] ?? 'telegram');
            $chatId = (int) ($data['chat_id'] ?? ($platform === 'bale' ? $user->bale_user_id : $user->tg_user_id));
            if ($chatId < 1) {
                continue;
            }
            $ctx = new BotContext($platform);
            $runtime->sendMessage($ctx, $chatId, $texts->getForUser('msg.buy.receipt_reminder', $user, 'Please upload your payment receipt photo.'));
            $state->mergeData($user, ['reminded_at' => now()->toIso8601String()]);
        }
    }

    protected function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
