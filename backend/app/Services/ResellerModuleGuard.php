<?php

namespace App\Services;

class ResellerModuleGuard
{
    public function isResellerCommerceAllowed(): bool
    {
        return svp_modules()->isEnabled('reseller');
    }

    public function normalizeOwnerId(int $ownerId): int
    {
        return $this->isResellerCommerceAllowed() ? max(0, $ownerId) : 0;
    }
}
