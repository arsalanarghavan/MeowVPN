<?php

namespace App\Modules\XrayCore\Services;

use App\Models\SvpPlan;
use App\Modules\XrayCore\DTO\ProvisionResult;
use App\Modules\XrayCore\DTO\TrafficStats;

interface PanelDriverInterface
{
    public function driverType(): string;

    public function provisionFromPlan(int $userId, SvpPlan $plan, ?int $volumeGb, ?string $platform): ProvisionResult;

    /** @param  array<string, mixed>|object  $svc */
    public function addClientFromServiceRow(array|object $svc): array;

    /** @param  object  $svc */
    public function buildSubscriptionUris(object $svc): array;

    /** @param  object  $svc */
    public function syncTraffic(object $svc): ?TrafficStats;

    /** @param  object  $svc */
    public function disableClient(object $svc): bool;

    /** @param  object  $svc */
    public function deleteClient(object $svc): bool;

    /** @param  object  $svc */
    public function renewClient(object $svc, int $days, int $gb): bool;
}
