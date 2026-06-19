<?php

namespace Tests\Feature\Migration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** §11 — parity SQL migrates cleanly on MySQL (docker-smoke). */
class ParityMigrationMysqlTest extends TestCase
{
    use RefreshDatabase;

    public function test_parity_schema_migrates_on_mysql(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('MySQL only — run in docker-smoke or with DB_CONNECTION=mysql');
        }

        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();

        $tables = collect(DB::select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->filter(fn (string $name) => str_starts_with($name, 'svp_'))
            ->sort()
            ->values()
            ->all();

        $this->assertCount(43, $tables, 'Expected 43 svp_* tables after migrate:fresh');
        $this->assertTrue(Schema::hasTable('svp_broadcasts'));
        $this->assertTrue(Schema::hasColumn('svp_broadcasts', 'owner_svp_user_id'));
    }
}
