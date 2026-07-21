<?php

namespace Tests\Feature\Parity;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminUsersHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\ReceiptNotifyService;
use App\Modules\Core\Bot\Services\UserResolver;
use App\Modules\Core\Services\Portal\PortalLinkService;
use App\Modules\PasarGuard\Services\PanelClientFactory;
use App\Support\MutateOpCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AbsoluteParityWave5Test extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]], 200)]);
        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'admin_telegram_ids'],
            ['value' => json_encode([9001]), 'updated_at' => now()]
        );
        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'telegram_bot_token'],
            ['value' => '123:ABC', 'updated_at' => now()]
        );
        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'portal_link_secret'],
            ['value' => 'test-secret-32chars-minimum!!', 'updated_at' => now()]
        );
    }

    public function test_portal_ttl_constants_match_wp(): void
    {
        $this->assertSame(31536000, PortalLinkService::CUSTOMER_TTL);
        $this->assertSame(86400, PortalLinkService::ADMIN_TTL);
    }

    public function test_mutate_op_catalog_includes_crypto_and_rial(): void
    {
        $ops = MutateOpCatalog::all();
        $this->assertContains('crypto_settings', $ops);
        $this->assertContains('rial_settings', $ops);
        $this->assertSame(1, count(array_filter($ops, fn ($o) => $o === 'crypto_settings')));
        $this->assertSame(1, count(array_filter($ops, fn ($o) => $o === 'rial_settings')));
    }

    public function test_receipt_notify_writes_tg_file_id_and_delivers(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 9600,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'amount' => 5000,
            'type' => 'purchase',
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $rcId = (int) DB::table('svp_receipts')->insertGetId([
            'user_id' => $user->id,
            'transaction_id' => $txId,
            'amount' => 5000,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $ctx = new BotContext('telegram');
        $result = app(ReceiptNotifyService::class)->onUpload($ctx, $rcId, 'AgAC_file_abc');

        $this->assertTrue($result['ok']);
        $this->assertGreaterThan(0, $result['delivered']);
        $row = DB::table('svp_receipts')->where('id', $rcId)->first();
        $this->assertSame('AgAC_file_abc', (string) ($row->tg_file_id ?? ''));
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendPhoto') || str_contains($request->url(), 'sendMessage');
        });
    }

    public function test_moderation_receipt_approve_reply_without_admin_mode(): void
    {
        $admin = SvpUser::query()->create([
            'tg_user_id' => 9001,
            'status' => 'approved',
            'admin_mode' => false,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 9601,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'amount' => 1000,
            'type' => 'purchase',
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $rcId = (int) DB::table('svp_receipts')->insertGetId([
            'user_id' => $user->id,
            'transaction_id' => $txId,
            'amount' => 1000,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $ctx = new BotContext('telegram');
        $handled = app(AdminUsersHandler::class)->routeModerationReplyShortcuts(
            $ctx,
            $admin,
            9001,
            '✅ رسید '.$rcId,
            ['id' => 9001]
        );

        // Parity: reply keyboard works without admin_mode (platform admin only).
        $this->assertTrue($handled);
        $this->assertFalse((bool) $admin->admin_mode);
    }

    public function test_blocking_state_interrupt_on_main_menu_text(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 9700,
            'status' => 'approved',
            'state' => 'receipt_upload',
            'state_data' => json_encode([]),
            'created_at' => now(),
        ]);
        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'bot_texts'],
            ['value' => json_encode(['btn.main.buy' => '🛒 خرید سرویس']), 'updated_at' => now()]
        );

        $ctx = new BotContext('telegram');
        $state = app(BotStateService::class);
        $buyLabel = app(\App\Modules\Core\Bot\Services\TextService::class)->getForUser('btn.main.buy', $user, '🛒 خرید سرویس');
        $cleared = $state->interruptBlockingStateOnMainMenuText($ctx, 9700, $user, $buyLabel);

        $this->assertTrue($cleared);
        $this->assertSame('', $state->get($user->fresh()));
    }

    public function test_user_resolver_syncs_platform_identity(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 9800,
            'status' => 'approved',
            'first_name' => 'Old',
            'username' => '',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        $resolved = app(UserResolver::class)->resolve($ctx, [
            'id' => 9800,
            'username' => 'new_user',
            'first_name' => 'New',
            'last_name' => 'Name',
        ]);

        $this->assertNotNull($resolved);
        $this->assertSame('New', (string) $resolved->first_name);
        $this->assertSame('Name', (string) $resolved->last_name);
        $this->assertSame('new_user', (string) ($resolved->tg_username ?? $resolved->username));
    }

    public function test_panel_client_factory_is_bound(): void
    {
        $this->assertTrue(app()->bound(PanelClientFactory::class));
        $this->assertInstanceOf(PanelClientFactory::class, app(PanelClientFactory::class));
    }
}
