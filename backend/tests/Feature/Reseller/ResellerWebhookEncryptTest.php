<?php

namespace Tests\Feature\Reseller;

use App\Modules\Reseller\Services\ResellerBotProfileService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class ResellerWebhookEncryptTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        DB::table('svp_users')->insert([
            'id' => 50,
            'tg_user_id' => 500,
            'status' => 'approved',
            'created_at' => now(),
        ]);
    }

    public function test_webhook_secret_encrypted_at_rest(): void
    {
        $svc = app(ResellerBotProfileService::class);
        $plain = $svc->rotateWebhookSecret(50);
        $raw = DB::table('svp_reseller_bot_profiles')->where('reseller_svp_user_id', 50)->value('webhook_secret');
        $this->assertNotSame($plain, $raw);
        $this->assertSame($plain, Crypt::decryptString((string) $raw));
        $profile = $svc->findByReseller(50);
        $this->assertSame($plain, $svc->webhookSecretPlaintext($profile));
    }
}
