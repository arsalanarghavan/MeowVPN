<?php

namespace App\Modules\PasarGuard\Mutations;

use App\Modules\PasarGuard\Services\PanelClientFactory;
use App\Modules\PasarGuard\Services\PasarGuardClient;
use App\Modules\PasarGuard\Services\PasarGuardPanelContext;
use App\Modules\XuiPanel\Mutations\XuiPanelMutations;
use App\Services\PanelSecretCipher;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PasarGuardMutations
{
    public function __construct(
        protected PanelClientFactory $factory,
        protected PasarGuardClient $pg,
        protected XuiPanelMutations $xuiPanels,
    ) {}

    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            // Override XUI handlers so panel CRUD/test honor panel_provider.
            'panel_xp' => [self::class, 'panelXp'],
            'panel_test' => [self::class, 'panelTest'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function panelXp(array $payload, ?Authenticatable $actor): array
    {
        $action = strtolower(trim((string) ($payload['xp_action'] ?? $payload['action'] ?? '')));
        if (in_array($action, ['toggle', 'delete'], true)) {
            return $this->xuiPanels->panelXp($payload, $actor);
        }

        $id = (int) ($payload['id'] ?? $payload['panel_id'] ?? $payload['xp_id'] ?? 0);
        $provider = strtolower(trim((string) ($payload['panel_provider'] ?? $payload['xp_panel_provider'] ?? 'xui')));
        $isPg = $provider === PasarGuardPanelContext::PROVIDER;

        // Normalize WP xp_* aliases before field collect.
        foreach ([
            'xp_label' => 'label',
            'xp_panel_url' => 'panel_url',
            'xp_panel_username' => 'panel_username',
            'xp_panel_password' => 'panel_password',
            'xp_panel_api_base' => 'panel_api_base',
            'xp_panel_login_secret' => 'panel_login_secret',
            'xp_panel_api_token' => 'panel_api_token',
            'xp_panel_api_flavor' => 'panel_api_flavor',
            'xp_subscription_public_base' => 'subscription_public_base',
            'xp_sort_order' => 'sort_order',
            'xp_active' => 'active',
            'xp_panel_provider' => 'panel_provider',
            'xp_panel_template_required' => 'panel_template_required',
            'xp_buy_category_intro_fa' => 'buy_category_intro_fa',
            'xp_buy_category_intro_en' => 'buy_category_intro_en',
        ] as $from => $to) {
            if (array_key_exists($from, $payload) && ! array_key_exists($to, $payload)) {
                $payload[$to] = $payload[$from];
            }
        }

        $fields = [
            'label', 'panel_url', 'panel_username', 'panel_password', 'panel_api_base',
            'panel_login_secret', 'panel_api_token', 'panel_api_flavor', 'subscription_public_base',
            'sort_order', 'active',
        ];
        if (Schema::hasColumn('svp_panels', 'panel_provider')) {
            $fields[] = 'panel_provider';
        }
        if (Schema::hasColumn('svp_panels', 'panel_template_required')) {
            $fields[] = 'panel_template_required';
        }

        $data = collect($payload)->only($fields)->filter(fn ($v) => $v !== null)->all();

        if (array_key_exists('panel_provider', $payload) || array_key_exists('xp_panel_provider', $payload)) {
            $data['panel_provider'] = $isPg ? PasarGuardPanelContext::PROVIDER : 'xui';
        }
        if (array_key_exists('panel_template_required', $payload) || array_key_exists('xp_panel_template_required', $payload)) {
            $data['panel_template_required'] = ! empty($payload['panel_template_required'] ?? $payload['xp_panel_template_required'] ?? false) ? 1 : 0;
        }
        if ($isPg) {
            $data['panel_api_flavor'] = PasarGuardPanelContext::FLAVOR;
            if (empty($data['panel_api_base']) || ($data['panel_api_base'] ?? '') === 'panel/api') {
                $data['panel_api_base'] = 'api';
            }
        }
        foreach (['buy_category_intro_fa', 'buy_category_intro_en'] as $col) {
            if (array_key_exists($col, $payload) && Schema::hasColumn('svp_panels', $col)) {
                $data[$col] = $payload[$col];
            }
        }

        $data = $this->encryptPanelSecrets($data);

        if ($id > 0) {
            if (array_key_exists('panel_password', $data) && trim((string) ($payload['panel_password'] ?? '')) === '') {
                unset($data['panel_password']);
            }
            if (array_key_exists('panel_api_token', $data) && trim((string) ($payload['panel_api_token'] ?? '')) === '') {
                unset($data['panel_api_token']);
            }
            DB::table('svp_panels')->where('id', $id)->update($data);

            return svp_ok(['panel_id' => $id, 'code' => 'updated']);
        }
        $newId = DB::table('svp_panels')->insertGetId(array_merge($data, ['created_at' => now()]));

        return svp_ok(['panel_id' => $newId, 'code' => 'added']);
    }

    /** @param  array<string, mixed>  $payload */
    public function panelTest(array $payload, ?Authenticatable $actor): array
    {
        $panel = DB::table('svp_panels')->where('id', (int) ($payload['panel_id'] ?? 0))->first();
        if (! $panel) {
            return svp_err('not_found');
        }
        $client = $this->factory->forPanel($panel);

        return svp_ok($client->testConnection((array) $panel));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function encryptPanelSecrets(array $data): array
    {
        $cipher = app(PanelSecretCipher::class);
        foreach (['panel_password', 'panel_login_secret', 'panel_api_token'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $plain = trim((string) $data[$key]);
            if ($plain === '') {
                continue;
            }
            $data[$key] = $cipher->encrypt($plain);
        }

        return $data;
    }
}
