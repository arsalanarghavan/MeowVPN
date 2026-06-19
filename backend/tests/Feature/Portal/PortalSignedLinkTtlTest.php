<?php

namespace Tests\Feature\Portal;

use App\Models\SvpUser;
use App\Modules\Core\Services\Portal\PortalLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class PortalSignedLinkTtlTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'portal_link_secret'],
            ['value' => 'test-secret-32chars-minimum!!', 'updated_at' => now()]
        );
    }

    public function test_expired_customer_link_rejected(): void
    {
        $user = SvpUser::query()->create([
            'username' => 'ttluser',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $exp = time() - 60;
        $svc = app(PortalLinkService::class);
        $key = $svc->portalKey();
        $sig = hash_hmac('sha256', "{$user->id}|{$exp}", $key);

        $this->get('/info?'.http_build_query([
            'svp_u' => $user->id,
            'svp_e' => $exp,
            'svp_s' => $sig,
            'svp_p' => '1',
        ]))->assertStatus(403);
    }

    public function test_valid_customer_link_within_ttl(): void
    {
        $user = SvpUser::query()->create([
            'username' => 'ttluser2',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $link = app(PortalLinkService::class)->buildPortalLink((int) $user->id, 3600);

        $this->get('/info?'.http_build_query(array_merge($link, ['svp_p' => '1', 'svp_fmt' => 'sub'])))
            ->assertOk();
    }
}
