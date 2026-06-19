<?php

namespace Tests\Feature\Backup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class BackupListDownloadTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        Artisan::call('svp:backup-run');
    }

    public function test_admin_can_list_and_download_backups(): void
    {
        $list = $this->actingAsAdmin()->getJson('/api/v1/admin/backups');
        $list->assertOk()->assertJsonPath('ok', true);
        $rows = $list->json('rows');
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);

        $filename = (string) ($rows[0]['filename'] ?? '');
        $this->assertNotSame('', $filename);

        $download = $this->actingAsAdmin()->get('/api/v1/admin/backup/download?filename='.urlencode($filename));
        $download->assertOk();
        $this->assertStringContainsString('application/zip', (string) $download->headers->get('content-type'));
    }

    public function test_reseller_cannot_access_backups(): void
    {
        $this->actingAsReseller()->getJson('/api/v1/admin/backups')->assertForbidden();
    }
}
