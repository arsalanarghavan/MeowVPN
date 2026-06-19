<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RedactSecretsInLogs
{
    /** @var list<string> */
    protected const SENSITIVE_KEYS = [
        'token', 'secret', 'password', 'api_key', 'authorization', 'panel_password',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (Log::getLogger() !== null && $this->shouldLogRequest($request)) {
            $safe = $this->redactArray($request->all());
            $level = config('app.debug') ? 'debug' : 'info';
            Log::channel('svp')->{$level}('request', [
                'path' => $request->path(),
                'payload' => $safe,
            ]);
        }

        return $next($request);
    }

    /** @param  array<string, mixed>  $data */
    protected function redactArray(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $k = strtolower((string) $key);
            if ($this->isSensitiveKey($k)) {
                $out[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $out[$key] = $this->redactArray($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    protected function shouldLogRequest(Request $request): bool
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return false;
        }

        return str_starts_with($request->path(), 'api/v1');
    }

    protected function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
