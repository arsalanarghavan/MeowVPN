<?php

namespace App\Services;

use App\Models\SvpService;
use App\Models\SvpUser;
use App\Modules\Core\Services\Portal\PortalLinkService;
use App\Services\AdminState\AdminRowFormatter;

class UserPortalStateBuilder
{
    public function __construct(protected PortalLinkService $portal) {}

    /** @return array<string, mixed> */
    public function build(int $svpUserId): array
    {
        if ($svpUserId < 1) {
            return ['ok' => false, 'message' => 'no_linked_user'];
        }

        $user = SvpUser::query()->find($svpUserId);
        if (! $user || $user->status !== 'approved') {
            return ['ok' => false, 'message' => 'not_found'];
        }

        $link = $this->portal->buildPortalLink($svpUserId);
        $portalBase = url('/info').'?'.http_build_query([
            'svp_p' => '1',
            'svp_u' => $link['svp_u'],
            'svp_e' => $link['svp_e'],
            'svp_s' => $link['svp_s'],
        ]);

        $services = SvpService::query()
            ->where('user_id', $svpUserId)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get()
            ->map(function ($svc) use ($svpUserId) {
                $row = AdminRowFormatter::rowArray($svc);
                $svcLink = $this->portal->buildPortalLink($svpUserId, PortalLinkService::CUSTOMER_TTL, (int) $svc->id);
                $row['portal_url'] = url('/info').'?'.http_build_query([
                    'svp_p' => '1',
                    'svp_u' => $svcLink['svp_u'],
                    'svp_sid' => (int) $svc->id,
                    'svp_e' => $svcLink['svp_e'],
                    'svp_s' => $svcLink['svp_s'],
                ]);
                $tt = (int) ($row['total_traffic'] ?? 0);
                $ut = (int) ($row['used_traffic'] ?? 0);
                $row['quota_gb'] = $tt > 0 ? round($tt / (1024 * 1024 * 1024), 4) : 0.0;
                $row['used_gb'] = $ut > 0 ? round($ut / (1024 * 1024 * 1024), 4) : 0.0;

                return $row;
            })
            ->all();

        return [
            'ok' => true,
            'user' => AdminRowFormatter::sanitizeUserRow(AdminRowFormatter::rowArray($user), false),
            'portal_url' => $portalBase,
            'services' => $services,
        ];
    }
}
