<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TelegramWebhookSecurity
{
    // Telegram IP ranges (as of 2024)
    private const TELEGRAM_IP_RANGES = [
        '149.154.160.0/20',
        '91.108.4.0/22',
        '91.108.8.0/22',
        '91.108.12.0/22',
        '91.108.16.0/22',
        '91.108.20.0/22',
        '91.108.56.0/22',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Check IP address
        $clientIp = $request->ip();
        
        if (!$this->isTelegramIp($clientIp)) {
            $secretToken = config('services.telegram.webhook_secret');
            if ($secretToken) {
                $headerToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
                if ($headerToken !== $secretToken) {
                    return response()->json(['error' => 'Unauthorized'], 401);
                }
            } elseif (app()->environment('production')) {
                // In production, reject non-Telegram IPs when secret is not set
                return response()->json(['error' => 'Unauthorized'], 401);
            } else {
                \Log::warning("Telegram webhook request from non-Telegram IP: {$clientIp}");
            }
        }

        return $next($request);
    }

    private function isTelegramIp(string $ip): bool
    {
        foreach (self::TELEGRAM_IP_RANGES as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }
        return false;
    }

    private function ipInRange(string $ip, string $range): bool
    {
        list($subnet, $mask) = explode('/', $range);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int)$mask);
        $subnetLong &= $maskLong;
        return ($ipLong & $maskLong) === $subnetLong;
    }
}

