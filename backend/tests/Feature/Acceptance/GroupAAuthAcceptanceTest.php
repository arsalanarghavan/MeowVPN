<?php

namespace Tests\Feature\Acceptance;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 Group A — Overview & Auth */
class GroupAAuthAcceptanceTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_bootstrap_optional_without_auth(): void
    {
        $this->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('isLoggedIn', false)
            ->assertJsonStructure(['features', 'branding']);
    }

    public function test_login_and_me_state(): void
    {
        $user = DashboardUser::query()->where('username', 'admin')->first();
        $this->actingAs($user);

        $this->getJson('/api/v1/me/state')
            ->assertOk()
            ->assertJsonPath('isLoggedIn', true);
    }

    public function test_persona_switch(): void
    {
        $user = DashboardUser::query()->where('username', 'admin')->first();
        $this->actingAs($user);

        $this->postJson('/api/v1/dashboard/persona', ['persona' => 'admin'])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_login_failure_returns_ok_false(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'log' => 'admin',
            'pwd' => 'wrong-password',
        ])->assertUnauthorized()->assertJsonPath('ok', false);
    }

    public function test_csrf_cookie_endpoint(): void
    {
        $this->get('/sanctum/csrf-cookie')->assertNoContent();
    }

    public function test_overview_metrics_in_admin_state(): void
    {
        $this->actingAs(DashboardUser::query()->where('username', 'admin')->first());

        $this->getJson('/api/v1/admin/state?tab=dashboard&overview_metrics_window_days=30')
            ->assertOk()
            ->assertJsonStructure(['overview']);
    }

    public function test_reseller_overview_scoped(): void
    {
        $this->actingAs(DashboardUser::query()->where('username', 'reseller')->first());

        $this->getJson('/api/v1/admin/state?tab=dashboard')
            ->assertOk()
            ->assertJsonStructure(['resellerOverviewMetrics', 'resellerAllowedTabs']);
    }

    public function test_monitoring_hosts_in_state(): void
    {
        $this->actingAs(DashboardUser::query()->where('username', 'admin')->first());

        $this->getJson('/api/v1/admin/state?tab=monitoring')
            ->assertOk()
            ->assertJsonStructure(['monitorHosts', 'overview']);
    }

    public function test_refresh_live_metrics_query_accepted(): void
    {
        $this->actingAs(DashboardUser::query()->where('username', 'admin')->first());

        $this->getJson('/api/v1/admin/state?tab=monitoring&refreshLivePanelMetrics=1')
            ->assertOk()
            ->assertJsonStructure(['overview']);
    }
}
