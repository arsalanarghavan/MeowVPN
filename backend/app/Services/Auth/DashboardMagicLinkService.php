<?php

namespace App\Services\Auth;

use App\Models\DashboardUser;
use App\Modules\Core\Services\Portal\PortalLinkService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DashboardMagicLinkService
{
    public const TTL = 300;

    public function __construct(
        protected SettingsStore $settings,
        protected PortalLinkService $portalLinks,
        protected DashboardTelegramAuth $telegramAuth,
    ) {}

    public function magicKey(): string
    {
        return $this->portalLinks->portalKey().'|svp_dash_magic_v1';
    }

    public function buildUrl(string $platform, int $platformUserId, ?string $locale = null): string
    {
        $plat = in_array($platform, ['telegram', 'bale'], true) ? $platform : 'telegram';
        $uid = max(1, $platformUserId);
        $exp = time() + self::TTL;
        $nonce = Str::random(12);
        $msg = 'dash_login|'.$plat.'|'.$uid.'|'.$exp.'|'.$nonce;
        $sig = hash_hmac('sha256', $msg, $this->magicKey());
        $base = rtrim($this->frontendBase(), '/');
        $loc = $locale ?: (string) config('svp.dashboard_default_locale', 'fa');

        return $base.'/'.$loc.'/dashboard/auth/magic?'.http_build_query([
            'svp_dl' => '1',
            'svp_p' => $plat,
            'svp_uid' => (string) $uid,
            'svp_e' => (string) $exp,
            'svp_n' => $nonce,
            'svp_s' => $sig,
        ]);
    }

    /** @param  array<string, mixed>  $params */
    public function verifyParams(array $params): bool
    {
        if (empty($params['svp_dl'])) {
            return false;
        }
        $plat = Str::slug((string) ($params['svp_p'] ?? ''));
        $uid = (int) ($params['svp_uid'] ?? 0);
        $exp = (int) ($params['svp_e'] ?? 0);
        $nonce = (string) ($params['svp_n'] ?? '');
        $sig = (string) ($params['svp_s'] ?? '');
        if (! in_array($plat, ['telegram', 'bale'], true) || $uid < 1 || $exp < time() || $nonce === '' || strlen($sig) < 8) {
            return false;
        }
        $msg = 'dash_login|'.$plat.'|'.$uid.'|'.$exp.'|'.$nonce;
        $check = hash_hmac('sha256', $msg, $this->magicKey());

        return hash_equals($check, $sig);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{ok:bool, code?:string, redirect?:string}
     */
    public function consume(array $params, bool $remember = true): array
    {
        if (! $this->verifyParams($params)) {
            return ['ok' => false, 'code' => 'invalid_link'];
        }
        $plat = Str::slug((string) ($params['svp_p'] ?? ''));
        $uid = (int) ($params['svp_uid'] ?? 0);
        $nonce = (string) ($params['svp_n'] ?? '');
        $usedKey = 'svp_dash_magic_used_'.md5($plat.'|'.$uid.'|'.$nonce);
        if (Cache::has($usedKey)) {
            return ['ok' => false, 'code' => 'used_link'];
        }
        $dashUser = $this->telegramAuth->resolveDashboardUserForPlatform($plat, $uid);
        if (! $dashUser) {
            return ['ok' => false, 'code' => 'not_linked'];
        }
        Cache::put($usedKey, 1, self::TTL);
        Auth::guard('web')->login($dashUser, $remember);
        request()->session()->regenerate();

        return [
            'ok' => true,
            'redirect' => url('/dashboard/'),
        ];
    }

    protected function frontendBase(): string
    {
        $configured = rtrim((string) $this->settings->get('dashboard_public_url', ''), '/');
        if ($configured !== '') {
            return $configured;
        }
        $env = rtrim((string) env('FRONTEND_URL', ''), '/');
        if ($env !== '') {
            return $env;
        }

        return rtrim((string) config('app.url', ''), '/');
    }
}
