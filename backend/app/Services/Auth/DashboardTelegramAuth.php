<?php

namespace App\Services\Auth;

use App\Models\DashboardUser;
use App\Models\SvpUser;
use App\Modules\Core\Bot\Services\AdminGuard;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;

class DashboardTelegramAuth
{
    public function __construct(
        protected SettingsStore $settings,
        protected AdminGuard $adminGuard,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function verifyWidgetPayload(array $data): bool
    {
        if (empty($data['hash']) || empty($data['auth_date']) || empty($data['id'])) {
            return false;
        }
        $token = trim((string) $this->settings->get('telegram_token', ''));
        if ($token === '') {
            return false;
        }
        $hash = (string) $data['hash'];
        unset($data['hash']);
        $lines = [];
        foreach ($data as $key => $val) {
            if (is_scalar($val) || $val === null) {
                $lines[] = $key.'='.(string) $val;
            }
        }
        sort($lines);
        $secret = hash('sha256', $token, true);
        $check = hash_hmac('sha256', implode("\n", $lines), $secret);
        if (! hash_equals($check, $hash)) {
            return false;
        }
        $age = time() - (int) $data['auth_date'];

        return $age >= 0 && $age <= 86400;
    }

    public function resolveDashboardUserForTelegramId(int $telegramId): ?DashboardUser
    {
        return $this->resolveDashboardUserForPlatform('telegram', $telegramId);
    }

    public function resolveDashboardUserForPlatform(string $platform, int $platformUserId): ?DashboardUser
    {
        if ($platformUserId < 1) {
            return null;
        }
        $plat = in_array($platform, ['telegram', 'bale'], true) ? $platform : 'telegram';
        $col = $plat === 'bale' ? 'bale_user_id' : 'tg_user_id';
        $svp = SvpUser::query()->where($col, $platformUserId)->first();
        if ($svp) {
            $user = DashboardUser::query()->where('svp_user_id', (int) $svp->id)->first();
            if ($user) {
                return $user;
            }
        }
        if ($this->adminGuard->isPlatformAdmin($plat, $platformUserId)) {
            return DashboardUser::query()->where('role', 'admin')->orderBy('id')->first();
        }

        return null;
    }

    public function telegramLoginEnabled(): bool
    {
        $token = trim((string) $this->settings->get('telegram_token', ''));
        $user = trim((string) $this->settings->get('telegram_bot_username', ''));

        return $token !== '' && $user !== '';
    }

    public function baleLoginEnabled(): bool
    {
        $token = trim((string) $this->settings->get('bale_token', ''));
        $user = trim((string) $this->settings->get('bale_bot_username', ''));

        return $token !== '' && $user !== '';
    }

    public function baleBotDeepLink(): string
    {
        $user = ltrim(trim((string) $this->settings->get('bale_bot_username', '')), '@');
        if ($user === '') {
            return '';
        }

        return 'https://ble.ir/'.$user;
    }

    /** @return array<string, mixed> */
    public function loginPagePayload(): array
    {
        return [
            'ok' => true,
            'telegram_login_enabled' => $this->telegramLoginEnabled(),
            'bale_login_enabled' => $this->baleLoginEnabled(),
            'telegram_bot_username' => ltrim((string) $this->settings->get('telegram_bot_username', ''), '@'),
            'bale_bot_deep_link' => $this->baleBotDeepLink(),
            'login_url' => url('/api/v1/dashboard/login'),
            'telegram_login_url' => url('/api/v1/dashboard/login/telegram'),
            'magic_issue_url' => url('/api/v1/dashboard/login/magic/issue'),
            'magic_consume_url' => url('/api/v1/dashboard/login/magic'),
        ];
    }
}
