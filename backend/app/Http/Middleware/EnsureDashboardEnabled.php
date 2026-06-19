<?php

namespace App\Http\Middleware;

use App\Services\SettingsStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDashboardEnabled
{
    public function __construct(protected SettingsStore $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) $this->settings->get('dashboard_enabled', true)) {
            return response()->json(svp_err('dashboard_disabled'), 503);
        }

        return $next($request);
    }
}
