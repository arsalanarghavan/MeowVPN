<?php

namespace Tests\Feature\InstallWizard;

use App\Models\DashboardUser;
use App\Services\InstallWizard\InstallWizardService;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class SetupWizardFlowTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected string $token = 'test-setup-token-abc';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpSchema();
        $settings = app(SettingsStore::class);
        $settings->merge([
            'public_site_url' => 'https://api.example.test',
            'dashboard_public_url' => 'https://panel.example.test',
        ]);
        app(InstallWizardService::class)->init($this->token, [
            'core_url' => 'https://api.example.test',
            'dashboard_url' => 'https://panel.example.test',
        ]);
    }

    protected function wizardHeaders(): array
    {
        return ['X-Install-Token' => $this->token];
    }

    public function test_status_is_public(): void
    {
        $this->getJson('/api/v1/setup/status')
            ->assertOk()
            ->assertJsonPath('open', true)
            ->assertJsonPath('pending', true);
    }

    public function test_domains_requires_token(): void
    {
        $this->getJson('/api/v1/setup/domains')->assertForbidden();
        $this->getJson('/api/v1/setup/domains', $this->wizardHeaders())->assertOk();
    }

    public function test_login_blocked_while_wizard_open(): void
    {
        DashboardUser::query()->create([
            'username' => 'admin',
            'password' => Hash::make('password12345'),
            'role' => 'admin',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'admin',
            'password' => 'password12345',
        ])->assertForbidden()
            ->assertJsonPath('code', 'wizard_pending');
    }

    public function test_admin_credentials_and_complete_lock_wizard(): void
    {
        $this->postJson('/api/v1/setup/admin-credentials', [
            'username' => 'owner',
            'password' => 'secure-pass-1',
            'password_confirm' => 'secure-pass-1',
        ], $this->wizardHeaders())->assertOk();

        $user = DashboardUser::query()->where('username', 'owner')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('secure-pass-1', $user->password));

        $this->postJson('/api/v1/setup/complete', [], $this->wizardHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true);

        $wizard = app(InstallWizardService::class);
        $this->assertFalse($wizard->isOpen());
        $this->assertTrue($wizard->isCompleted());

        $this->getJson('/api/v1/setup/domains', $this->wizardHeaders())->assertStatus(410);
    }
}
