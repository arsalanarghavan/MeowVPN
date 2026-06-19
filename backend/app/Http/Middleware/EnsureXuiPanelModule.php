<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureXuiPanelModule
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! svp_modules()->isEnabled('xui_panel')) {
            return response()->json(svp_err('module_disabled'), 403);
        }

        return $next($request);
    }
}
