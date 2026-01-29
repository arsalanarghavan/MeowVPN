<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTelegramNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $telegramId,
        public string $message
    ) {}

    public function handle(): void
    {
        if (!$this->telegramId) {
            return;
        }

        $botToken = config('services.telegram.bot_token');
        if (!$botToken) {
            Log::warning('Telegram bot token not configured');
            return;
        }

        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $this->telegramId,
                'text' => $this->message,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send Telegram notification: " . $e->getMessage());
        }
    }
}

