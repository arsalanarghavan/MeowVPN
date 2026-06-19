<?php

namespace Tests\Feature\Settings;

use App\Models\DashboardUser;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class SettingsStateRedactTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_admin_state_masks_bot_token(): void
    {
        app(SettingsStore::class)->set('telegram_bot_token', 'super-secret');

        $admin = DashboardUser::query()->where('role', 'admin')->first();
        $response = $this->actingAs($admin)->getJson('/api/v1/admin/state?load=settings');

        $response->assertOk();
        $token = data_get($response->json(), 'settings.telegram_bot_token');
        $this->assertSame('••••••••', $token);
        $this->assertNotSame('super-secret', $token);
    }
}
