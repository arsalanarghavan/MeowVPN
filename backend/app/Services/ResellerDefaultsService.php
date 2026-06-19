<?php

namespace App\Services;

class ResellerDefaultsService
{
    public function __construct(protected SettingsStore $settings) {}

    /** @return array<string, bool> */
    public function permissions(): array
    {
        $raw = $this->settings->get('resellers_defaults.permissions');
        if ($raw === null) {
            $raw = $this->settings->get('resellers_defaults');
        }
        if (! is_array($raw)) {
            return $this->fallbackPermissions();
        }
        $out = [];
        foreach ($this->permissionKeys() as $key) {
            if (array_key_exists($key, $raw)) {
                $out[$key] = (bool) $raw[$key];
            }
        }

        return $out !== [] ? array_merge($this->fallbackPermissions(), $out) : $this->fallbackPermissions();
    }

    /** @return array<string, mixed> */
    public function forAdminState(): array
    {
        $stored = $this->settings->get('resellers_defaults');
        if (is_array($stored)) {
            return $stored;
        }

        return ['permissions' => $this->fallbackPermissions()];
    }

    /** @return list<string> */
    public function permissionKeys(): array
    {
        return [
            'users.manage',
            'plans.manage',
            'receipts.review',
            'services.manage',
            'broadcast.send',
            'users.bulk',
            'marketing.lifecycle',
        ];
    }

    /** @return array<string, bool> */
    protected function fallbackPermissions(): array
    {
        return [
            'users.manage' => true,
            'plans.manage' => true,
            'receipts.review' => true,
            'services.manage' => true,
            'broadcast.send' => true,
            'users.bulk' => true,
            'marketing.lifecycle' => true,
        ];
    }
}
