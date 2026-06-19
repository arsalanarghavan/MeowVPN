<?php

namespace App\Modules\XuiPanel\Services;

use Illuminate\Support\Facades\Cache;

class XuiSessionStore
{
    private const TTL_SECONDS = 43200;

    public function cookieKey(int $panelId): string
    {
        return $panelId < 1 ? 'svp_xui_cookie_legacy' : 'svp_xui_cookie_p'.$panelId;
    }

    public function csrfKey(int $panelId): string
    {
        return $panelId < 1 ? 'svp_xui_csrf_legacy' : 'svp_xui_csrf_p'.$panelId;
    }

    public function noCsrfKey(int $panelId): string
    {
        return $panelId < 1 ? 'svp_xui_no_csrf' : 'svp_xui_no_csrf_p'.$panelId;
    }

    public function authBaseKey(int $panelId): string
    {
        return $panelId < 1 ? 'svp_xui_authbase' : 'svp_xui_authbase_p'.$panelId;
    }

    public function getCookie(int $panelId): string
    {
        return (string) Cache::get($this->cookieKey($panelId), '');
    }

    public function setCookie(int $panelId, string $cookie): void
    {
        Cache::put($this->cookieKey($panelId), $cookie, self::TTL_SECONDS);
    }

    public function getCsrf(int $panelId): string
    {
        return (string) Cache::get($this->csrfKey($panelId), '');
    }

    public function setCsrf(int $panelId, string $token): void
    {
        Cache::put($this->csrfKey($panelId), $token, self::TTL_SECONDS);
    }

    public function hasNoCsrf(int $panelId): bool
    {
        return (bool) Cache::get($this->noCsrfKey($panelId), false);
    }

    public function markNoCsrf(int $panelId): void
    {
        Cache::put($this->noCsrfKey($panelId), true, self::TTL_SECONDS);
    }

    public function getAuthBase(int $panelId): string
    {
        return (string) Cache::get($this->authBaseKey($panelId), '');
    }

    public function setAuthBase(int $panelId, string $base): void
    {
        Cache::put($this->authBaseKey($panelId), $base, self::TTL_SECONDS);
    }

    public function clear(int $panelId): void
    {
        Cache::forget($this->cookieKey($panelId));
        Cache::forget($this->csrfKey($panelId));
        Cache::forget($this->authBaseKey($panelId));
    }
}
