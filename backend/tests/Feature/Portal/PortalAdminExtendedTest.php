<?php

namespace Tests\Feature\Portal;

use App\Models\SvpUser;
use App\Modules\Core\Services\Portal\PortalLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class PortalAdminExtendedTest extends TestCase
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

    /** @return array<string, mixed> */
    protected function adminSigned(SvpUser $admin): array
    {
        $signed = app(PortalLinkService::class)->buildAdminLink((int) $admin->id, 3600);

        return array_merge($signed, ['op' => 'stats']);
    }

    public function test_membership_detail_includes_core_fields(): void
    {
        $admin = SvpUser::query()->create([
            'username' => 'pa2',
            'role' => 'reseller',
            'status' => 'approved',
            'tg_user_id' => 3,
            'created_at' => now(),
        ]);
        $target = SvpUser::query()->create([
            'username' => 'detail_user',
            'role' => 'user',
            'status' => 'pending',
            'first_name' => 'Detail',
            'created_at' => now(),
        ]);
        $signed = app(PortalLinkService::class)->buildAdminLink((int) $admin->id, 3600);

        $this->postJson('/api/v1/portal/admin', array_merge($signed, [
            'op' => 'membership_detail',
            'user_id' => $target->id,
        ]))->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id', 'username', 'status']]);
    }

    public function test_membership_detail_returns_avatar_url_for_telegram_user(): void
    {
        $admin = SvpUser::query()->create([
            'username' => 'pa',
            'role' => 'reseller',
            'status' => 'approved',
            'tg_user_id' => 1,
            'created_at' => now(),
        ]);
        $target = SvpUser::query()->create([
            'username' => 'pending1',
            'role' => 'user',
            'status' => 'pending',
            'tg_user_id' => 999,
            'created_at' => now(),
        ]);
        $signed = app(PortalLinkService::class)->buildAdminLink((int) $admin->id, 3600);

        $this->postJson('/api/v1/portal/admin', array_merge($signed, [
            'op' => 'membership_detail',
            'user_id' => $target->id,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['avatar_url']]);
    }

    public function test_discount_list_for_site_admin(): void
    {
        $admin = SvpUser::query()->create([
            'username' => 'siteadm',
            'role' => 'reseller',
            'status' => 'approved',
            'tg_user_id' => 2,
            'created_at' => now(),
        ]);
        $signed = app(PortalLinkService::class)->buildAdminLink((int) $admin->id, 3600);

        $this->postJson('/api/v1/portal/admin', array_merge($signed, ['op' => 'discount_list']))
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
