<?php

namespace Tests\Feature\Portal;

use App\Models\SvpService;
use App\Models\SvpUser;
use App\Modules\Core\Services\Portal\PortalLinkService;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class PortalThemeBootstrapTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'portal_link_secret'],
            ['value' => 'test-secret', 'updated_at' => now()]
        );
    }

    public function test_info_returns_theme_payload_for_uid_exp_sig(): void
    {
        $user = SvpUser::query()->create([
            'username' => 'themeuser',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        SvpService::factory()->create([
            'user_id' => $user->id,
            'display_label' => 'Theme Svc',
            'total_traffic' => 5 * 1024 * 1024 * 1024,
            'used_traffic' => 1024 * 1024 * 1024,
        ]);

        $link = app(PortalLinkService::class)->buildPortalLink((int) $user->id, 3600);

        $this->getJson('/info?'.http_build_query([
            'uid' => $link['svp_u'],
            'exp' => $link['svp_e'],
            'sig' => $link['svp_s'],
        ]))
            ->assertOk()
            ->assertJsonPath('user.username', 'Theme Svc')
            ->assertJsonStructure(['user', 'links', 'meta' => ['usage_endpoint', 'auth_qs'], 'cards']);
    }

    public function test_info_rejects_invalid_signature(): void
    {
        $this->getJson('/info?'.http_build_query([
            'uid' => 1,
            'exp' => time() + 3600,
            'sig' => 'invalidsignature',
        ]))
            ->assertOk()
            ->assertJsonPath('note', 'portal_html');
    }

    public function test_meta_theme_prefers_portal_subscription_template(): void
    {
        $settings = app(SettingsStore::class);
        $settings->set('portal_subscription_template', 'pasarguard_v1');
        $settings->set('portal_theme', 'modern');

        $user = SvpUser::query()->create([
            'username' => 'themetpl',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        SvpService::factory()->create([
            'user_id' => $user->id,
            'display_label' => 'Tpl Svc',
        ]);

        $link = app(PortalLinkService::class)->buildPortalLink((int) $user->id, 3600);

        $this->getJson('/info?'.http_build_query([
            'uid' => $link['svp_u'],
            'exp' => $link['svp_e'],
            'sig' => $link['svp_s'],
        ]))
            ->assertOk()
            ->assertJsonPath('meta.theme', 'pasarguard_v1');
    }

    public function test_meta_theme_falls_back_to_portal_theme(): void
    {
        DB::table('svp_settings')->where('key_name', 'portal_subscription_template')->delete();
        app(SettingsStore::class)->set('portal_theme', 'xui');

        $user = SvpUser::query()->create([
            'username' => 'themefallback',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        SvpService::factory()->create([
            'user_id' => $user->id,
            'display_label' => 'Fallback Svc',
        ]);

        $link = app(PortalLinkService::class)->buildPortalLink((int) $user->id, 3600);

        $this->getJson('/info?'.http_build_query([
            'uid' => $link['svp_u'],
            'exp' => $link['svp_e'],
            'sig' => $link['svp_s'],
        ]))
            ->assertOk()
            ->assertJsonPath('meta.theme', 'xui');
    }
}
