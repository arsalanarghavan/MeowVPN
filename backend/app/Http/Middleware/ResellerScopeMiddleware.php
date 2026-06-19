<?php

namespace App\Http\Middleware;

use App\Models\DashboardUser;
use App\Modules\Reseller\Services\ResellerScopeService;
use App\Services\ImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResellerScopeMiddleware
{
    public function __construct(protected ResellerScopeService $scope) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $impersonation = app(ImpersonationService::class);
        $actorId = 0;
        if ($user instanceof DashboardUser) {
            if ($user->role === 'reseller' && (int) ($user->svp_user_id ?? 0) > 0) {
                $actorId = (int) $user->svp_user_id;
            } elseif ($user->role === 'admin' && $impersonation->isActive()) {
                $actorId = $impersonation->targetId();
            }
        }
        if ($actorId > 0) {
            $request->attributes->set('reseller_scope_ids', $this->scope->downlineUserIds($actorId));
            $request->attributes->set('reseller_actor_id', $actorId);
        }

        return $next($request);
    }
}
