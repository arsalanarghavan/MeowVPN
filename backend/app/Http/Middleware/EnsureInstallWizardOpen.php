<?php

namespace App\Http\Middleware;

use App\Services\InstallWizard\InstallWizardService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstallWizardOpen
{
    public function __construct(protected InstallWizardService $wizard) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->wizard->isOpen()) {
            return response()->json([
                'ok' => false,
                'code' => 'wizard_closed',
                'message' => 'Install wizard is not available.',
            ], 410);
        }

        $token = (string) ($request->header('X-Install-Token') ?: $request->query('token', ''));
        if (! $this->wizard->validateToken($token)) {
            return response()->json([
                'ok' => false,
                'code' => 'invalid_install_token',
                'message' => 'Invalid or missing install token.',
            ], 403);
        }

        return $next($request);
    }
}
