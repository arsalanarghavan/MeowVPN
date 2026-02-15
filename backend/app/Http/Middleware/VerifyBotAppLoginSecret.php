<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBotAppLoginSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.telegram.app_login_secret');

        if (empty($secret)) {
            return response()->json(['error' => 'App login not configured'], 503);
        }

        $provided = $request->header('X-Bot-Secret')
            ?? $request->bearerToken();

        if ($provided !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
