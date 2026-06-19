<?php

namespace Tests\Feature\Bot;

use App\Models\SvpService;
use App\Models\SvpUser;
use App\Services\Commerce\TransactionFulfillService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class CryptoServiceFulfillTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_service_renew_transaction_fulfills_and_extends_expiry(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 770,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $svc = SvpService::query()->create([
            'user_id' => $user->id,
            'email' => 'crypto-renew@test',
            'expires_at' => now()->subDay(),
            'created_at' => now(),
        ]);
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'service_id' => $svc->id,
            'amount' => 1500,
            'type' => 'service_renew',
            'status' => 'pending',
            'meta_json' => json_encode(['service_id' => $svc->id]),
            'created_at' => now(),
        ]);
        $result = app(TransactionFulfillService::class)->fulfillByTransaction($txId, 'crypto');
        $this->assertTrue($result['ok']);
        $fresh = SvpService::query()->find($svc->id);
        $this->assertTrue($fresh?->expires_at?->isFuture() ?? false);
    }
}
