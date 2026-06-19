<?php

namespace Tests\Feature\Reseller;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class ResellerBotScopeTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_reseller_sees_only_own_bots_in_state(): void
    {
        DB::table('svp_reseller_bot_profiles')->where('reseller_svp_user_id', 100)->update([
            'telegram_bot_username' => 'r100bot',
            'updated_at' => now(),
        ]);
        DB::table('svp_reseller_bot_profiles')->insert([
            'reseller_svp_user_id' => 200,
            'telegram_bot_username' => 'r200bot',
            'enabled' => true,
            'telegram_enabled' => true,
            'updated_at' => now(),
        ]);

        $json = $this->actingAsReseller()->getJson('/api/v1/admin/state?tab=reseller_bots')->assertOk()->json();
        $list = $json['botsList'] ?? $json['resellerBots'] ?? [];
        $this->assertIsArray($list);
        foreach ($list as $row) {
            $rid = (int) ($row['reseller_svp_user_id'] ?? $row['reseller_id'] ?? 0);
            if ($rid > 0) {
                $this->assertSame(100, $rid);
            }
        }
    }
}
