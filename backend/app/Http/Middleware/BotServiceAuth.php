<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BotServiceAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('svp.bot_service_secret', '');
        if ($expected === '') {
            return response()->json(['ok' => false, 'message' => 'bot_service_not_configured'], 503);
        }

        $secret = (string) $request->header('X-SVP-Bot-Service-Secret', '');
        if (! hash_equals($expected, $secret)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        $platform = strtolower((string) $request->header('X-SVP-Platform', ''));
        if ($platform !== '' && ! in_array($platform, ['telegram', 'bale'], true)) {
            return response()->json(['ok' => false, 'message' => 'invalid_platform'], 400);
        }

        $allowed = array_filter(array_map('trim', explode(',', (string) config('svp.bot_service_allowed_ips', ''))));
        if ($allowed !== []) {
            $ip = (string) $request->ip();
            if (! in_array($ip, $allowed, true)) {
                return response()->json(['ok' => false, 'message' => 'forbidden_ip'], 403);
            }
        }

        return $next($request);
    }
}
