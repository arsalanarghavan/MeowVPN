<?php

namespace App\Services;

use App\Models\DashboardUser;
use App\Models\SvpUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ImpersonationService
{
    public const SESSION_KEY = 'impersonating_reseller_id';

    public function targetId(): int
    {
        return max(0, (int) Session::get(self::SESSION_KEY, 0));
    }

    public function isActive(): bool
    {
        return $this->targetId() > 0;
    }

    public function start(DashboardUser $admin, int $targetSvpUserId): array
    {
        if ($admin->role !== 'admin') {
            return svp_err('forbidden');
        }

        if ($targetSvpUserId < 1) {
            return svp_err('invalid_target');
        }

        $target = SvpUser::query()->find($targetSvpUserId);
        if (! $target || (string) $target->role !== 'reseller') {
            return svp_err('invalid_target');
        }

        Session::put(self::SESSION_KEY, $targetSvpUserId);

        return svp_ok(['target_svp_user_id' => $targetSvpUserId]);
    }

    public function stop(DashboardUser $actor): array
    {
        $tid = $this->targetId();
        if ($tid < 1) {
            return svp_ok();
        }

        if ($actor->role !== 'admin') {
            return svp_err('forbidden');
        }

        Session::forget(self::SESSION_KEY);

        return svp_ok(['target_svp_user_id' => $tid]);
    }

    public function targetLabel(): string
    {
        $tid = $this->targetId();
        if ($tid < 1) {
            return '';
        }

        $row = SvpUser::query()->find($tid);
        if (! $row) {
            return '#'.$tid;
        }

        $parts = array_filter([
            trim((string) ($row->first_name ?? '')),
            trim((string) ($row->last_name ?? '')),
        ]);

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        $username = trim((string) ($row->username ?? ''));

        return $username !== '' ? $username : '#'.$tid;
    }

    public function recordAudit(string $eventType, DashboardUser $actor, int $targetId): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('svp_audit_log') || $targetId < 1) {
            return;
        }

        \Illuminate\Support\Facades\DB::table('svp_audit_log')->insert([
            'domain' => 'security',
            'event_type' => $eventType,
            'actor_kind' => 'admin',
            'actor_svp_user_id' => (int) ($actor->svp_user_id ?? 0),
            'target_type' => 'user',
            'target_id' => $targetId,
            'reseller_scope_id' => $targetId,
            'payload_json' => '{}',
            'ip_hash' => '',
            'created_at' => now(),
        ]);
    }

    public function applyToBoot(array $boot, DashboardUser $user, Request $request): array
    {
        if ($user->role !== 'admin' || ! $this->isActive()) {
            return $boot;
        }

        $tid = $this->targetId();
        $boot['impersonating'] = true;
        $boot['impersonationTargetId'] = $tid;
        $boot['impersonationTargetLabel'] = $this->targetLabel();
        $boot['isReseller'] = true;
        $boot['isAdmin'] = false;
        $boot['activePersona'] = 'reseller';
        $boot['svpUserId'] = $tid;
        $target = DashboardUser::query()->where('svp_user_id', $tid)->where('role', 'reseller')->first();
        if ($target) {
            $boot['resellerAllowedTabs'] = app(DashboardBootBuilder::class)->resellerAllowedTabsMap($target);
        }

        return $boot;
    }
}
