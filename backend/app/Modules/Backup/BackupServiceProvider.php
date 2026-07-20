<?php

namespace App\Modules\Backup;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\Backup\Mutations\BackupMutations;

class BackupServiceProvider extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'backup';
    }

    public function mutationHandlers(): array
    {
        return (new \ReflectionClass(BackupMutations::class))->newInstanceWithoutConstructor()->handlers();
    }

    protected function bootEnabled(): void
    {
        // Backup REST routes are registered in routes/api.php (admin-only).
    }
}
