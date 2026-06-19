<?php

namespace Tests\Feature\AdminState;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class ReferralReportsAcceptanceTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        if (! \Illuminate\Support\Facades\Schema::hasTable('svp_referral_events')) {
            \Illuminate\Support\Facades\Schema::create('svp_referral_events', function ($table) {
                $table->id();
                $table->unsignedBigInteger('referrer_user_id')->nullable();
                $table->unsignedBigInteger('referred_user_id')->nullable();
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('event_type', 32)->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
        DB::table('svp_referral_events')->insert([
                'referrer_user_id' => 101,
                'referred_user_id' => 102,
                'amount' => 5000,
                'event_type' => 'signup',
                'created_at' => now(),
        ]);
    }

    public function test_referral_reports_tab_loads_stats(): void
    {
        $user = DashboardUser::query()->create([
            'username' => 'admin',
            'password' => Hash::make('x'),
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $this->getJson('/api/v1/admin/state?tab=referral_reports')
            ->assertOk()
            ->assertJsonStructure(['referralStats', 'referralEvents']);
    }
}
