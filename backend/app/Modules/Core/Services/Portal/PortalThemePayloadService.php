<?php

namespace App\Modules\Core\Services\Portal;

use App\Models\SvpUser;
use App\Services\SettingsStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PortalThemePayloadService
{
    public function __construct(
        protected PortalConfigUriCollector $uriCollector,
        protected SettingsStore $settings,
        protected PortalLinkService $portal,
    ) {}

    /**
     * Build SPA theme bootstrap payload (`window.__SIMPLEVPBOT_PORTAL__` shape).
     *
     * @return array<string, mixed>
     */
    public function build(SvpUser $user, int $serviceId = 0, ?Request $request = null): array
    {
        $services = $this->loadServices((int) $user->id, $serviceId);
        $cards = [];
        $allLinks = [];
        $primary = null;

        foreach ($services as $svc) {
            $card = $this->serviceCard($user, $svc);
            $cards[] = $card;
            if ($primary === null) {
                $primary = $card;
            }
            foreach ($card['links'] as $link) {
                $link = (string) $link;
                if ($link !== '' && ! in_array($link, $allLinks, true)) {
                    $allLinks[] = $link;
                }
            }
        }

        if ($primary === null) {
            return [
                'user' => null,
                'links' => [],
                'apps' => null,
                'apps_clients' => [],
                'meta' => $this->metaPayload('', $request, $user, $serviceId),
                'cards' => [],
            ];
        }

        $subUrl = (string) ($primary['subscription_url'] ?? '');

        return [
            'user' => $primary['user'],
            'links' => $allLinks !== [] ? $allLinks : $primary['links'],
            'link_items' => $primary['link_items'] ?? [],
            'service_id' => (int) ($primary['service_id'] ?? 0),
            'apps' => null,
            'apps_clients' => [],
            'meta' => $this->metaPayload($subUrl, $request, $user, (int) ($primary['service_id'] ?? $serviceId)),
            'chart' => $primary['chart'],
            'cards' => $cards,
        ];
    }

    /**
     * @return list<object>
     */
    protected function loadServices(int $userId, int $serviceId): array
    {
        if (! Schema::hasTable('svp_services')) {
            return [];
        }

        $q = DB::table('svp_services')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where('service_type', '!=', 'l2tp')
            ->orderByDesc('id');

        if ($serviceId > 0) {
            $q->where('id', $serviceId);
        }

        return $q->get()->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serviceCard(SvpUser $user, object $svc): array
    {
        $collected = $this->uriCollector->collect($user, (int) $svc->id);
        $uris = array_values(array_filter(array_map('strval', $collected['uris'] ?? [])));

        $total = (int) ($svc->total_traffic ?? 0);
        $used = (int) ($svc->used_traffic ?? 0);
        $quotaHidden = \App\Support\PlanQuotaDisplay::isHiddenForService($svc);
        $displayTotal = $quotaHidden ? 0 : $total;
        $name = trim((string) ($svc->display_label ?? $svc->remark ?? $svc->email ?? ''));
        if ($name === '') {
            $name = 'Service #'.(int) $svc->id;
        }

        $expireIso = null;
        if (! empty($svc->expires_at)) {
            $ts = strtotime((string) $svc->expires_at.' UTC');
            if ($ts) {
                $expireIso = gmdate('c', $ts);
            }
        }

        $createdIso = null;
        if (! empty($svc->created_at)) {
            $cts = strtotime((string) $svc->created_at.' UTC');
            if ($cts) {
                $createdIso = gmdate('c', $cts);
            }
        }

        $status = $this->mapStatus($svc, $total, $used); // real caps for status (limited/expired)
        $subUrl = $this->pickSubscriptionUrl($uris);
        if ($subUrl === '') {
            $link = $this->portal->buildPortalLink((int) $user->id, PortalLinkService::CUSTOMER_TTL, (int) $svc->id);
            $subUrl = url('/info').'?'.http_build_query([
                'svp_p' => '1',
                'svp_u' => $link['svp_u'],
                'svp_sid' => (int) $svc->id,
                'svp_e' => $link['svp_e'],
                'svp_s' => $link['svp_s'],
                'svp_fmt' => 'sub',
            ]);
        }

        $linkItems = [];
        foreach ($uris as $uri) {
            $uri = trim((string) $uri);
            if ($uri === '') {
                continue;
            }
            $linkItems[] = ['uri' => $uri, 'label' => ''];
        }
        if ($linkItems === [] && $subUrl !== '') {
            $linkItems[] = ['uri' => $subUrl, 'label' => ''];
            $uris = [$subUrl];
        }

        return [
            'service_id' => (int) $svc->id,
            'subscription_url' => $subUrl,
            'links' => $uris,
            'link_items' => $linkItems,
            'user' => [
                'username' => $name,
                'status' => $status,
                'data_limit' => $displayTotal > 0 ? $displayTotal : 0,
                'used_traffic' => $used,
                'lifetime_used_traffic' => $used,
                'expire' => $expireIso,
                'created_at' => $createdIso,
                'id' => (int) $svc->id,
                'quota_hidden_from_user' => $quotaHidden ? 1 : 0,
            ],
            'chart' => [
                'up' => 0,
                'down' => $used,
                'total' => $displayTotal,
                'used' => $used,
                'ranges' => [],
            ],
        ];
    }

    protected function mapStatus(object $svc, int $total, int $used): string
    {
        if (! empty($svc->expires_at)) {
            $ts = strtotime((string) $svc->expires_at.' UTC');
            if ($ts && $ts < time()) {
                return 'expired';
            }
        }
        if ($total > 0 && $used >= $total) {
            return 'limited';
        }
        $status = strtolower((string) ($svc->status ?? 'active'));

        return in_array($status, ['active', 'disabled', 'expired', 'limited', 'on_hold'], true)
            ? $status
            : 'active';
    }

    /**
     * @param  list<string>  $uris
     */
    protected function pickSubscriptionUrl(array $uris): string
    {
        foreach ($uris as $uri) {
            $uri = trim((string) $uri);
            if ($uri !== '' && preg_match('#^https?://#i', $uri)) {
                return $uri;
            }
        }

        return isset($uris[0]) ? (string) $uris[0] : '';
    }

    /**
     * Prefer admin subscription template; fall back to legacy portal_theme.
     */
    protected function resolveThemeKey(): string
    {
        $template = trim((string) $this->settings->get('portal_subscription_template', ''));
        if ($template !== '') {
            return $template;
        }

        return trim((string) $this->settings->get('portal_theme', ''));
    }

    /**
     * @return array<string, mixed>
     */
    protected function metaPayload(string $subscriptionUrl, ?Request $request, SvpUser $user, int $serviceId): array
    {
        $brandName = trim((string) $this->settings->get('portal_theme_brand_name', ''));
        if ($brandName === '') {
            $brandName = trim((string) $this->settings->get('dashboard_site_name', ''));
        }
        if ($brandName === '') {
            $brandName = trim((string) $this->settings->get('site_name', config('app.name', 'MeowVPN')));
        }

        $tagline = trim((string) $this->settings->get('portal_theme_brand_tagline', ''));
        $support = trim((string) $this->settings->get('support_info', ''));
        $tg = trim((string) $this->settings->get('support_telegram_username', ''));
        if ($support === '' && $tg !== '') {
            $support = 'https://t.me/'.ltrim($tg, '@');
        }

        $datepicker = (string) $this->settings->get('portal_datepicker', 'jalali');
        if (! in_array($datepicker, ['gregorian', 'jalali'], true)) {
            $datepicker = 'jalali';
        }

        $locale = (string) $this->settings->get('default_bot_locale', 'fa');
        if (! in_array($locale, ['fa', 'en', 'zh', 'ru'], true)) {
            $locale = 'fa';
        }

        $announce = trim((string) $this->settings->get('portal_theme_announce', ''));
        if ($announce === '') {
            $announce = $tagline;
        }
        $headers = [];
        if ($announce !== '') {
            $headers['announce'] = $announce;
        }
        $announceUrl = trim((string) $this->settings->get('portal_theme_announce_url', ''));
        if ($announceUrl !== '') {
            $headers['announce-url'] = $announceUrl;
        }
        if ($support !== '') {
            $headers['support-url'] = $support;
        }

        $authQs = $this->authQueryString($request, (int) $user->id, $serviceId);

        return [
            'subscription_url' => $subscriptionUrl,
            'support_url' => $support,
            'usage_endpoint' => url('/api/v1/portal/usage'),
            'auth_qs' => $authQs,
            'siteTimeZone' => (string) config('app.timezone', 'UTC'),
            'theme' => $this->resolveThemeKey(),
            'datepicker' => $datepicker,
            'branding' => [
                'name' => $brandName,
                'tagline' => $tagline,
                'logo' => trim((string) $this->settings->get('branding_logo_url', '')),
            ],
            'appearance' => [
                'primary_light' => trim((string) $this->settings->get('portal_theme_primary_light', '')),
                'primary_dark' => trim((string) $this->settings->get('portal_theme_primary_dark', '')),
                'radius' => trim((string) $this->settings->get('portal_theme_radius', '')),
            ],
            'locale' => $locale,
            'headers' => $headers,
        ];
    }

    protected function authQueryString(?Request $request, int $userId, int $serviceId): string
    {
        if ($request) {
            $uid = (int) $request->query('svp_u', $request->query('uid', 0));
            $exp = (int) $request->query('svp_e', $request->query('exp', 0));
            $sig = (string) $request->query('svp_s', $request->query('sig', ''));
            if ($uid > 0 && $exp > 0 && $sig !== '') {
                $q = [
                    'svp_u' => $uid,
                    'svp_e' => $exp,
                    'svp_s' => $sig,
                ];
                $sid = (int) $request->query('svp_sid', $request->query('service_id', $serviceId));
                if ($sid > 0) {
                    $q['svp_sid'] = $sid;
                    $q['service_id'] = $sid;
                }

                return http_build_query($q);
            }
        }

        $link = $this->portal->buildPortalLink($userId, PortalLinkService::CUSTOMER_TTL, $serviceId);
        $q = [
            'svp_u' => $link['svp_u'],
            'svp_e' => $link['svp_e'],
            'svp_s' => $link['svp_s'],
        ];
        if ($serviceId > 0) {
            $q['svp_sid'] = $serviceId;
            $q['service_id'] = $serviceId;
        }

        return http_build_query($q);
    }
}
