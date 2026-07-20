<?php

namespace App\Services\AdminState\Loaders;

use App\Modules\PasarGuard\Services\PasarGuardClient;
use App\Modules\PasarGuard\Services\PasarGuardPanelContext;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loads PasarGuard user templates for plan picker (WP route_panel_templates parity).
 */
class PanelTemplatesLoader extends AbstractLoader
{
    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        if (! function_exists('svp_modules') || ! svp_modules()->isEnabled('pasarguard')) {
            return false;
        }
        if (! class_exists(PasarGuardClient::class)) {
            return false;
        }
        if (! Schema::hasTable('svp_panels') || ! Schema::hasColumn('svp_panels', 'panel_provider')) {
            return false;
        }

        return in_array($ctx->activeTab, [
            'plans', 'plan_cats', 'xui_panels', 'reseller_panels', 'reseller_xui_panels', 'site_settings',
        ], true)
            || (int) $ctx->request->query('panelTemplatesPanelId', 0) > 0
            || (string) $ctx->request->query('includePanelTemplates') === '1';
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        /** @var PasarGuardClient $pg */
        $pg = app(PasarGuardClient::class);
        $specific = (int) $ctx->request->query('panelTemplatesPanelId', 0);
        $q = DB::table('svp_panels')->where('panel_provider', PasarGuardPanelContext::PROVIDER);
        if ($specific > 0) {
            $q->where('id', $specific);
        } elseif ($ctx->allowedPanelIds !== []) {
            $q->whereIn('id', $ctx->allowedPanelIds);
        } elseif ($ctx->isReseller) {
            $result->merge([
                'panelTemplates' => [],
                'panelTemplatesMeta' => ['provider' => PasarGuardPanelContext::PROVIDER, 'panel_ids' => []],
            ]);

            return;
        }
        $panels = $q->orderBy('sort_order')->orderBy('id')->limit($specific > 0 ? 1 : 5)->get();

        $byPanel = [];
        foreach ($panels as $panel) {
            $pid = (int) $panel->id;
            try {
                $list = $pg->runWithPanel($pid, function () use ($pg) {
                    if (! $pg->loginWithRetries(2, 100000)) {
                        return null;
                    }

                    return $pg->templatesListSimple();
                }, (array) $panel);
            } catch (\Throwable) {
                $list = null;
            }
            $byPanel[(string) $pid] = is_array($list) ? array_values($list) : [];
        }

        $result->merge([
            'panelTemplates' => $byPanel,
            'panelTemplatesMeta' => [
                'provider' => PasarGuardPanelContext::PROVIDER,
                'panel_ids' => array_map('intval', array_keys($byPanel)),
            ],
        ]);
    }
}
