<?php

namespace Tests\Unit\Backup;

use App\Modules\Backup\Services\BackupMergeRestore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class BackupMergeRestoreTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_restore_from_dump_delegates_to_merge(): void
    {
        $this->assertDatabaseMissing('svp_users', ['tg_user_id' => 777777]);

        $stats = app(BackupMergeRestore::class)->restoreFromDump([
            'svp_users' => [[
                'id' => 8888,
                'username' => 'merged',
                'tg_user_id' => 777777,
                'role' => 'user',
                'status' => 'approved',
                'created_at' => now()->toDateTimeString(),
            ]],
        ]);

        $this->assertSame(1, $stats['users_inserted'] ?? 0);
        $this->assertDatabaseHas('svp_users', ['tg_user_id' => 777777]);
    }

    public function test_merge_skips_existing_row_by_primary_key(): void
    {
        $existingId = (int) DB::table('svp_texts')->insertGetId([
            'key_name' => 'live_key',
            'locale' => 'fa',
            'value' => 'live body',
            'updated_at' => now(),
        ]);

        $stats = app(BackupMergeRestore::class)->restoreMerge([
            'svp_texts' => [[
                'id' => $existingId,
                'key_name' => 'backup_key',
                'locale' => 'fa',
                'value' => 'backup body',
                'updated_at' => now()->toDateTimeString(),
            ]],
        ]);

        $this->assertSame(1, $stats['rows_skipped']['svp_texts'] ?? 0);
        $this->assertDatabaseHas('svp_texts', ['id' => $existingId, 'key_name' => 'live_key']);
    }
}
