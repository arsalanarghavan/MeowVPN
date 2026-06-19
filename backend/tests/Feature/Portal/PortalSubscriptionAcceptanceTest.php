<?php

namespace Tests\Feature\Portal;

use App\Models\SvpUser;
use App\Modules\Core\Services\Portal\PortalLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class PortalSubscriptionAcceptanceTest extends TestCase
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

    public function test_subscription_plain_format(): void
    {
        $user = SvpUser::query()->create([
            'username' => 'subuser',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $link = app(PortalLinkService::class)->buildPortalLink((int) $user->id, 3600);

        $this->get('/info?'.http_build_query(array_merge($link, [
            'svp_p' => '1',
            'svp_fmt' => 'sub',
        ])))
            ->assertOk()
            ->assertHeader('content-type');
    }

    public function test_sub_token_route_is_registered(): void
    {
        $this->get('/sub/demo-token')
            ->assertOk()
            ->assertJsonPath('note', 'portal_html');
    }

    public function test_subscription_html_when_accept_html(): void
    {
        $user = SvpUser::query()->create([
            'username' => 'subuser2',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $link = app(PortalLinkService::class)->buildPortalLink((int) $user->id, 3600);

        $this->withHeaders(['Accept' => 'text/html'])
            ->get('/info?'.http_build_query(array_merge($link, ['svp_p' => '1'])))
            ->assertOk()
            ->assertSee('text/html', false);
    }
}
