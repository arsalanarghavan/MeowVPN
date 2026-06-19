<?php

namespace Tests\Feature\Reseller;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class ResellerBotWebhookMutateTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        putenv('SVP_MODULE_RELAY=false');
        parent::setUp();
        $this->app->forgetInstance(\App\Modules\ModuleManager::class);
        $this->setUpMutateFixtures();
        app(SettingsStore::class)->set('public_site_url', 'https://example.test');
    }

    public function test_reseller_bot_webhook_set_calls_telegram_api(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
        ]);

        $this->actingAsReseller()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_bot_webhook_set',
            'reseller_svp_user_id' => 100,
            'platform' => 'telegram',
        ])->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'setWebhook')
                && str_contains((string) $request['url'], '/reseller/100/');
        });
    }
}
