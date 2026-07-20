<?php

namespace App\Modules\Core\Services\Portal;

use App\Models\SvpUser;
use App\Modules\Core\Bot\Services\AdminGuard;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class PortalLinkService
{
    public const CUSTOMER_TTL = 604800;

    public const ADMIN_TTL = 3600;

    public function __construct(
        protected SettingsStore $settings,
        protected AdminGuard $adminGuard,
    ) {}

    public function portalKey(): string
    {
        $k = (string) $this->settings->get('portal_link_secret', '');
        try {
            if ($k !== '' && str_starts_with($k, 'ey')) {
                $k = Crypt::decryptString($k);
            }
        } catch (\Throwable) {
            //
        }
        if ($k !== '' && strlen($k) >= 20) {
            return $k;
        }

        if (app()->environment('production')) {
            throw new \RuntimeException('SVP_PORTAL_LINK_SECRET must be set in production');
        }

        return hash('sha256', (string) config('app.key').'simplevpbot_portal_v1_dev_only');
    }

    public function verifyCustomerSignature(int $userId, int $exp, string $sig, int $serviceId = 0): ?SvpUser
    {
        if ($userId < 1 || $exp < time() || strlen($sig) < 8) {
            return null;
        }
        $payload = $serviceId > 0 ? "{$userId}|{$serviceId}|{$exp}" : "{$userId}|{$exp}";
        $check = hash_hmac('sha256', $payload, $this->portalKey());
        if (! hash_equals($check, $sig)) {
            return null;
        }
        $user = SvpUser::query()->find($userId);

        return ($user && $user->status === 'approved') ? $user : null;
    }

    public function verifyAdminSignature(int $userId, int $exp, string $sig): ?SvpUser
    {
        if ($userId < 1 || $exp < time() || strlen($sig) < 8) {
            return null;
        }
        $check = hash_hmac('sha256', 'admin|'.$userId.'|'.$exp, $this->portalKey().'|svp_admin_v1');
        if (! hash_equals($check, $sig)) {
            return null;
        }
        $user = SvpUser::query()->find($userId);
        if (! $user || ! $this->isPortalEligible($user)) {
            return null;
        }

        return $user;
    }

    public function isPortalEligible(SvpUser $user): bool
    {
        if ($user->role === 'reseller') {
            return true;
        }
        if ((int) ($user->tg_user_id ?? 0) > 0 && $this->adminGuard->isPlatformAdmin('telegram', (int) $user->tg_user_id)) {
            return true;
        }
        if ((int) ($user->bale_user_id ?? 0) > 0 && $this->adminGuard->isPlatformAdmin('bale', (int) $user->bale_user_id)) {
            return true;
        }

        return false;
    }

    public function verifyPortalNonce(int $userId, string $nonce): bool
    {
        if ($nonce === '' || $userId < 1) {
            return false;
        }
        $cacheKey = 'svp_portal_admin_nonce:'.$userId.':'.hash('sha256', $nonce);
        if (! Cache::add($cacheKey, 1, now()->addHours(2))) {
            return false;
        }
        $expected = hash_hmac('sha256', 'svp_portal_admin_'.$userId, $this->portalKey());

        return hash_equals($expected, $nonce);
    }

    public function portalNonce(int $userId): string
    {
        return hash_hmac('sha256', 'svp_portal_admin_'.$userId, $this->portalKey());
    }

    public function portalAvatarNonce(int $adminUserId, int $targetUserId): string
    {
        return hash_hmac('sha256', 'svp_portal_tgav_'.$adminUserId.'_'.$targetUserId, $this->portalKey());
    }

    public function verifyAvatarNonce(int $adminUserId, int $targetUserId, string $nonce): bool
    {
        if ($nonce === '') {
            return false;
        }

        return hash_equals($this->portalAvatarNonce($adminUserId, $targetUserId), $nonce);
    }

    public function avatarUrl(int $adminUserId, int $exp, string $sig, int $targetUserId): string
    {
        return url('/api/v1/portal/tg-avatar').'?'.http_build_query([
            'svp_u' => $adminUserId,
            'svp_e' => $exp,
            'svp_s' => $sig,
            'target_uid' => $targetUserId,
            'avnonce' => $this->portalAvatarNonce($adminUserId, $targetUserId),
        ]);
    }

    /** @return array{svp_u:int, svp_e:int, svp_s:string, nonce:string} */
    public function buildAdminLink(int $userId, int $ttl = self::ADMIN_TTL): array
    {
        $exp = time() + max(60, $ttl);
        $sig = hash_hmac('sha256', 'admin|'.$userId.'|'.$exp, $this->portalKey().'|svp_admin_v1');

        return [
            'svp_u' => $userId,
            'svp_e' => $exp,
            'svp_s' => $sig,
            'nonce' => $this->portalNonce($userId),
        ];
    }

    /** @return array{svp_u:int, svp_e:int, svp_s:string} */
    public function buildPortalLink(int $userId, int $ttl = self::CUSTOMER_TTL, int $serviceId = 0): array
    {
        $exp = time() + max(60, $ttl);
        $payload = $serviceId > 0 ? "{$userId}|{$serviceId}|{$exp}" : "{$userId}|{$exp}";
        $sig = hash_hmac('sha256', $payload, $this->portalKey());

        return [
            'svp_u' => $userId,
            'svp_e' => $exp,
            'svp_s' => $sig,
        ];
    }

    /** @return array{ok:bool, user_id?:int, service_id?:int} */
    public function resolveFromRequest(\Illuminate\Http\Request $request): array
    {
        $userId = (int) $request->query('svp_u', 0);
        $exp = (int) $request->query('svp_e', 0);
        $sig = (string) $request->query('svp_s', '');
        $serviceId = (int) $request->query('service_id', 0);
        if ($userId < 1 || $exp < 1 || $sig === '') {
            return ['ok' => false];
        }
        $user = $this->verifyCustomerSignature($userId, $exp, $sig, $serviceId);
        if ($user) {
            return ['ok' => true, 'user_id' => (int) $user->id, 'service_id' => $serviceId];
        }
        $admin = $this->verifyAdminSignature($userId, $exp, $sig);
        if ($admin) {
            return ['ok' => true, 'user_id' => (int) $admin->id, 'service_id' => $serviceId];
        }

        return ['ok' => false];
    }
}
