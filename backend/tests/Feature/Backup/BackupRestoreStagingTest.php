<?php

namespace Tests\Feature\Backup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** §16 P10 — backup download + restore staging (v23). */
class BackupRestoreStagingTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('backup', true);
        Storage::fake('local');
    }

    public function test_backup_list_and_status_endpoints(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/backups')->assertOk();
        $this->actingAsAdmin()->getJson('/api/v1/admin/backup/status')->assertOk();
    }
}
