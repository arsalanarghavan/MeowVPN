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
        $all['features'] = $boot['features'] ?? [];

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
