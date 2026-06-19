<?php

namespace Tests\Feature\Portal;

use App\Models\SvpUser;
use App\Modules\Core\Services\Portal\PortalLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class PortalAdminTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_portal_admin_rejects_invalid_signature(): void
    {
        $this->postJson('/api/v1/portal/admin', [
            'op' => 'stats',
            'svp_u' => 1,
            'svp_e' => time() + 3600,
            'svp_s' => 'bad',
            'nonce' => 'bad',
        ])->assertStatus(403);
    }

    public function test_portal_admin_accepts_valid_signature(): void
    {
        $admin = SvpUser::query()->create([
            'username' => 'portaladmin',
            'role' => 'reseller',
            'status' => 'approved',
            'balance' => 0,
            'created_at' => now(),
        ]);
        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'portal_link_secret'],
            ['value' => 'test-secret', 'updated_at' => now()]
        );

        $link = app(PortalLinkService::class);
        $signed = $link->buildAdminLink((int) $admin->id, 3600);

        $this->postJson('/api/v1/portal/admin', array_merge($signed, ['op' => 'stats']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.panels', []);
    }

    public function test_portal_admin_html_shell(): void
    {
        $admin = SvpUser::query()->create([
            'username' => 'portaladmin2',
            'role' => 'reseller',
            'status' => 'approved',
            'balance' => 0,
            'created_at' => now(),
        ]);
        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'portal_link_secret'],
            ['value' => 'test-secret', 'updated_at' => now()]
        );
        $link = app(PortalLinkService::class)->buildAdminLink((int) $admin->id, 3600);

        $this->get('/info?'.http_build_query([
            'svp_adm' => '1',
            'svp_u' => $link['svp_u'],
            'svp_e' => $link['svp_e'],
            'svp_s' => $link['svp_s'],
        ]))
            ->assertOk()
            ->assertSee('svp-admin', false)
            ->assertSee('/api/v1/portal/admin', false);
    }
}
