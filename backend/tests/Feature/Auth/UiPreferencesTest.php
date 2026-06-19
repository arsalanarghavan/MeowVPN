<?php

namespace Tests\Feature\Auth;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UiPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_ui_preferences_save(): void
    {
        $user = DashboardUser::query()->create([
            'username' => 'admin',
            'password' => Hash::make('changeme'),
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $this->postJson('/api/v1/dashboard/ui-preferences', [
            'ui_theme' => 'dark',
            'ui_accent' => 'blue',
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
