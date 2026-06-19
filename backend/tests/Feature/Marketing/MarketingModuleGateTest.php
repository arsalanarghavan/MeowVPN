<?php

namespace Tests\Feature\Marketing;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class MarketingModuleGateTest extends TestCase
{
    use RefreshDatabase;
    use TogglesModules;

    public function test_broadcast_queue_returns_403_when_marketing_disabled(): void
    {
        $this->setModuleEnabled('marketing', false);
        $user = DashboardUser::factory()->create(['role' => 'admin']);
        $this->actingAs($user)
            ->getJson('/api/v1/admin/broadcast-queue')
            ->assertForbidden()
            ->assertJsonPath('message', 'module_disabled');
    }

    public function test_broadcast_not_scheduled_when_marketing_disabled(): void
    {
        $this->setModuleEnabled('marketing', false);
        $this->artisan('schedule:list');
        $this->assertStringNotContainsString('svp:broadcast', $this->artisanOutput());
    }

    protected function artisanOutput(): string
    {
        return \Illuminate\Support\Facades\Artisan::output();
    }
}
