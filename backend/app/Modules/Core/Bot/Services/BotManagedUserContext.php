<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;

class BotManagedUserContext
{
    public function set(SvpUser $admin, int $managedUserId, ?int $managedServiceId = null): void
    {
        app(BotStateService::class)->mergeData($admin, [
            'managed_user_id' => $managedUserId,
            'managed_service_id' => $managedServiceId,
        ]);
    }

    public function managedUserId(SvpUser $admin): int
    {
        $data = app(BotStateService::class)->data($admin);

        return (int) ($data['managed_user_id'] ?? 0);
    }

    public function managedServiceId(SvpUser $admin): int
    {
        $data = app(BotStateService::class)->data($admin);

        return (int) ($data['managed_service_id'] ?? 0);
    }

    public function isManagingOtherUser(SvpUser $admin, int $serviceOwnerId): bool
    {
        $managed = $this->managedUserId($admin);

        return $managed > 0 && $managed === $serviceOwnerId && (int) $admin->id !== $serviceOwnerId;
    }

    public function clear(SvpUser $admin): void
    {
        $state = app(BotStateService::class);
        $data = $state->data($admin);
        unset($data['managed_user_id'], $data['managed_service_id']);
        $current = $state->get($admin);
        if ($current !== '') {
            $state->set($admin, $current, $data);
        }
    }
}
