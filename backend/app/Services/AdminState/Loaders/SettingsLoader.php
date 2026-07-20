<?php

namespace App\Services\AdminState\Loaders;

use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use App\Services\DashboardBootBuilder;
use App\Services\Migration\SensitiveSettings;
use App\Services\ResellerDefaultsService;
use App\Services\SettingsStore;

class SettingsLoader extends AbstractLoader
{
    public function __construct(
        protected SettingsStore $settings,
        protected DashboardBootBuilder $bootBuilder,
        protected ResellerDefaultsService $resellerDefaults,
        protected SensitiveSettings $sensitive,
    ) {}

    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return true;
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $boot = $this->bootBuilder->bootstrapApiPayload($ctx->actor);
        $all = $this->redactSecrets($this->settings->all());
        $all = array_merge($this->siteSettingsDefaults(), $all);
        $all['features'] = $boot['features'] ?? [];
        $all = $this->enrichGatewaySettings($all);

        if ($ctx->isReseller) {
            foreach (['telegram_bot_token', 'bale_bot_token', 'relay_master_secret'] as $secret) {
                unset($all[$secret]);
            }
        }

        $all['resellers_defaults'] = $this->resellerDefaults->forAdminState();

        $result->merge([
            'settings' => $all,
            'resellersDefaults' => $this->resellerDefaults->forAdminState(),
            'textDefaults' => is_array($all['text_defaults'] ?? null) ? $all['text_defaults'] : [],
            'paymentMethods' => $all['payment_methods'] ?? null,
        ]);
    }

    /** @param  array<string, mixed>  $all
     *  @return array<string, mixed>
     */
    protected function enrichGatewaySettings(array $all): array
    {
        $secretKeys = [
            'crypto_nowpayments_api_key',
            'crypto_nowpayments_ipn_secret',
            'crypto_ipn_path_secret',
            'crypto_tetra_api_key',
            'crypto_tetra_callback_path_secret',
            'zarinpal_merchant_id',
            'zarinpal_callback_path_secret',
            'aqayepardakht_pin',
            'aqayepardakht_callback_path_secret',
            'zibal_merchant',
            'zibal_callback_path_secret',
        ];
        foreach ($secretKeys as $k) {
            $val = $all[$k] ?? null;
            if ($val !== null && $val !== '' && $val !== '••••••••') {
                $all[$k.'_set'] = true;
            } elseif ($val === '••••••••') {
                $all[$k.'_set'] = true;
            }
        }

        $base = rtrim((string) ($all['public_site_url'] ?? config('app.url')), '/');
        $tetraPath = (string) ($this->settings->get('crypto_tetra_callback_path_secret', '') ?? '');
        if ($tetraPath !== '') {
            $all['crypto_tetra_callback_url'] = $base.'/api/v1/tetra-callback/'.rawurlencode($tetraPath);
        }
        $zarinPath = (string) ($this->settings->get('zarinpal_callback_path_secret', '') ?? '');
        if ($zarinPath !== '') {
            $all['zarinpal_callback_url'] = $base.'/api/v1/zarinpal-callback/'.rawurlencode($zarinPath).'?svp_tx=0';
        }
        $aqayePath = (string) ($this->settings->get('aqayepardakht_callback_path_secret', '') ?? '');
        if ($aqayePath !== '') {
            $all['aqayepardakht_callback_url'] = $base.'/api/v1/aqayepardakht-callback/'.rawurlencode($aqayePath).'?svp_tx=0';
        }
        $zibalPath = (string) ($this->settings->get('zibal_callback_path_secret', '') ?? '');
        if ($zibalPath !== '') {
            $all['zibal_callback_url'] = $base.'/api/v1/zibal-callback/'.rawurlencode($zibalPath).'?svp_tx=0';
        }
        $cryptoIpnPath = (string) ($this->settings->get('crypto_ipn_path_secret', '') ?? '');
        if ($cryptoIpnPath !== '') {
            $all['crypto_ipn_callback_url'] = $base.'/api/v1/crypto-ipn/'.rawurlencode($cryptoIpnPath);
        }

        return $all;
    }

    /** @return array<string, mixed> */
    protected function siteSettingsDefaults(): array
    {
        return [
            'landing_enabled' => false,
            'landing_hero_title' => '',
            'landing_hero_subtitle' => '',
            'landing_promo_title' => '',
            'landing_promo_code' => '',
            'portal_subscription_template' => 'classic',
            'portal_theme_brand_name' => '',
            'portal_theme_brand_tagline' => '',
            'portal_modern_brand_name' => '',
            'portal_modern_brand_tagline' => '',
            'portal_datepicker' => 'jalali',
            'portal_theme_primary_light' => '',
            'portal_theme_primary_dark' => '',
            'portal_theme_radius' => '',
            'internal_cron_secret' => '',
            'internal_cron_allowed_ips' => '',
            'live_metrics_poll_seconds' => 15,
            'live_sse_push_seconds' => 5,
        ];
    }

    /** @param  array<string, mixed>  $all */
    protected function redactSecrets(array $all): array
    {
        foreach ($all as $key => $value) {
            if (! is_string($key) || $value === '' || $value === null) {
                continue;
            }
            if ($this->sensitive->shouldEncrypt($key)) {
                $all[$key] = '••••••••';
            }
        }

        return $all;
    }
}
