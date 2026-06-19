<?php

namespace Tests\Feature\Portal;

use App\Models\SvpUser;
use App\Modules\Core\Services\Portal\PortalLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class PortalTgAvatarTest extends TestCase
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

    public function test_tg_avatar_rejects_bad_nonce(): void
    {
        $admin = SvpUser::query()->create([
            'username' => 'adm',
            'role' => 'reseller',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $target = SvpUser::query()->create([
            'username' => 'tgt',
            'tg_user_id' => 12345,
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $signed = app(PortalLinkService::class)->buildAdminLink((int) $admin->id, 3600);

        $this->get('/api/v1/portal/tg-avatar?'.http_build_query([
            'svp_u' => $signed['svp_u'],
            'svp_e' => $signed['svp_e'],
            'svp_s' => $signed['svp_s'],
            'target_uid' => $target->id,
            'avnonce' => 'bad',
        ]))->assertStatus(403);
    }
}
