<?php

namespace App\Http\Middleware;

use App\Modules\Core\Services\Portal\PortalLinkService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortalSignatureMiddleware
{
    public function __construct(protected PortalLinkService $portal) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userId = (int) $request->input('svp_u', $request->query('svp_u', 0));
        $exp = (int) $request->input('svp_e', $request->query('svp_e', 0));
        $sig = (string) $request->input('svp_s', $request->query('svp_s', ''));

        $user = $this->portal->verifyAdminSignature($userId, $exp, $sig);
        if (! $user) {
            return response()->json(['success' => false, 'data' => ['message' => 'forbidden']], 403);
        }

        $nonce = (string) $request->input('nonce', '');
        if ($nonce === '' || ! $this->portal->verifyPortalNonce($userId, $nonce)) {
            return response()->json(['success' => false, 'data' => ['message' => 'forbidden']], 403);
        }

        $request->attributes->set('portal_admin_user', $user);

        return $next($request);
    }
}
