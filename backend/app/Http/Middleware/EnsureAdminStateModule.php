<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates admin/state reads when tab requires a disabled module (spec §6 HTTP parity).
 */
class EnsureAdminStateModule
{
    /** @var list<string> */
    private const BOT_TABS = ['bots', 'bot_ui', 'texts', 'reseller_bots'];

    /** @var list<string> */
    private const XUI_TABS = ['xui_panels', 'configs', 'unit_economics', 'reseller_xui_panels'];

    public function handle(Request $request, Closure $next): Response
    {
        $tab = strtolower((string) ($request->query('activeTab', '') ?: $request->query('tab', '')));
        $siteSubtab = strtolower((string) $request->query('site_subtab', ''));
        $modules = svp_modules();

        if ($tab === 'l2tp_servers' && ! $modules->isEnabled('l2tp')) {
            return response()->json(svp_err('module_disabled'), 403);
        }

        if (in_array($tab, self::XUI_TABS, true) && ! $modules->isEnabled('xui_panel')) {
            return response()->json(svp_err('module_disabled'), 403);
        }

        if ($tab === 'backup' && ! $modules->isEnabled('backup')) {
            return response()->json(svp_err('module_disabled'), 403);
        }

        if (in_array($tab, ['marketing_lifecycle', 'broadcast'], true) && ! $modules->isEnabled('marketing')) {
            return response()->json(svp_err('module_disabled'), 403);
        }

        if (in_array($tab, self::BOT_TABS, true)) {
            if (! $modules->isEnabled('telegram') && ! $modules->isEnabled('bale')) {
                return response()->json(svp_err('module_disabled'), 403);
            }
        }

        if ($tab === 'site_settings' && $siteSubtab === 'relay' && ! $modules->isEnabled('relay')) {
            return response()->json(svp_err('module_disabled'), 403);
        }

        if ($tab === 'site_settings' && $siteSubtab === 'finance' && ! $modules->isEnabled('crypto')) {
            return response()->json(svp_err('module_disabled'), 403);
        }

        if ($tab === 'site_settings' && $siteSubtab === 'proxy') {
            if (! $modules->isEnabled('telegram') && ! $modules->isEnabled('bale')) {
                return response()->json(svp_err('module_disabled'), 403);
            }
        }

        return $next($request);
    }
}
