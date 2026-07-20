<?php

namespace App\Modules\PasarGuard\Services;

use Illuminate\Support\Facades\Cache;

class PasarGuardSessionStore
{
    private const TTL_SECONDS = 82800; // 23h — matches WP JWT transient

    public function tokenKey(int $panelId): string
    {
        return $panelId < 1 ? 'svp_pg_jwt_p0' : 'svp_pg_jwt_p'.$panelId;
    }

    public function getToken(int $panelId): string
    {
        return (string) Cache::get($this->tokenKey($panelId), '');
    }

    public function setToken(int $panelId, string $token): void
    {
        Cache::put($this->tokenKey($panelId), trim($token), self::TTL_SECONDS);
    }

    public function clear(int $panelId): void
    {
        Cache::forget($this->tokenKey($panelId));
    }
}
