<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\File;

class EnsureSetupNotComplete
{
    /**
     * Handle an incoming request.
     * Only allow access to setup routes if setup is not complete.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if setup is marked as complete in .env
        $backendEnvPath = base_path('.env');
        $setupComplete = false;
        
        if (File::exists($backendEnvPath)) {
            $envContent = File::get($backendEnvPath);
            $setupComplete = preg_match('/^SETUP_COMPLETE\s*=\s*true/i', $envContent);
        }

        if ($setupComplete) {
            return response()->json([
                'error' => 'Setup has already been completed. These routes are no longer accessible.',
                'setup_complete' => true,
            ], 403);
        }

        return $next($request);
    }
}

