<?php

namespace Tests\Feature\L2tp;

use App\Models\DashboardUser;
use App\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class L2tpFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_hides_l2tp_when_module_disabled(): void
    {
        config(['modules.modules.l2tp.enabled' => false]);
        $this->app->forgetInstance(ModuleManager::class);

        $user = DashboardUser::query()->create([
            'username' => 'admin',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);

        $this->actingAs($user)->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('features.l2tp', false);
    }
}
