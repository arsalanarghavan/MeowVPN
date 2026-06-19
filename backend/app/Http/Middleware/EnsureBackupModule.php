<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBackupModule
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! svp_modules()->isEnabled('backup')) {
            return response()->json(['ok' => false, 'message' => 'module_disabled'], 403);
        }

        return $next($request);
    }
}
