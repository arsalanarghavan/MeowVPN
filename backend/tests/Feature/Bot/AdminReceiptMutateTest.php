<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminUsersHandler;
use App\Modules\Core\Bot\Handlers\CallbackHandler;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminReceiptMutateTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
        app(SettingsStore::class)->set('telegram_admin_ids', [9001]);
        app(SettingsStore::class)->set('receipt_reject_reasons', ['blurry', 'wrong_amount']);
    }

    protected function admin(): SvpUser
    {
        return SvpUser::query()->create([
            'tg_user_id' => 9001,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
    }

    protected function pendingReceipt(): array
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 7930,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'amount' => 12000,
            'type' => 'purchase',
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $rcId = (int) DB::table('svp_receipts')->insertGetId([
            'user_id' => $user->id,
            'transaction_id' => $txId,
            'amount' => 12000,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        return [$rcId, $txId];
    }

    public function test_receipt_approve_via_admin_users_handler(): void
    {
        [$rcId] = $this->pendingReceipt();
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminUsersHandler::class)->handleCallback($ctx, ['pnl', 'urcp', 'a', (string) $rcId], $admin, 9001, 0);
        $this->assertSame('approved', (string) DB::table('svp_receipts')->where('id', $rcId)->value('status'));
        Http::assertSentCount(2);
    }

    public function test_receipt_set_status_reject_via_callback_handler(): void
    {
        [$rcId] = $this->pendingReceipt();
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(CallbackHandler::class)->handle($ctx, [
            'cb' => ['id' => 'cb1', 'data' => 'rc:rr:blurry:'.$rcId, 'from' => ['id' => 9001]],
            'user' => $admin,
            'chat_id' => 9001,
            'from' => ['id' => 9001],
        ]);
        $row = DB::table('svp_receipts')->where('id', $rcId)->first();
        $this->assertSame('rejected', (string) ($row->status ?? ''));
        $this->assertSame('blurry', (string) ($row->reject_reason ?? ''));
    }

    public function test_receipt_update_amount_via_admin_users_state(): void
    {
        [$rcId] = $this->pendingReceipt();
        $admin = $this->admin();
        app(\App\Modules\Core\Bot\Services\BotStateService::class)->set($admin, 'admin_users_receipt_update', [
            'receipt_id' => $rcId,
            'step' => 'amount',
        ]);
        $ctx = new BotContext('telegram');
        app(AdminUsersHandler::class)->routeState($ctx, $admin->fresh(), 9001, '15000');
        $this->assertSame(15000.0, (float) DB::table('svp_receipts')->where('id', $rcId)->value('amount'));
    }

    public function test_receipt_approve_via_rc_callback(): void
    {
        [$rcId, $txId] = $this->pendingReceipt();
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        Http::fake([
            '*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);
        app(CallbackHandler::class)->handle($ctx, [
            'cb' => ['id' => 'cb2', 'data' => 'rc:a:'.$rcId, 'from' => ['id' => 9001, 'username' => 'admin']],
            'user' => $admin,
            'chat_id' => 9001,
            'from' => ['id' => 9001],
        ]);
        $this->assertSame('approved', (string) DB::table('svp_receipts')->where('id', $rcId)->value('status'));
    }

    public function test_receipt_reject_prompt_via_rc_r_callback(): void
    {
        [$rcId] = $this->pendingReceipt();
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(CallbackHandler::class)->handle($ctx, [
            'cb' => ['id' => 'cb3', 'data' => 'rc:r:'.$rcId, 'from' => ['id' => 9001]],
            'user' => $admin,
            'chat_id' => 9001,
            'from' => ['id' => 9001],
        ]);
        $this->assertSame('pending', (string) DB::table('svp_receipts')->where('id', $rcId)->value('status'));
        Http::assertSentCount(1);
    }

    public function test_receipt_back_via_rc_rb_callback(): void
    {
        [$rcId] = $this->pendingReceipt();
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(CallbackHandler::class)->handle($ctx, [
            'cb' => ['id' => 'cb4', 'data' => 'rc:rb:'.$rcId, 'from' => ['id' => 9001]],
            'user' => $admin,
            'chat_id' => 9001,
            'from' => ['id' => 9001],
        ]);
        $this->assertSame('pending', (string) DB::table('svp_receipts')->where('id', $rcId)->value('status'));
    }
}
