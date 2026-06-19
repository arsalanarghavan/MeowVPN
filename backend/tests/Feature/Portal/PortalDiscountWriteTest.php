<?php

namespace Tests\Feature\Portal;

use App\Models\SvpUser;
use App\Modules\Core\Services\Portal\PortalLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

/** §188 — portal discount_save write (v13). */
class PortalDiscountWriteTest extends TestCase
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

    public function test_portal_discount_save_creates_code(): void
    {
        $admin = SvpUser::query()->create([
            'username' => 'disc_admin',
            'role' => 'reseller',
            'status' => 'approved',
            'tg_user_id' => 77,
            'created_at' => now(),
        ]);
        $signed = app(PortalLinkService::class)->buildAdminLink((int) $admin->id, 3600);

        $this->postJson('/api/v1/portal/admin', array_merge($signed, [
            'op' => 'discount_save',
            'discount_code' => 'V13TEST',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'discount_max_uses' => 5,
            'discount_active' => 1,
        ]))->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('svp_discount_codes', ['code' => 'V13TEST']);
    }
}
