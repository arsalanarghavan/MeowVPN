<?php

namespace Tests\Unit\WaveD;

use App\Services\Auth\DashboardMagicLinkService;
use App\Services\Commerce\CardRotationService;
use App\Services\Marketing\MarketingGuardService;
use App\Services\NotificationDedupService;
use App\Services\QrService;
use App\Services\Reseller\ResellerBrandingService;
use App\Support\ConfigLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class WaveDServicesTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_config_link_fragment_helpers(): void
    {
        $uri = 'vless://uuid@host:443#Brand%20Name';
        $this->assertSame('Brand Name', ConfigLink::uriFragmentLabel($uri));
        $this->assertSame(
            'vless://uuid@host:443#New%20Label',
            ConfigLink::replaceUriFragment($uri, 'New Label')
        );
    }

    public function test_card_rotation_fits_daily_limit(): void
    {
        $svc = app(CardRotationService::class);
        $this->assertTrue($svc->fitsDailyLimit(0, 0, 100));
        $this->assertTrue($svc->fitsDailyLimit(50, 100, 50));
        $this->assertFalse($svc->fitsDailyLimit(90, 100, 20));
    }

    public function test_card_rotation_round_robin_advances_cursor(): void
    {
        $svc = app(CardRotationService::class);
        $cards = [
            (object) ['id' => 1, 'daily_limit' => 0],
            (object) ['id' => 2, 'daily_limit' => 0],
        ];
        $picked = $svc->pickForCheckout($cards, 'sequential', 'scope:1', 0);
        $this->assertCount(1, $picked);
        $this->assertSame(1, (int) $picked[0]->id);
        $this->assertSame(1, $svc->getCursor('scope:1'));
    }

    public function test_notification_dedup_claim_is_single_use(): void
    {
        $dedup = app(NotificationDedupService::class);
        $this->assertTrue($dedup->claim('expiry', 'svc1:expd:3', 1));
        $this->assertFalse($dedup->claim('expiry', 'svc1:expd:3', 1));
        $this->assertTrue($dedup->wasSent('expiry', 'svc1:expd:3'));
    }

    public function test_marketing_guard_blocks_when_lifecycle_unconfirmed(): void
    {
        app(\App\Services\SettingsStore::class)->set('marketing_lifecycle_confirmed', false);
        $guard = app(MarketingGuardService::class);
        $this->assertSame('awaiting_admin_confirm', $guard->cronBlockReason());
        $user = (object) ['id' => 1, 'status' => 'approved'];
        $rule = (object) ['segment_key' => 'upgrade_candidate', 'channel_telegram' => true, 'channel_bale' => true];
        $check = $guard->canSendToUser($user, $rule, false);
        $this->assertFalse($check['ok']);
        $this->assertSame('awaiting_admin_confirm', $check['reason']);
    }

    public function test_magic_link_verify_and_consume_contract(): void
    {
        $magic = app(DashboardMagicLinkService::class);
        $url = $magic->buildUrl('telegram', 12345);
        $parts = parse_url($url);
        parse_str((string) ($parts['query'] ?? ''), $params);
        $this->assertTrue($magic->verifyParams($params));
        $this->assertStringContainsString('/dashboard/auth/magic', $url);
    }

    public function test_reseller_branding_fragment_for_service(): void
    {
        $branding = app(ResellerBrandingService::class);
        $this->assertSame('', $branding->fragmentForService(0, 'svc'));
        $uris = ['vless://a@host:443#old'];
        $out = $branding->rewriteSubscriptionUrisForUser($uris, 0, 'svc');
        $this->assertSame($uris, $out);
    }

    public function test_qr_service_png_contract(): void
    {
        $qr = app(QrService::class);
        if (! $qr->isAvailable()) {
            $this->markTestSkipped('GD or chillerlan/php-qrcode unavailable');
        }
        $png = $qr->pngBytes('https://example.com/vless-test');
        $this->assertIsString($png);
        $this->assertStringStartsWith("\x89PNG", $png);
    }
}
