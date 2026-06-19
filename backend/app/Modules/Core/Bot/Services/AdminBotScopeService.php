<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Modules\Reseller\Services\ResellerScopeService;

class AdminBotScopeService
{
    protected ?int $actingAdminId = null;

    public function __construct(protected ResellerScopeService $scope) {}

    public function setActingAdmin(int $svpUserId): void
    {
        $this->actingAdminId = $svpUserId > 0 ? $svpUserId : null;
    }

    public function actingAdminId(): ?int
    {
        return $this->actingAdminId;
    }

    /** @return array<int, int>|null null = site admin, no scope filter */
    public function botAdminScopeUserIds(SvpUser $admin): ?array
    {
        if ($admin->role !== 'reseller') {
            return null;
        }

        return $this->scope->moderatableUserIds((int) $admin->id);
    }

    public function mayModerateUser(SvpUser $admin, int $targetUserId): bool
    {
        if ($admin->role !== 'reseller') {
            return true;
        }

        return $this->scope->resellerMayModerateUser((int) $admin->id, $targetUserId);
    }
}
