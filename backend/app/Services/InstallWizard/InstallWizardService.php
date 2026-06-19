<?php

namespace App\Services\InstallWizard;

use App\Models\DashboardUser;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Hash;

class InstallWizardService
{
    public const KEY_PENDING = 'install_wizard_pending';

    public const KEY_COMPLETED = 'install_wizard_completed';

    public const KEY_TOKEN_HASH = 'install_wizard_token_hash';

    public const KEY_HOSTS_SNAPSHOT = 'install_hosts_snapshot';

    public function __construct(protected SettingsStore $settings) {}

    public function isPending(): bool
    {
        if ($this->isCompleted()) {
            return false;
        }

        return (bool) $this->settings->get(self::KEY_PENDING, false);
    }

    public function isCompleted(): bool
    {
        return (bool) $this->settings->get(self::KEY_COMPLETED, false);
    }

    public function isOpen(): bool
    {
        return $this->isPending() && ! $this->isCompleted();
    }

    public function validateToken(?string $token): bool
    {
        if (! $this->isOpen()) {
            return false;
        }
        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }
        $stored = (string) $this->settings->get(self::KEY_TOKEN_HASH, '');
        if ($stored === '') {
            return false;
        }

        return hash_equals($stored, $this->hashToken($token));
    }

    /** @param  array<string, mixed>  $hostsSnapshot */
    public function init(string $token, array $hostsSnapshot = []): void
    {
        $this->settings->merge([
            self::KEY_PENDING => true,
            self::KEY_COMPLETED => false,
            self::KEY_TOKEN_HASH => $this->hashToken($token),
            self::KEY_HOSTS_SNAPSHOT => $hostsSnapshot,
        ]);
    }

    /** @return array<string, mixed> */
    public function statusPayload(): array
    {
        $dashboardUrl = rtrim((string) $this->settings->get('dashboard_public_url', ''), '/');
        $loginPath = '/dashboard/login/';
        if ($dashboardUrl !== '') {
            $loginUrl = $dashboardUrl.$loginPath;
        } else {
            $loginUrl = url('/dashboard/login');
        }

        return [
            'ok' => true,
            'pending' => $this->isPending(),
            'completed' => $this->isCompleted(),
            'open' => $this->isOpen(),
            'dashboard_login_url' => $loginUrl,
        ];
    }

    public function complete(): string
    {
        $this->settings->merge([
            self::KEY_PENDING => false,
            self::KEY_COMPLETED => true,
            self::KEY_TOKEN_HASH => '',
        ]);

        $dashboardUrl = rtrim((string) $this->settings->get('dashboard_public_url', ''), '/');
        if ($dashboardUrl !== '') {
            return $dashboardUrl.'/dashboard/login/';
        }

        return url('/dashboard/login');
    }

    public function setAdminCredentials(string $username, string $password): void
    {
        $username = trim($username);
        if ($username === '') {
            throw new \InvalidArgumentException('username_required');
        }
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('password_too_short');
        }

        DashboardUser::query()->updateOrCreate(
            ['username' => $username],
            [
                'password' => Hash::make($password),
                'role' => 'admin',
            ]
        );
    }

    /** @return array<string, mixed> */
    public function hostsSnapshot(): array
    {
        $raw = $this->settings->get(self::KEY_HOSTS_SNAPSHOT, []);
        if (! is_array($raw)) {
            return [];
        }

        return $raw;
    }

    /** @param  array<string, mixed>  $snapshot */
    public function updateHostsSnapshot(array $snapshot): void
    {
        $this->settings->set(self::KEY_HOSTS_SNAPSHOT, $snapshot);
    }

    protected function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
