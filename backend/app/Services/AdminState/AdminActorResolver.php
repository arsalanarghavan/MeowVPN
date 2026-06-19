<?php

namespace App\Services\AdminState;

use App\Modules\Reseller\Services\ResellerScopeService;
use App\Services\ImpersonationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class AdminActorResolver
{
    public function __construct(
        protected ResellerScopeService $scope,
        protected ImpersonationService $impersonation,
    ) {}

    public function applyScope(AdminStateContext $ctx): void
    {
        if ($ctx->isAdmin && $this->impersonation->isActive()) {
            $targetId = $this->impersonation->targetId();
            $ctx->resellerContextId = $targetId;
            $ctx->moderatableUserIds = $this->scope->moderatableUserIds($targetId);
            $ctx->allowedPanelIds = $this->scope->allowedPanelIdsFor($targetId);

            return;
        }

        if ($ctx->isReseller && $ctx->actorSvpUserId > 0) {
            if ($ctx->activeTab !== '' && ! $this->scope->resellerMayRequestAdminTab($ctx->actor, $ctx->activeTab)) {
                $this->abort(403, 'forbidden_tab');
            }
            $ctx->resellerContextId = $ctx->actorSvpUserId;
            $ctx->moderatableUserIds = $this->scope->moderatableUserIds($ctx->actorSvpUserId);
            $ctx->allowedPanelIds = $this->scope->allowedPanelIdsFor($ctx->actorSvpUserId);

            return;
        }

        if ($ctx->resellerContextId > 0) {
            $validated = $this->scope->validateResellerContextId($ctx->resellerContextId);
            if ($validated === null) {
                $this->abort(400, 'invalid_reseller_context');
            }
            $ctx->resellerContextId = $validated;
            $ctx->moderatableUserIds = $this->scope->moderatableUserIds($validated);
        }
    }

    protected function abort(int $status, string $message): void
    {
        throw new HttpResponseException(new JsonResponse(['ok' => false, 'message' => $message], $status));
    }
}
