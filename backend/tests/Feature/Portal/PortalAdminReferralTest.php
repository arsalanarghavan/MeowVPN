<?php

namespace Tests\Feature\Portal;

use App\Modules\Core\Services\Portal\PortalAdminService;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class PortalAdminReferralTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_referral_save_and_get_roundtrip(): void
    {
        $settings = app(SettingsStore::class);
        $svc = app(PortalAdminService::class);
        $admin = \App\Models\SvpUser::factory()->create(['status' => 'approved', 'role' => 'admin']);
        $save = $svc->handle('referral_save', [
            'referral_enabled' => '1',
            'referral_percent' => '10',
            'referral_min_payout_base' => '50000',
            'referral_example_base_toman' => '200000',
            'referral_example_invite_count' => '15',
            'referral_require_approved_referrer' => '1',
        ], $admin);
        $this->assertTrue($save['ok'] ?? false);

        $get = $svc->handle('referral_get', [], $admin);
        $data = $get['data'] ?? $get;
        $this->assertSame(200000.0, (float) ($data['referral_example_base_toman'] ?? 0));
        $this->assertSame(15, (int) ($data['referral_example_invite_count'] ?? 0));
        $this->assertTrue((bool) ($data['referral_require_approved_referrer'] ?? false));
    }
}
