<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Services\SettingsStore;

class AdminGuard
{
    public function __construct(protected SettingsStore $settings) {}

    /** @return list<int> */
    public function platformAdminIds(string $platform): array
    {
        $canonical = $platform === 'bale' ? 'admin_bale_ids' : 'admin_telegram_ids';
        $legacy = $platform === 'bale' ? 'bale_admin_ids' : 'telegram_admin_ids';
        $ids = array_merge(
            (array) $this->settings->get($canonical, []),
            (array) $this->settings->get($legacy, [])
        );

        return array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));
    }

    public function isPlatformAdmin(string $platform, int $platformUserId): bool
    {
        if ($platformUserId < 1) {
            return false;
        }

        return in_array($platformUserId, $this->platformAdminIds($platform), true);
    }

    public function resolveAdminByPlatformId(string $platform, int $platformUserId): ?SvpUser
    {
        if (! $this->isPlatformAdmin($platform, $platformUserId)) {
            return null;
        }

        $col = $platform === 'bale' ? 'bale_user_id' : 'tg_user_id';

        return SvpUser::query()->where($col, $platformUserId)->first();
    }
}
