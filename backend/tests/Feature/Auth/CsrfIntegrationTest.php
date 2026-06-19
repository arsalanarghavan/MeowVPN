<?php

namespace Tests\Feature\Auth;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class CsrfIntegrationTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_mutate_without_csrf_returns_419(): void
    {
        $user = DashboardUser::factory()->create([
            'username' => 'csrfadmin',
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $this->withHeaders(['X-XSRF-TOKEN' => ''])
            ->postJson('/api/v1/admin/mutate', ['op' => 'settings_tab', 'tab' => 'general'])
            ->assertStatus(419);
    }

    public function test_mutate_with_csrf_cookie_succeeds(): void
    {
        $user = DashboardUser::factory()->create([
            'username' => 'csrfadmin2',
            'role' => 'admin',
        ]);

        $this->get('/sanctum/csrf-cookie');
        $token = $this->extractXsrfToken();
        $this->assertNotSame('', $token);

        $this->withHeaders(['X-XSRF-TOKEN' => $token])
            ->actingAs($user)
            ->postJson('/api/v1/admin/mutate', [
                'op' => 'settings_tab',
                'tab' => 'general',
                'site_name' => 'CSRF OK',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    protected function extractXsrfToken(): string
    {
        $cookie = collect($this->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'XSRF-TOKEN');

        if (! $cookie) {
            return '';
        }

        return urldecode($cookie->getValue());
    }
}
