<?php

namespace Tests\Feature\Relay;

use Tests\TestCase;

class RelayModuleGateTest extends TestCase
{
    protected function disableRelay(): void
    {
        config(['modules.modules.relay.enabled' => false]);
        $this->app->forgetInstance(\App\Modules\ModuleManager::class);
    }

    public function test_relay_config_returns_403_when_module_disabled(): void
    {
        $this->disableRelay();
        $this->getJson('/api/v1/relay/config')->assertForbidden();
    }

    public function test_relay_mutate_returns_403_when_module_disabled(): void
    {
        $this->disableRelay();
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_test',
        ])->assertForbidden()->assertJsonPath('message', 'module_disabled');
    }

    protected function actingAsAdmin(): static
    {
        $user = \App\Models\DashboardUser::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        return $this;
    }
}
