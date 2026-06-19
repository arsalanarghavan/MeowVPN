<?php

namespace App\Modules\Backup;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\Backup\Jobs\BackupJob;

class BackupServiceProvider extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'backup';
    }

    protected function bootEnabled(): void
    {
        // Backup REST routes are registered in routes/api.php (admin-only).
    }
}
