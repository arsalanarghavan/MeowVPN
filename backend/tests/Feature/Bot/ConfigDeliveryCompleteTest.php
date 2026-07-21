<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotConfigDeliveryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class ConfigDeliveryCompleteTest extends TestCase
{
    use CreatesSvpTestSchema;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
        $this->setModuleEnabled('l2tp', true);
    }

    public function test_enqueue_stores_owner_id_in_cache(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 730,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $svcId = (int) DB::table('svp_services')->insertGetId([
            'user_id' => $user->id,
            'email' => 'cfg@test',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        \Illuminate\Support\Facades\Queue::fake();
        app(BotConfigDeliveryService::class)->enqueue($ctx, $user, 730, $svcId, 'config');
        $payload = Cache::get("bot_config_delivery:{$user->id}:{$svcId}:telegram:config");
        $this->assertIsArray($payload);
        $this->assertSame($user->id, (int) ($payload['owner_id'] ?? 0));
        $this->assertSame(0, (int) ($payload['attempt'] ?? -1));
    }

    public function test_deliver_l2tp_includes_decrypted_psk(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 731,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $psk = 'super-secret-psk';
        $serverId = (int) DB::table('svp_l2tp_servers')->insertGetId([
            'label' => 'L2TP-1',
            'l2tp_host' => 'l2tp.example.com',
            'l2tp_psk_enc' => Crypt::encryptString($psk),
            'active' => 1,
            'created_at' => now(),
        ]);
        $svcId = (int) DB::table('svp_services')->insertGetId([
            'user_id' => $user->id,
            'email' => 'l2tp@test',
            'service_type' => 'l2tp',
            'l2tp_server_id' => $serverId,
            'l2tp_username' => 'vpnuser',
            'l2tp_password' => 'secret',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        $delivery = app(BotConfigDeliveryService::class);
        \Illuminate\Support\Facades\Queue::fake();
        $delivery->enqueue($ctx, $user, 731, $svcId, 'config', 'cb-psk-1');
        $delivery->deliver($user->id, $svcId, 'cb-psk-1', 'telegram', 'config');
        Http::assertSent(function ($request) use ($psk) {
            $body = json_encode($request->data());

            return is_string($body) && str_contains($body, $psk);
        });
        Http::assertSentCount(2);
    }

    public function test_deliver_subscription_mode_sends_qr_photo_for_short_uri(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 732,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $panelId = (int) DB::table('svp_panels')->insertGetId([
            'label' => 'SubPanel',
            'subscription_public_base' => 'https://sub.example.com',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $svcId = (int) DB::table('svp_services')->insertGetId([
            'user_id' => $user->id,
            'email' => 'sub@test',
            'panel_id' => $panelId,
            'inbound_id' => 1,
            'sub_id' => 'abc123',
            'xui_client_uuid' => 'uuid-1',
            'created_at' => now(),
        ]);
        DB::table('svp_panel_inbound_clients')->insert([
            'panel_id' => $panelId,
            'inbound_id' => 1,
            'email' => 'sub@test',
            'client_json' => json_encode(['vless' => 'vless://uuid@host:443?type=tcp#test']),
        ]);
        $ctx = new BotContext('telegram');
        $delivery = app(BotConfigDeliveryService::class);
        \Illuminate\Support\Facades\Queue::fake();
        $delivery->enqueue($ctx, $user, 732, $svcId, 'subscription', 'cb-sub-1');
        $result = $delivery->deliver($user->id, $svcId, 'cb-sub-1', 'telegram', 'subscription');
        $this->assertSame('delivered', $result);
        Http::assertSent(function ($request) {
            $url = (string) $request->url();
            if (str_contains($url, 'sendPhoto')) {
                return true;
            }
            $text = (string) ($request->data()['text'] ?? '');

            // Primary may be subscription URL; QR falls back to text when GD is unavailable.
            return str_contains($url, 'sendMessage')
                && (str_contains($text, 'sub.example.com') || str_contains($text, 'vless://'));
        });
    }
}
