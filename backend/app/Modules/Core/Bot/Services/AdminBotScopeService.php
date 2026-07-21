<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Reseller\Services\ResellerPermissionGateService;
use App\Modules\Reseller\Services\ResellerScopeService;

/**
 * WP SimpleVPBot_Bot_Reseller_Scope parity for bot admin lists / panel ACL.
 */
class AdminBotScopeService
{
    protected ?int $actingAdminId = null;

    protected ?int $requestResellerId = null;

    public function __construct(
        protected ResellerScopeService $scope,
        protected ResellerPermissionGateService $permissionGate,
    ) {}

    public function bindContext(BotContext $ctx): void
    {
        $this->requestResellerId = $ctx->isResellerBot() ? max(0, $ctx->resellerSvpUserId) : 0;
    }

    /** Alias used by CallbackHandler / UpdateRouter. */
    public function bindBotContext(BotContext $ctx): void
    {
        $this->bindContext($ctx);
    }

    public function setActingAdmin(int $svpUserId): void
    {
        $this->actingAdminId = $svpUserId > 0 ? $svpUserId : null;
    }

    public function actingAdminId(): ?int
    {
        return $this->actingAdminId;
    }

    public function resolveScopeResellerId(?SvpUser $admin = null): int
    {
        if (($this->requestResellerId ?? 0) > 0) {
            return (int) $this->requestResellerId;
        }
        if ($admin && $admin->role === 'reseller') {
            return (int) $admin->id;
        }
        if ($this->actingAdminId) {
            $u = SvpUser::query()->find($this->actingAdminId);
            if ($u) {
                return max(0, $this->permissionGate->permissionActorId($u));
            }
        }

        return 0;
    }

    public function isScopedBotAdminContext(?SvpUser $admin = null): bool
    {
        return $this->resolveScopeResellerId($admin) > 0;
    }

    public function resellerBlocksGlobalSettings(): bool
    {
        return ($this->requestResellerId ?? 0) > 0;
    }

    /** @return array<int, int>|null null = site admin, no scope filter */
    public function botAdminScopeUserIds(SvpUser $admin): ?array
    {
        $rid = $this->resolveScopeResellerId($admin);
        if ($rid < 1) {
            return null;
        }

        return $this->scope->moderatableUserIds($rid);
    }

    public function mayModerateUser(SvpUser $admin, int $targetUserId): bool
    {
        $rid = $this->resolveScopeResellerId($admin);
        if ($rid < 1) {
            return true;
        }

        return $this->scope->resellerMayModerateUser($rid, $targetUserId);
    }

    /** @return array<int, int>|null */
    public function botAdminAllowedPanelIds(?SvpUser $admin = null): ?array
    {
        $rid = $this->resolveScopeResellerId($admin);
        if ($rid < 1) {
            return null;
        }

        return $this->scope->allowedPanelIdsFor($rid);
    }

    public function panelAllowedInContext(int $panelId, ?SvpUser $admin = null): bool
    {
        if ($panelId < 1) {
            return false;
        }
        $allowed = $this->botAdminAllowedPanelIds($admin);
        if ($allowed === null) {
            return true;
        }

        return in_array($panelId, $allowed, true);
    }
}
