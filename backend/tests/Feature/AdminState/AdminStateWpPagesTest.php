<?php

namespace Tests\Feature\AdminState;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class AdminStateWpPagesTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_admin_state_includes_portal_pages_from_settings(): void
    {
        app(SettingsStore::class)->set('portal_pages', [
            ['id' => 42, 'title' => 'Subscription Page', 'path' => '/info'],
        ]);
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/state?tab=site_settings')
            ->assertOk()
            ->assertJsonPath('portalPages.2.id', 42)
            ->assertJsonPath('portalPages.2.title', 'Subscription Page')
            ->assertJsonPath('wpPages.2.id', 42);
    }

    public function test_admin_state_includes_laravel_native_portal_defaults(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/state?tab=site_settings')
            ->assertOk()
            ->assertJsonPath('portalPages.0.path', '/info');
    }

    public function test_reseller_state_has_empty_portal_pages(): void
    {
        app(SettingsStore::class)->set('portal_pages', [
            ['id' => 42, 'title' => 'Subscription Page'],
        ]);
        $this->actingAsReseller();

        $this->getJson('/api/v1/admin/state?tab=dashboard')
            ->assertOk()
            ->assertJsonPath('portalPages', [])
            ->assertJsonPath('wpPages', []);
    }
}
