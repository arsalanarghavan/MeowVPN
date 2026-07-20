<?php

namespace Tests\Feature;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminRestEndpointsTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_me_state_without_auth_returns_logged_out_payload(): void
    {
        $this->getJson('/api/v1/me/state')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('isLoggedIn', false)
            ->assertJsonPath('loggedIn', false);
    }

    public function test_me_state_returns_boot_payload(): void
    {
        $user = DashboardUser::query()->create([
            'username' => 'admin',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);

        $this->actingAs($user)->getJson('/api/v1/me/state')
            ->assertOk()
            ->assertJsonPath('isLoggedIn', true);
    }

    public function test_audit_endpoint_returns_rows(): void
    {
        if (! Schema::hasTable('svp_audit_log')) {
            Schema::create('svp_audit_log', function ($table) {
                $table->id();
                $table->string('domain', 32)->nullable();
                $table->string('event_type', 64)->nullable();
                $table->string('actor_label')->nullable();
                $table->text('payload_json')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
        DB::table('svp_audit_log')->insert([
            'domain' => 'admin',
            'event_type' => 'test.event',
            'actor_label' => 'admin',
            'payload_json' => '{}',
            'created_at' => now(),
        ]);

        $user = DashboardUser::query()->create([
            'username' => 'admin',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);

        $this->actingAs($user)->getJson('/api/v1/admin/audit')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['rows', 'pagination']);
    }

    public function test_users_bulk_jobs_shape(): void
    {
        $user = DashboardUser::query()->create([
            'username' => 'admin',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);

        $this->actingAs($user)->getJson('/api/v1/admin/users-bulk-jobs')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['jobs', 'itemAggregates', 'pagination']);
    }
}
