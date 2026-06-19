<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

/** §11.1 — UNIQUE indexes from svp_schema.sql (v19). */
class DatabaseIndexesParityTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function uniqueIndexesProvider(): array
    {
        return [
            'svp_users_tg' => ['svp_users', 'svp_users_tg'],
            'svp_users_bale' => ['svp_users', 'svp_users_bale'],
            'svp_users_wp' => ['svp_users', 'svp_users_wp'],
            'owner_code' => ['svp_discount_codes', 'owner_code'],
            'reseller_one' => ['svp_reseller_bot_profiles', 'reseller_one'],
            'rule_user' => ['svp_marketing_offers', 'rule_user'],
            'reseller_panel_inbound' => ['svp_reseller_inbound_display_names', 'reseller_panel_inbound'],
            'parent_child_panel' => ['svp_reseller_parent_panel_floors', 'parent_child_panel'],
            'reseller_line' => ['svp_reseller_wholesale_line_assignments', 'reseller_line'],
            'reseller_panel' => ['svp_reseller_panel_prices', 'reseller_panel'],
            'panel_slug' => ['svp_plan_categories', 'panel_slug'],
            'p_i_e' => ['svp_panel_inbound_clients', 'p_i_e'],
            'p_in' => ['svp_panel_inbound_api', 'p_in'],
            'panel_stat' => ['svp_panel_online_daily', 'panel_stat'],
            'svp_texts_key_locale' => ['svp_texts', 'svp_texts_key_locale'],
        ];
    }

    /** @dataProvider uniqueIndexesProvider */
    public function test_unique_index_exists(string $table, string $indexName): void
    {
        $this->assertIndexExists($table, $indexName);
    }

    public function test_billing_reseller_svp_id_key_exists(): void
    {
        $this->assertIndexExists('svp_transactions', 'billing_reseller_svp_id');
    }

    protected function assertIndexExists(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            $this->fail("Missing table {$table}");
        }
        $indexes = DB::select("PRAGMA index_list('{$table}')");
        $names = array_map(fn ($row) => (string) ($row->name ?? ''), $indexes);
        $this->assertContains($indexName, $names, "Index {$indexName} missing on {$table}");
    }
}
