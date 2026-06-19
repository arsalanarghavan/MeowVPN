<?php

namespace App\Services\Commerce;

use App\Models\SvpService;
use App\Models\SvpUser;
use Illuminate\Support\Facades\DB;

class ServiceTransferService
{
    /** @return array<string, mixed> */
    public function transfer(int $serviceId, string $target): array
    {
        if ($serviceId < 1 || $target === '') {
            return svp_err('invalid');
        }

        $svc = SvpService::query()->find($serviceId);
        if (! $svc) {
            return svp_err('not_found');
        }

        $user = $this->resolveTarget($target);
        if (! $user) {
            return svp_err('target_not_found');
        }

        $fromUserId = (int) $svc->user_id;
        $svc->user_id = $user->id;
        $svc->save();

        if (\Illuminate\Support\Facades\Schema::hasTable('svp_service_transfers')) {
            DB::table('svp_service_transfers')->insert([
                'service_id' => $serviceId,
                'from_user_id' => $fromUserId,
                'to_user_id' => $user->id,
                'created_at' => now(),
            ]);
        }

        return svp_ok(['service_id' => $serviceId, 'user_id' => $user->id]);
    }

    protected function resolveTarget(string $target): ?SvpUser
    {
        if (preg_match('/^\d+$/', $target)) {
            return SvpUser::query()->find((int) $target);
        }

        $u = ltrim($target, '@');

        return SvpUser::query()
            ->where('username', $u)
            ->orWhere('phone', $u)
            ->first();
    }
}
