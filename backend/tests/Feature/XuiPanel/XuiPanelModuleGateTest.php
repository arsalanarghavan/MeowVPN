<?php

namespace Tests\Feature\XuiPanel;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class XuiPanelModuleGateTest extends TestCase
{
    use RefreshDatabase;
    use TogglesModules;

    public function test_configs_snapshot_returns_403_when_xui_disabled(): void
    {
        $this->setModuleEnabled('xui_panel', false);
        $user = DashboardUser::factory()->create(['role' => 'admin']);
        $this->actingAs($user)
            ->getJson('/api/v1/admin/configs-snapshot')
            ->assertForbidden()
            ->assertJsonPath('message', 'module_disabled');
    }
}
