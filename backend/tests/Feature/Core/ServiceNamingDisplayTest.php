<?php

namespace Tests\Feature\Core;

use App\Support\Xui\ServiceNaming;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class ServiceNamingDisplayTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_platform_slug_mode_affects_provision_label(): void
    {
        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'service_naming_mode'],
            ['value' => 'platform_slug', 'updated_at' => now()]
        );

        $user = (object) ['id' => 101, 'username' => 'alice'];
        $label = ServiceNaming::provisionCanonicalLabel($user, 'telegram', 1);

        $this->assertStringStartsWith('telegram_', $label);
        $this->assertStringContainsString('alice', $label);
    }

    public function test_legacy_mode_uses_email_style(): void
    {
        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'service_naming_mode'],
            ['value' => 'legacy', 'updated_at' => now()]
        );

        $label = ServiceNaming::provisionCanonicalLabel((object) ['id' => 42], null, 1);
        $this->assertSame('u42@svp.local', $label);
    }

    public function test_format_service_display_label_prefix_numbered(): void
    {
        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'service_naming_mode'],
            ['value' => 'prefix_numbered', 'updated_at' => now()]
        );
        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'service_naming_prefix'],
            ['value' => 'SVC-', 'updated_at' => now()]
        );

        $svc = (object) ['id' => 5, 'email' => 'u5@svp.local', 'display_label' => '', 'remark' => ''];
        $this->assertSame('SVC-2', ServiceNaming::formatServiceDisplayLabel($svc, 2));
    }

    public function test_format_service_display_label_prefers_display_label(): void
    {
        $svc = (object) ['id' => 1, 'display_label' => 'My VPN', 'email' => 'a@b.c'];
        $this->assertSame('My VPN', ServiceNaming::formatServiceDisplayLabel($svc));
    }
}
