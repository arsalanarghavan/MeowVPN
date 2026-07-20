<?php

namespace App\Modules\PasarGuard\Services;

use App\Modules\XuiPanel\Services\XuiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves XuiClient or PasarGuardClient from panel_provider.
 */
class PanelClientFactory
{
    public function __construct(
        protected XuiClient $xui,
        protected PasarGuardClient $pasarGuard,
    ) {}

    public function providerForPanelId(int $panelId): string
    {
        if ($panelId < 1 || ! $this->pasarGuardEnabled()) {
            return 'xui';
        }

        return PasarGuardPanelContext::providerForId($panelId);
    }

    /** @param  array<string, mixed>|object|null  $panel */
    public function providerForPanel(array|object|null $panel): string
    {
        if (! $this->pasarGuardEnabled()) {
            return 'xui';
        }
        if ($panel === null) {
            return 'xui';
        }
        $row = is_array($panel) ? $panel : (array) $panel;
        if (PasarGuardPanelContext::isPasarGuard($row)) {
            return PasarGuardPanelContext::PROVIDER;
        }
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0) {
            return $this->providerForPanelId($id);
        }

        return 'xui';
    }

    public function isPasarGuardPanel(int $panelId): bool
    {
        return $this->providerForPanelId($panelId) === PasarGuardPanelContext::PROVIDER;
    }

    /**
     * @param  array<string, mixed>  $panel
     * @return XuiClient|PasarGuardClient
     */
    public function forPanelId(int $panelId, array $panel = []): XuiClient|PasarGuardClient
    {
        if ($panel !== [] && $this->providerForPanel($panel) === PasarGuardPanelContext::PROVIDER) {
            return $this->pasarGuard;
        }
        if ($this->isPasarGuardPanel($panelId)) {
            return $this->pasarGuard;
        }

        return $this->xui;
    }

    /**
     * @param  array<string, mixed>|object  $panel
     * @return XuiClient|PasarGuardClient
     */
    public function forPanel(array|object $panel): XuiClient|PasarGuardClient
    {
        $row = is_array($panel) ? $panel : (array) $panel;
        $id = (int) ($row['id'] ?? 0);

        return $this->forPanelId($id, $row);
    }

    /**
     * Run callable with the correct client bound to the panel.
     *
     * @param  array<string, mixed>  $panel
     */
    public function runWithPanel(int $panelId, callable $fn, array $panel = []): mixed
    {
        $client = $this->forPanelId($panelId, $panel);

        return $client->runWithPanel($panelId, function () use ($fn, $client) {
            return $fn($client);
        }, $panel);
    }

    /** @return array<string, mixed>|null */
    public function loadPanelRow(int $panelId): ?array
    {
        if ($panelId < 1 || ! Schema::hasTable('svp_panels')) {
            return null;
        }
        $row = DB::table('svp_panels')->where('id', $panelId)->first();

        return $row ? (array) $row : null;
    }

    protected function pasarGuardEnabled(): bool
    {
        return function_exists('svp_modules') && svp_modules()->isEnabled('pasarguard');
    }
}
