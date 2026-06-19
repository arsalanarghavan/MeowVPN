<?php

namespace Tests\Feature\Backup;

use App\Modules\Backup\Services\BackupExportService;
use App\Modules\Backup\Services\BackupRestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_merge_restore_inserts_missing_user(): void
    {
        $this->assertDatabaseMissing('svp_users', ['tg_user_id' => 888888]);

        $stats = app(BackupRestoreService::class)->restoreMerge([
            'svp_users' => [[
                'id' => 9999,
                'username' => 'imported',
                'tg_user_id' => 888888,
                'role' => 'user',
                'status' => 'approved',
                'created_at' => now()->toDateTimeString(),
            ]],
        ]);

        $this->assertSame(1, $stats['users_inserted'] ?? 0);
        $this->assertDatabaseHas('svp_users', ['tg_user_id' => 888888]);
    }

    public function test_restore_from_zip_file(): void
    {
        $path = app(BackupExportService::class)->buildZip('restore-test');
        $res = app(BackupRestoreService::class)->restoreFromZip($path, false);
        $this->assertTrue($res['ok'] ?? false);
    }
}
