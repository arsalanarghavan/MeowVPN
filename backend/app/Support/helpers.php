<?php

use App\Modules\ModuleManager;

if (! function_exists('svp_modules')) {
    function svp_modules(): ModuleManager
    {
        return app(ModuleManager::class);
    }
}

if (! function_exists('svp_ok')) {
    /** @param  array<string, mixed>  $extra */
    function svp_ok(array $extra = []): array
    {
        return array_merge(['ok' => true], $extra);
    }
}

if (! function_exists('svp_err')) {
    /** @param  array<string, mixed>  $extra */
    function svp_err(string $message, array $extra = []): array
    {
        return array_merge(['ok' => false, 'message' => $message], $extra);
    }
}
