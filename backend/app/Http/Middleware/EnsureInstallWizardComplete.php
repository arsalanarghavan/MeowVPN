<?php

namespace App\Http\Middleware;

use App\Services\InstallWizard\InstallWizardService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstallWizardComplete
{
    public function __construct(protected InstallWizardService $wizard) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->wizard->isOpen()) {
            return response()->json([
                'ok' => false,
                'code' => 'wizard_pending',
                'message' => 'Complete the install wizard before logging in.',
            ], 403);
        }

        return $next($request);
    }
}
