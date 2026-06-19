<?php

namespace Tests\Feature\AdminState;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        DB::table('svp_transactions')->insert([
            'user_id' => 101,
            'amount' => 25000,
            'type' => 'purchase',
            'status' => 'completed',
            'billing_reseller_svp_id' => 100,
            'created_at' => now(),
        ]);
    }

    public function test_reseller_reports_includes_daily_chart(): void
    {
        $user = DashboardUser::query()->create([
            'username' => 'admin',
            'password' => Hash::make('x'),
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $this->getJson('/api/v1/admin/state?tab=reseller_reports')
            ->assertOk()
            ->assertJsonStructure(['resellerReportsDaily', 'resellerReportsStats']);
    }
}
