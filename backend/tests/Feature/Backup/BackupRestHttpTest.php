<?php

namespace Tests\Feature\Backup;

use App\Modules\Backup\Jobs\ManualBackupJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** §14 H — backup REST HTTP endpoints (v13). */
class BackupRestHttpTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('backup', true);
    }

    public function test_backup_index_returns_ok(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/backups')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['rows', 'panels']);
    }

    public function test_backup_status_returns_json(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/backup/status')
            ->assertOk()
            ->assertJsonStructure(['status']);
    }

    public function test_backup_run_dispatches_job(): void
    {
        Bus::fake([ManualBackupJob::class]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/backup/run')
            ->assertOk()
            ->assertJsonPath('ok', true);

        Bus::assertDispatched(ManualBackupJob::class);
    }

    public function test_backup_download_requires_filename(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/backup/download')
            ->assertStatus(400)
            ->assertJsonPath('message', 'missing_filename');
    }

    public function test_backup_restore_requires_confirm(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/backup/restore', [])
            ->assertStatus(400);
    }
}
