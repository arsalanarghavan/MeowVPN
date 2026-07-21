<?php

namespace App\Modules\Core\Services\Portal;

use App\Models\SvpUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PortalSubscriptionService
{
    public function __construct(
        protected PortalLinkService $portal,
        protected PortalConfigUriCollector $uriCollector,
        protected PortalPageService $pages,
    ) {}

    public function maybeServe(Request $request): ?Response
    {
        if ($request->query('svp_adm') === '1') {
            return $this->pages->maybeServeAdmin($request);
        }
        if ($request->query('svp_p') !== '1') {
            return null;
        }

        $params = $this->portal->signedParamsFromRequest($request);
        $userId = $params['user_id'];
        $serviceId = $params['service_id'];
        $exp = $params['exp'];
        $sig = $params['sig'];

        $user = $this->portal->verifyCustomerSignature($userId, $exp, $sig, $serviceId);
        if (! $user) {
            return response('subscription not available', 404)->header('Content-Type', 'text/plain; charset=utf-8');
        }

        $collected = $this->uriCollector->collect($user, $serviceId);
        $uris = $collected['uris'] ?? [];
        if ($uris === []) {
            return response('subscription not available', 404)->header('Content-Type', 'text/plain; charset=utf-8');
        }

        $userinfo = (string) ($collected['userinfo'] ?? '');

        if ($this->isBrowserRequest($request) && ! $this->forceSubscriptionFormat($request)) {
            return response()->view('portal.subscription', [
                'uris' => $uris,
                'userinfo' => $userinfo,
            ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $body = base64_encode(implode("\n", $uris));

        $response = response($body, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Content-Disposition', 'inline')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Profile-Update-Interval', '24');
        if ($userinfo !== '') {
            $response->header('subscription-userinfo', $userinfo);
        }

        return $response;
    }

    protected function isBrowserRequest(Request $request): bool
    {
        if ($this->forceSubscriptionFormat($request)) {
            return false;
        }
        $accept = (string) $request->header('Accept', '');

        return $accept !== '' && str_contains(strtolower($accept), 'text/html');
    }

    protected function forceSubscriptionFormat(Request $request): bool
    {
        return $request->query('svp_fmt') === 'sub';
    }

}
