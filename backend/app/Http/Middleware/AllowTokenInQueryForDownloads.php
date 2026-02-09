<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When Authorization header is missing but request has ?token=... (e.g. from window.open),
 * set the header so Sanctum can authenticate. Used for invoice/receipt download URLs.
 */
class AllowTokenInQueryForDownloads
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->bearerToken() && $request->has('token')) {
            $request->headers->set('Authorization', 'Bearer ' . $request->query('token'));
        }

        return $next($request);
    }
}
