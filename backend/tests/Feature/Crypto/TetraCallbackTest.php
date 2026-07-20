<?php

namespace Tests\Feature\Crypto;

use App\Modules\Crypto\Jobs\CryptoFulfillJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class TetraCallbackTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('crypto', true);

        Http::fake([
            'https://tetra98.com/api/verify' => Http::response([
                'status' => '100',
                'hash_id' => 'svp-50',
            ]),
        ]);

        app(\App\Services\SettingsStore::class)->set('crypto_tetra_callback_path_secret', 'test-tetra-path');
        app(\App\Services\SettingsStore::class)->set('crypto_tetra_api_key', 'tetra-key');
        DB::table('svp_transactions')->where('id', 50)->update([
            'meta_json' => json_encode([
                'plan_id' => 1,
                'tetra_authority' => 'AUTH-50',
                'tetra_hash_id' => 'svp-50',
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function test_tetra_callback_verifies_and_queues_fulfill(): void
    {
        Bus::fake([CryptoFulfillJob::class]);

        $this->postJson('/api/v1/tetra-callback/test-tetra-path', [
            'status' => '100',
            'authority' => 'AUTH-50',
            'hashid' => 'svp-50',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame('processing', DB::table('svp_transactions')->where('id', 50)->value('status'));
        Bus::assertDispatched(CryptoFulfillJob::class, function (CryptoFulfillJob $job) {
            return $job->transactionId === 50 && $job->source === 'tetra';
        });
    }

    public function test_tetra_callback_forbidden_bad_secret(): void
    {
        $this->postJson('/api/v1/tetra-callback/wrong', [
            'status' => '100',
            'authority' => 'AUTH-50',
        ])->assertStatus(403);
    }
}
