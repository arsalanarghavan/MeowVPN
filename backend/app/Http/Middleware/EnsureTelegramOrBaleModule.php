<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTelegramOrBaleModule
{
    public function handle(Request $request, Closure $next): Response
    {
        $modules = svp_modules();
        if (! $modules->isEnabled('telegram') && ! $modules->isEnabled('bale')) {
            return response()->json(svp_err('module_disabled'), 403);
        }

        return $next($request);
    }
}
