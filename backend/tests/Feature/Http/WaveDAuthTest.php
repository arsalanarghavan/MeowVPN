<?php

namespace Tests\Feature\Http;

use App\Models\DashboardUser;
use App\Models\SvpUser;
use App\Services\Auth\DashboardMagicLinkService;
use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class WaveDAuthTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
    }

    public function test_dashboard_login_boot_includes_bale_and_magic_urls(): void
    {
        $this->getJson('/api/v1/dashboard/login')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'telegram_login_enabled',
                'bale_login_enabled',
                'telegram_login_url',
                'magic_issue_url',
                'magic_consume_url',
            ]);
    }

    public function test_magic_link_issue_returns_signed_url(): void
    {
        $svp = SvpUser::query()->where('tg_user_id', '>', 0)->firstOrFail();

        $this->postJson('/api/v1/dashboard/login/magic/issue', [
            'platform' => 'telegram',
            'platform_user_id' => (int) $svp->tg_user_id,
            'locale' => 'fa',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['url', 'ttl']);

        $url = (string) $this->postJson('/api/v1/dashboard/login/magic/issue', [
            'platform' => 'telegram',
            'platform_user_id' => (int) $svp->tg_user_id,
        ])->json('url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        $this->assertTrue(app(DashboardMagicLinkService::class)->verifyParams($params));
    }

    public function test_magic_link_consume_establishes_session(): void
    {
        $svp = SvpUser::query()->where('tg_user_id', '>', 0)->firstOrFail();
        DashboardUser::query()->updateOrCreate(
            ['username' => 'magic-user'],
            ['password' => bcrypt('secret'), 'role' => 'admin', 'svp_user_id' => (int) $svp->id]
        );

        $magic = app(DashboardMagicLinkService::class);
        $url = $magic->buildUrl('telegram', (int) $svp->tg_user_id);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        $this->postJson('/api/v1/dashboard/login/magic', $params)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertAuthenticated('web');
    }
}
