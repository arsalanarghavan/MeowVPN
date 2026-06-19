<?php

namespace App\Modules\Core\Http;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\Portal\PortalSubscriptionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortalSubscriptionController extends Controller
{
    public function __invoke(Request $request, PortalSubscriptionService $subscription): Response
    {
        $response = $subscription->maybeServe($request);
        if ($response) {
            return $response;
        }

        return response()->json(svp_ok(['note' => 'portal_html']));
    }
}
