<?php

namespace App\Services\Reseller;

use App\Models\SvpResellerBotProfile;
use App\Models\SvpUser;
use App\Services\SettingsStore;
use App\Support\ConfigLink;
use App\Support\Xui\ServiceNaming;
use Illuminate\Support\Facades\DB;

class ResellerBrandingService
{
    /** @var array<int, int> */
    protected array $nearestResellerCache = [];

    public function __construct(protected SettingsStore $settings) {}

    public function nearestResellerIdForUser(int $svpUserId): int
    {
        $start = $svpUserId;
        if ($start < 1) {
            return 0;
        }
        if (array_key_exists($start, $this->nearestResellerCache)) {
            return (int) $this->nearestResellerCache[$start];
        }
        $id = $start;
        for ($i = 0; $i < 64; $i++) {
            if ($id < 1) {
                $this->nearestResellerCache[$start] = 0;

                return 0;
            }
            $u = SvpUser::query()->find($id);
            if (! $u) {
                $this->nearestResellerCache[$start] = 0;

                return 0;
            }
            if ((string) $u->role === 'reseller') {
                $this->nearestResellerCache[$start] = (int) $u->id;

                return (int) $u->id;
            }
            $inv = (int) ($u->invited_by ?? 0);
            if ($inv < 1) {
                $this->nearestResellerCache[$start] = 0;

                return 0;
            }
            $id = $inv;
        }
        $this->nearestResellerCache[$start] = 0;

        return 0;
    }

    public function displayBrandForReseller(int $resellerSvpUserId): string
    {
        $rid = $resellerSvpUserId;
        if ($rid < 1) {
            return '';
        }
        $prof = SvpResellerBotProfile::query()->where('reseller_svp_user_id', $rid)->first();
        $bn = $prof ? trim((string) ($prof->brand_name ?? '')) : '';
        if ($bn !== '') {
            return $bn;
        }
        $u = SvpUser::query()->find($rid);
        if (! $u) {
            return '';
        }
        $name = trim((string) ($u->first_name ?? '').' '.(string) ($u->last_name ?? ''));
        if ($name !== '') {
            return $name;
        }
        $un = trim((string) ($u->username ?? ''));

        return $un !== '' ? '@'.$un : '';
    }

    public function configLabelOverrideForUser(int $svpUserId): string
    {
        $rid = $this->nearestResellerIdForUser($svpUserId);
        if ($rid > 0) {
            $prof = SvpResellerBotProfile::query()->where('reseller_svp_user_id', $rid)->first();
            $ov = $prof ? trim((string) ($prof->config_label_override ?? '')) : '';
            if ($ov !== '') {
                return $ov;
            }
        }

        return trim((string) $this->settings->get('subscription_config_label_override', ''));
    }

    public function configLabelPrefixForUser(int $svpUserId): string
    {
        $rid = $this->nearestResellerIdForUser($svpUserId);
        if ($rid > 0) {
            $prof = SvpResellerBotProfile::query()->where('reseller_svp_user_id', $rid)->first();
            $pref = $prof ? trim((string) ($prof->config_label_prefix ?? '')) : '';
            if ($pref !== '') {
                return $pref;
            }
        }
        $pref = trim((string) $this->settings->get('config_label_prefix', ''));
        if ($pref !== '') {
            return $pref;
        }
        if ($rid > 0) {
            $brand = $this->displayBrandForReseller($rid);
            if ($brand !== '') {
                return $brand;
            }
        }

        return trim((string) $this->settings->get('dashboard_site_name', ''));
    }

    public function brandFragmentForUser(int $svpUserId): string
    {
        $rid = $this->nearestResellerIdForUser($svpUserId);
        if ($rid < 1) {
            return '';
        }

        return $this->displayBrandForReseller($rid);
    }

    public function effectiveBrandForUser(int $svpUserId): string
    {
        return $this->brandFragmentForUser($svpUserId);
    }

    public function fragmentForService(int $svpUserId, string $serviceRemark): string
    {
        $brand = trim($this->effectiveBrandForUser($svpUserId));
        if ($brand === '') {
            return '';
        }
        $svc = trim($serviceRemark);
        if ($svc === '') {
            return $brand;
        }

        return $brand.'-'.$svc;
    }

    public function panelClientNameForUser(int $svpUserId, string $serviceRemark): string
    {
        if ($this->isPlatformSlugRemark($serviceRemark)) {
            $brand = $this->panelBrandOnlyForUser($svpUserId);
            if ($brand !== '') {
                return $this->limitText($brand, 50);
            }
        }
        $frag = $this->fragmentForService($svpUserId, $serviceRemark);
        if ($frag === '') {
            return trim($serviceRemark);
        }

        return $this->limitText($frag, 50);
    }

    public function panelBrandOnlyForUser(int $svpUserId): string
    {
        $brand = trim($this->effectiveBrandForUser($svpUserId));
        if ($brand !== '') {
            return $brand;
        }
        $site = trim((string) $this->settings->get('dashboard_site_name', ''));
        if ($site !== '') {
            return $site;
        }

        return (string) config('app.name', 'MeowVPN');
    }

    /**
     * @param  array<int, string>  $uris
     * @return array<int, string>
     */
    public function rewriteSubscriptionUrisForUser(array $uris, int $svpUserId, string $serviceRemark = '', ?object $svc = null): array
    {
        $baseFrag = $this->fragmentForService($svpUserId, $serviceRemark);
        $brand = $this->brandFragmentForUser($svpUserId);
        if ($baseFrag === '' && $brand === '') {
            return $uris;
        }
        $multi = count($uris) > 1;
        $idx = 1;
        $out = [];
        foreach ($uris as $u) {
            $frag = $multi ? ($baseFrag !== '' ? $baseFrag.'-'.$idx : $brand.'-'.$idx) : ($baseFrag !== '' ? $baseFrag : $brand);
            if ($frag === '') {
                $out[] = (string) $u;
            } else {
                $current = trim(ConfigLink::uriFragmentLabel((string) $u));
                $out[] = ($current === '' || $current !== $frag)
                    ? ConfigLink::replaceUriFragment((string) $u, $frag)
                    : (string) $u;
            }
            $idx++;
        }

        return $out;
    }

    public function portalHeaderTitleForUser(int $svpUserId): string
    {
        $frag = $this->effectiveBrandForUser($svpUserId);
        if ($frag !== '') {
            return $frag;
        }

        return trim((string) $this->settings->get('dashboard_site_name', config('app.name', 'MeowVPN')));
    }

    protected function isPlatformSlugRemark(string $remark): bool
    {
        $r = trim($remark);
        if ($r === '') {
            return false;
        }

        return (bool) preg_match('/^(tg|bale|telegram)_[a-z0-9_-]+$/i', $r)
            || ServiceNaming::usesPlatformSlugForNew();
    }

    protected function limitText(string $text, int $max): string
    {
        $in = trim($text);
        if ($max < 1) {
            return $in;
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($in, 'UTF-8') > $max ? mb_substr($in, 0, $max, 'UTF-8') : $in;
        }

        return strlen($in) > $max ? substr($in, 0, $max) : $in;
    }
}
