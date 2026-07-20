<?php

namespace App\Services;

use App\Models\DashboardUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardBootBuilder
{
    public function __construct(
        protected SettingsStore $settings,
        protected NavTabsBuilder $navTabs,
        protected ImpersonationService $impersonation,
    ) {}

    /** @return array<string, mixed> */
    public function forRequest(Request $request, ?string $dashPath = null): array
    {
        $path = $dashPath ?? $this->detectDashPath($request);
        $isLogin = $path === 'login';
        $user = Auth::guard('web')->user();

        if (! $user instanceof DashboardUser) {
            return $this->guestBoot($request, $isLogin);
        }

        return $this->authenticatedBoot($request, $user, $path);
    }

    /** @return array<string, mixed> */
    protected function guestBoot(Request $request, bool $isLogin): array
    {
        $lang = $this->lang($request);

        $boot = [
            'restUrl' => url('/api/v1'),
            'nonce' => '',
            'locale' => $lang === 'fa' ? 'fa_IR' : 'en_US',
            'lang' => $lang,
            'isRtl' => $lang === 'fa',
            'isLoggedIn' => false,
            'isAdmin' => false,
            'isReseller' => false,
            'svpUserId' => 0,
            'dashboardUrl' => url('/dashboard/'),
            'dashboardLoginUrl' => url('/dashboard/login'),
            'logoutUrl' => url('/dashboard/login'),
            'siteName' => (string) $this->settings->get('site_name', 'SimpleVPBot'),
            'siteIconUrl' => (string) $this->settings->get('site_icon_url', ''),
            'dashPath' => $isLogin ? 'login' : '',
            'siteTimeZone' => (string) config('app.timezone', 'UTC'),
            'loginNonce' => 'sanctum',
        ];

        return $this->applyBranding($boot);
    }

    /** @return array<string, mixed> */
    protected function authenticatedBoot(Request $request, DashboardUser $user, string $dashPath): array
    {
        $lang = $this->lang($request);
        $isAdmin = $user->role === 'admin';
        $isReseller = $user->role === 'reseller';
        $svpUserId = (int) ($user->svp_user_id ?? 0);

        $boot = [
            'restUrl' => url('/api/v1'),
            'nonce' => '',
            'locale' => $lang === 'fa' ? 'fa_IR' : 'en_US',
            'lang' => $lang,
            'isRtl' => $lang === 'fa',
            'isLoggedIn' => true,
            'isAdmin' => $isAdmin,
            'isReseller' => $isReseller,
            'svpUserId' => $svpUserId,
            'user' => $this->sidebarUser($user),
            'actorPermissions' => $isReseller ? $this->resellerPermissions($user) : null,
            'activePersona' => $this->resolveActivePersona($request, $user),
            'availablePersonas' => $this->availablePersonas($user),
            'impersonating' => false,
            'impersonationTargetId' => 0,
            'impersonationTargetLabel' => '',
            'loginUrl' => url('/dashboard/login'),
            'dashboardUrl' => url('/dashboard/'),
            'dashboardLoginUrl' => url('/dashboard/login'),
            'logoutUrl' => url('/api/v1/auth/logout'),
            'siteName' => (string) $this->settings->get('site_name', 'SimpleVPBot'),
            'siteIconUrl' => (string) $this->settings->get('site_icon_url', ''),
            'dashPath' => $dashPath,
            'siteTimeZone' => (string) config('app.timezone', 'UTC'),
            'uiAccent' => (string) ($user->ui_accent ?: $this->settings->get('ui_accent', 'default')),
            'uiTheme' => (string) ($user->ui_theme ?? ''),
            'uiSidebar' => (string) ($user->ui_sidebar ?? ''),
            'features' => $this->features(),
            'branding' => $this->brandingPayload(),
            'navTabs' => $this->navTabsForUser($user),
            'resellerAllowedTabs' => $isReseller ? $this->resellerAllowedTabsMap($user) : null,
        ];

        return $this->impersonation->applyToBoot($this->applyBranding($boot), $user, $request);
    }

    /** @return array<string, mixed> */
    public function bootstrapApiPayload(DashboardUser $user): array
    {
        return $this->authenticatedBoot(request(), $user, '');
    }

    /** @return array<string, mixed> */
    public function publicBootstrapPayload(Request $request): array
    {
        $boot = $this->guestBoot($request, false);
        $boot['loginRequired'] = true;
        $boot['features'] = $this->features();
        $boot['branding'] = $this->brandingPayload();

        return $boot;
    }

    protected function detectDashPath(Request $request): string
    {
        $path = trim($request->path(), '/');
        if (str_starts_with($path, 'dashboard/')) {
            $sub = substr($path, strlen('dashboard/'));

            return $sub === 'login' ? 'login' : explode('/', $sub)[0] ?? '';
        }

        return $path === 'dashboard/login' ? 'login' : '';
    }

    protected function lang(Request $request): string
    {
        $locale = (string) $request->getPreferredLanguage(['fa', 'en']);

        return str_starts_with($locale, 'fa') ? 'fa' : 'en';
    }

    /** @return array<string, mixed> */
    protected function features(): array
    {
        $modules = svp_modules();

        return [
            'telegram' => $modules->isEnabled('telegram'),
            'bale' => $modules->isEnabled('bale'),
            'xui_panel' => $modules->isEnabled('xui_panel'),
            'xray_core' => $modules->isEnabled('xray_core'),
            'tunnel' => $modules->isEnabled('tunnel'),
            'relay' => $modules->isEnabled('relay'),
            'crypto' => $modules->isEnabled('crypto'),
            'l2tp' => $modules->isEnabled('l2tp'),
            'marketing' => $modules->isEnabled('marketing'),
            'reseller' => $modules->isEnabled('reseller'),
            'backup' => $modules->isEnabled('backup'),
        ];
    }

    /** @return array<string, mixed> */
    protected function brandingPayload(): array
    {
        $packed = \App\Services\BrandingResolver::packFromSettings($this->settings);
        $whitelabel = $this->settings->get('whitelabel', []);
        if (is_array($whitelabel) && ! empty($whitelabel['cssVariables']) && is_array($whitelabel['cssVariables'])) {
            $packed['cssVariables'] = $whitelabel['cssVariables'];
        }

        return [
            'cssVariables' => $packed['cssVariables'] ?? [],
            'customDomain' => (string) ($packed['customDomain'] ?? ''),
        ];
    }

    /** @param  array<string, mixed>  $boot */
    protected function applyBranding(array $boot): array
    {
        $boot['branding'] = $boot['branding'] ?? $this->brandingPayload();

        return $boot;
    }

    /** @return array{label: string}|null */
    protected function sidebarUser(DashboardUser $user): ?array
    {
        return [
            'label' => $user->username,
        ];
    }

    /** @return array<string, bool>|null */
    protected function resellerPermissions(DashboardUser $user): ?array
    {
        if ($user->role !== 'reseller') {
            return null;
        }

        $perms = $user->permissions_json;
        if (is_array($perms) && $perms !== []) {
            return $perms;
        }

        return [
            'users.manage' => false,
            'plans.manage' => false,
            'receipts.review' => false,
            'services.manage' => false,
            'broadcast.send' => false,
            'users.bulk' => false,
            'marketing.lifecycle' => false,
        ];
    }

    protected function resolveActivePersona(Request $request, DashboardUser $user): string
    {
        $available = $this->availablePersonas($user);
        $session = (string) $request->session()->get('svp_active_persona', '');
        if ($session !== '' && in_array($session, $available, true)) {
            return $session;
        }

        return $user->role === 'reseller' ? 'reseller' : ($user->role === 'admin' ? 'admin' : 'user');
    }

    /** @return list<string> */
    protected function availablePersonas(DashboardUser $user): array
    {
        if ($user->role === 'admin') {
            return ['admin', 'user'];
        }
        if ($user->role === 'reseller') {
            return ['reseller'];
        }

        return ['user'];
    }

    /** @return list<array{key: string, label: string}> */
    protected function navTabsForUser(DashboardUser $user): array
    {
        $l2tp = (bool) ($this->features()['l2tp'] ?? false);
        $tabs = $this->navTabs->build($l2tp);

        if ($user->role === 'reseller') {
            $allowed = $this->resellerAllowedTabsMap($user);

            return $this->navTabs->filterForReseller($tabs, $allowed);
        }

        return $tabs;
    }

    /** @return array<string, bool> */
    public function resellerAllowedTabsMap(DashboardUser $user): array
    {
        $perms = $this->resellerPermissions($user) ?? [];
        $map = [
            'dashboard' => true,
            'users' => ! empty($perms['users.manage']),
            'users_bulk' => ! empty($perms['users.bulk']),
            'plans' => ! empty($perms['plans.manage']),
            'plan_cats' => ! empty($perms['plans.manage']),
            'cards' => ! empty($perms['plans.manage']),
            'receipts' => ! empty($perms['receipts.review']),
            'payments' => ! empty($perms['receipts.review']),
            'broadcast' => ! empty($perms['broadcast.send']),
            'monitoring' => ! empty($perms['services.manage']),
            'reseller_bots' => ! empty($perms['services.manage']),
            'bot_ui' => ! empty($perms['services.manage']),
            'marketing_lifecycle' => ! empty($perms['marketing.lifecycle']),
            'discounts' => ! empty($perms['plans.manage']),
            'referral' => ! empty($perms['users.manage']),
            'referral_reports' => ! empty($perms['users.manage']),
            'resellers' => ! empty($perms['users.manage']),
            'reseller_reports' => ! empty($perms['users.manage']),
            'reseller_charge' => ! empty($perms['plans.manage']),
            'reseller_settings' => true,
            'reseller_xui_panels' => ! empty($perms['services.manage']),
        ];

        return array_filter($map);
    }
}
