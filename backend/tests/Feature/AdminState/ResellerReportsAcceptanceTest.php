<?php

namespace Tests\Feature\AdminState;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class ResellerReportsAcceptanceTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();

        if (! Schema::hasTable('svp_reseller_wholesale_accruals')) {
            Schema::create('svp_reseller_wholesale_accruals', function ($table) {
                $table->id();
                $table->unsignedBigInteger('reseller_svp_user_id');
                $table->unsignedBigInteger('line_id')->default(0);
                $table->decimal('delta_gb', 15, 4)->default(0);
                $table->decimal('delta_wholesale_toman', 15, 2)->default(0);
                $table->timestamp('created_at')->nullable();
            });
        }

        DB::table('svp_users')->insert([
            [
                'id' => 100,
                'username' => 'reseller_a',
                'first_name' => 'Ali',
                'last_name' => 'A',
                'role' => 'reseller',
                'status' => 'approved',
                'balance' => 5000,
                'invited_by' => null,
                'created_at' => now(),
            ],
            [
                'id' => 101,
                'username' => 'customer_of_a',
                'first_name' => '',
                'last_name' => '',
                'role' => 'user',
                'status' => 'approved',
                'balance' => 0,
                'invited_by' => 100,
                'created_at' => now(),
            ],
        ]);
        DB::table('svp_reseller_closure')->insert([
            ['ancestor_id' => 100, 'descendant_id' => 100, 'depth' => 0],
            ['ancestor_id' => 100, 'descendant_id' => 101, 'depth' => 1],
        ]);
        DB::table('svp_transactions')->insert([
            'user_id' => 101,
            'amount' => 25000,
            'type' => 'purchase',
            'status' => 'completed',
            'billing_reseller_svp_id' => 100,
            'created_at' => now(),
        ]);
        DB::table('svp_reseller_wholesale_accruals')->insert([
            'reseller_svp_user_id' => 100,
            'line_id' => 1,
            'delta_gb' => 10,
            'delta_wholesale_toman' => 4000,
            'created_at' => now(),
        ]);
    }

    public function test_reseller_reports_includes_wp_shaped_summary_rows_daily(): void
    {
        $user = DashboardUser::query()->create([
            'username' => 'rr_admin_'.uniqid(),
            'password' => Hash::make('x'),
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $json = $this->getJson('/api/v1/admin/state?tab=reseller_reports&reseller_reports_window_days=30&reseller_reports_sort=sales')
            ->assertOk()
            ->assertJsonStructure([
                'resellerReports',
                'resellerReportsRows',
                'resellerReportsDaily',
                'resellerReportsStats' => [
                    'window_days',
                    'since',
                    'backfill_done',
                    'daily_scoped',
                    'summary' => [
                        'reseller_count',
                        'total_sales_toman',
                        'total_wholesale_toman',
                        'total_receipts_toman',
                        'total_downline_users',
                        'margin_est',
                        'top_reseller',
                    ],
                ],
            ])
            ->json();

        $this->assertSame($json['resellerReports'], $json['resellerReportsRows']);
        $this->assertGreaterThan(0, (float) ($json['resellerReportsStats']['summary']['total_sales_toman'] ?? 0));
        $this->assertNotEmpty($json['resellerReports']);
        $this->assertArrayHasKey('sales_toman', $json['resellerReports'][0]);
        $this->assertNotEmpty($json['resellerReportsDaily']);
        $this->assertArrayHasKey('date', $json['resellerReportsDaily'][0]);
        $this->assertArrayHasKey('sales_toman', $json['resellerReportsDaily'][0]);
        $this->assertArrayHasKey('wholesale_toman', $json['resellerReportsDaily'][0]);
    }
}
