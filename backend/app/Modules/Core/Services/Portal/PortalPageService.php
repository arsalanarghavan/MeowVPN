<?php

namespace App\Modules\Core\Services\Portal;

use App\Models\SvpUser;
use App\Services\SettingsStore;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

class PortalPageService
{
    public function __construct(
        protected PortalLinkService $portal,
        protected SettingsStore $settings,
    ) {}

    public function maybeServeAdmin(Request $request): ?Response
    {
        if ($request->query('svp_adm') !== '1') {
            return null;
        }

        $userId = (int) $request->query('svp_u', 0);
        $exp = (int) $request->query('svp_e', 0);
        $sig = (string) $request->query('svp_s', '');

        $admin = $this->portal->verifyAdminSignature($userId, $exp, $sig);
        if (! $admin instanceof SvpUser) {
            return response('forbidden', 403)->header('Content-Type', 'text/plain; charset=utf-8');
        }

        $secret = (string) $this->settings->get('crypto_ipn_path_secret', '');
        $ipnUrl = $secret !== ''
            ? url('/api/v1/crypto-ipn/'.$secret)
            : url('/api/v1/crypto-ipn/…');

        $html = View::make('portal.admin', [
            'admin' => $admin,
            'nonce' => $this->portal->portalNonce($userId),
            'apiUrl' => url('/api/v1/portal/admin'),
            'isReseller' => $admin->role === 'reseller',
            'ipnUrl' => $ipnUrl,
            'assetVersion' => (string) config('svp.portal_asset_version', '1'),
        ])->render();

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
