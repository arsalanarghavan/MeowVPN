<?php

namespace App\Services\Mutations;

use App\Models\DashboardUser;

class MutateContext
{
    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public string $op,
        public array $payload,
        public DashboardUser $actor,
        public bool $isReseller,
        public int $actorSvpUserId,
        public int $resellerContextId = 0,
    ) {}

    public function isAdmin(): bool
    {
        return $this->actor->role === 'admin';
    }
}
