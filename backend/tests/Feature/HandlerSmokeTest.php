<?php

namespace Tests\Feature;

use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminBackupHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminBulkHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminCatalogHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminEconomicsHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminFinanceHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminInboundHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminLogsHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminMarketingHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminPanelHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminPnlHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminReceiptsHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminResellersHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminSettingsHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminStatsHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminTextsHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminUsersHandler;
use App\Modules\Core\Bot\Handlers\AppsHandler;
use App\Modules\Core\Bot\Handlers\BuyHandler;
use App\Modules\Core\Bot\Handlers\ReferralHandler;
use App\Modules\Core\Bot\Handlers\ServiceHandler;
use App\Modules\Core\Bot\Handlers\SupportHandler;
use App\Modules\Core\Bot\Handlers\SyncHandler;
use App\Modules\Core\Bot\Handlers\WalletHandler;
use App\Models\SvpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class HandlerSmokeTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    /** @return array<string, array{0: class-string}> */
    public static function handlerClasses(): array
    {
        $classes = [
            BuyHandler::class,
            ServiceHandler::class,
            WalletHandler::class,
            SupportHandler::class,
            AppsHandler::class,
            ReferralHandler::class,
            SyncHandler::class,
            AdminPnlHandler::class,
            AdminPanelHandler::class,
            AdminUsersHandler::class,
            AdminReceiptsHandler::class,
            AdminCatalogHandler::class,
            AdminFinanceHandler::class,
            AdminSettingsHandler::class,
            AdminTextsHandler::class,
            AdminStatsHandler::class,
            AdminMarketingHandler::class,
            AdminEconomicsHandler::class,
            AdminInboundHandler::class,
            AdminResellersHandler::class,
            AdminBulkHandler::class,
            AdminBackupHandler::class,
            AdminLogsHandler::class,
        ];
        $out = [];
        foreach ($classes as $class) {
            $out[$class] = [$class];
        }

        return $out;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    /** @dataProvider handlerClasses */
    public function test_handler_resolves_from_container(string $class): void
    {
        $this->assertInstanceOf($class, app($class));
    }

    public function test_admin_pnl_handle_does_not_throw(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 1,
            'status' => 'approved',
            'role' => 'user',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(AdminPnlHandler::class)->handle($ctx, ['pnl', 'dash'], $user, 1, 0, 1);
        $this->assertTrue(true);
    }
}
