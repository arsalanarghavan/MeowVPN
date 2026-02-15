<?php

namespace App\Services;

use App\Models\LoginConfirmation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function sendLoginConfirmationRequest(LoginConfirmation $confirmation): bool
    {
        $user = $confirmation->user;
        if (!$user->telegram_id) {
            return false;
        }

        $botToken = config('services.telegram.bot_token');
        if (!$botToken) {
            Log::warning('Telegram bot token not configured');
            return false;
        }

        $ip = $confirmation->ip_address ?? 'Unknown';
        $sessionId = $confirmation->session_token;

        $text = "🔑 Someone is trying to access your account\n"
            . "💻 IP: {$ip}\n\n"
            . "❕ This means that the first authentication step was passed, but access to the account has not been granted yet. "
            . "If this wasn't you, please change your password in account settings immediately.";

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Allow', 'callback_data' => "login_2fa:allow:{$sessionId}"],
                    ['text' => '❌ End Session', 'callback_data' => "login_2fa:reject:{$sessionId}"],
                ],
            ],
        ];

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $user->telegram_id,
                'text' => $text,
                'reply_markup' => json_encode($replyMarkup),
                'parse_mode' => 'HTML',
            ]);

            if (!$response->successful()) {
                Log::error('Telegram sendMessage failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram login confirmation: ' . $e->getMessage());
            return false;
        }
    }
}
