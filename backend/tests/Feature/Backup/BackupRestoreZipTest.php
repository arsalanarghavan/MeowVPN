<?php

namespace Tests\Feature\Backup;

use App\Modules\Backup\Services\BackupExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class BackupRestoreZipTest extends TestCase
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

    public function test_restore_rejects_missing_backup_file(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/backup/restore', [
            'filename' => 'nonexistent-backup.zip',
            'confirm' => true,
        ])->assertStatus(400);
    }

    public function test_restore_rejects_invalid_zip_fixture(): void
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $name = 'simplevpbot-backup-v14test.zip';
        File::put($dir.'/'.$name, 'PK'.str_repeat("\0", 20));

        $this->actingAsAdmin()->postJson('/api/v1/admin/backup/restore', [
            'filename' => $name,
            'confirm' => true,
        ])->assertStatus(400)->assertJsonPath('ok', false);

        File::delete($dir.'/'.$name);
    }

    public function test_restore_accepts_valid_export_zip(): void
    {
        $zipPath = app(BackupExportService::class)->buildZip('v16-restore-test');
        $filename = basename($zipPath);

        $this->actingAsAdmin()->postJson('/api/v1/admin/backup/restore', [
            'filename' => $filename,
            'confirm' => true,
        ])->assertOk()->assertJsonPath('ok', true);

        File::delete($zipPath);
    }
}
