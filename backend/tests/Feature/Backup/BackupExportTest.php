<?php

namespace Tests\Feature\Backup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;
use ZipArchive;

class BackupExportTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_backup_run_creates_zip_with_manifest_and_sql(): void
    {
        $exit = Artisan::call('svp:backup-run');
        $this->assertSame(0, $exit);

        $files = glob(storage_path('app/backups/svp-backup-*.zip')) ?: [];
        $this->assertNotEmpty($files);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($files[0]));
        $manifest = $zip->getFromName('laravel/manifest.json');
        $sql = $zip->getFromName('laravel/database.sql');
        $zip->close();

        $this->assertNotFalse($manifest);
        $this->assertNotFalse($sql);
        $this->assertStringContainsString('svp_users', (string) $sql);
        $decoded = json_decode((string) $manifest, true);
        $this->assertIsArray($decoded);
        $this->assertSame(1, $decoded['version'] ?? null);
    }
}
