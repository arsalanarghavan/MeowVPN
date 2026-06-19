<?php

namespace App\Services;

class BackupIntervalResolver
{
    public function __construct(protected SettingsStore $settings) {}

    public function minutes(): int
    {
        $fromSettings = $this->settings->get('backup_interval_minutes');
        if ($fromSettings === null) {
            $fromSettings = $this->settings->get('backup.backup_interval_minutes');
        }
        if ($fromSettings === null) {
            $fromSettings = config('svp.backup_interval_minutes', 60);
        }

        return max(5, min(1440, (int) $fromSettings));
    }
}
