<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BroadcastMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $message
    ) {}

    public function handle(): void
    {
        $botToken = config('services.telegram.bot_token');
        if (!$botToken) {
            Log::warning('Telegram bot token not configured');
            return;
        }

        // Get all users with telegram_id
        $users = User::whereNotNull('telegram_id')
            ->chunk(100, function ($chunk) use ($botToken) {
                foreach ($chunk as $user) {
                    try {
                        Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                            'chat_id' => $user->telegram_id,
                            'text' => $this->message,
                            'parse_mode' => 'HTML',
                        ]);
                        
                        // Small delay to avoid rate limiting
                        usleep(100000); // 0.1 second
                    } catch (\Exception $e) {
                        Log::error("Failed to send broadcast to user {$user->id}: " . $e->getMessage());
                    }
                }
            });
    }
}

