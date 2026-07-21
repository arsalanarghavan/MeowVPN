<?php

namespace App\Modules\Core\Http;

use App\Http\Controllers\Controller;
use App\Models\SvpUser;
use App\Modules\Core\Services\Portal\PortalLinkService;
use App\Modules\Core\Services\Portal\PortalSubscriptionService;
use App\Modules\Core\Services\Portal\PortalThemePayloadService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortalSubscriptionController extends Controller
{
    public function __invoke(
        Request $request,
        PortalSubscriptionService $subscription,
        PortalLinkService $portal,
        PortalThemePayloadService $themes,
    ): Response {
        $response = $subscription->maybeServe($request);
        if ($response) {
            return $response;
        }

        $ctx = $portal->resolveFromRequest($request);
        if (! empty($ctx['ok'])) {
            $user = SvpUser::query()->find((int) ($ctx['user_id'] ?? 0));
            if ($user instanceof SvpUser) {
                $payload = $themes->build($user, (int) ($ctx['service_id'] ?? 0), $request);

                return response()->json($payload)
                    ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
            }
        }

        return response()->json(svp_ok(['note' => 'portal_html']));
    }
}
