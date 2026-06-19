<?php

namespace Database\Seeders;

use App\Models\DashboardUser;
use App\Models\SvpPanel;
use App\Models\SvpPlan;
use App\Models\SvpPlanCategory;
use App\Models\SvpUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SvpTestDataSeeder extends Seeder
{
    public function run(): void
    {
        DashboardUser::query()->updateOrCreate(
            ['username' => 'admin'],
            ['password' => Hash::make('changeme'), 'role' => 'admin']
        );

        DB::table('svp_users')->updateOrInsert(
            ['id' => 100],
            [
                'username' => 'reseller1',
                'role' => 'reseller',
                'status' => 'approved',
                'created_at' => now(),
            ]
        );

        DB::table('svp_users')->updateOrInsert(
            ['id' => 101],
            [
                'username' => 'child1',
                'role' => 'user',
                'status' => 'approved',
                'invited_by' => 100,
                'created_at' => now(),
            ]
        );

        DB::table('svp_users')->updateOrInsert(
            ['id' => 200],
            [
                'username' => 'outsider',
                'role' => 'user',
                'status' => 'approved',
                'created_at' => now(),
            ]
        );

        DashboardUser::query()->updateOrCreate(
            ['username' => 'reseller'],
            [
                'password' => Hash::make('changeme'),
                'role' => 'reseller',
                'svp_user_id' => 100,
                'permissions_json' => ['users.manage' => true, 'plans.manage' => true],
            ]
        );

        DB::table('svp_reseller_closure')->updateOrInsert(
            ['ancestor_id' => 100, 'descendant_id' => 100],
            ['depth' => 0]
        );
        DB::table('svp_reseller_closure')->updateOrInsert(
            ['ancestor_id' => 100, 'descendant_id' => 101],
            ['depth' => 1]
        );

        DB::table('svp_reseller_bot_profiles')->updateOrInsert(
            ['reseller_svp_user_id' => 100],
            [
                'webhook_secret' => 'test-reseller-webhook-secret',
                'telegram_token' => Crypt::encryptString('123456:reseller-telegram-token'),
                'enabled' => true,
                'telegram_enabled' => true,
                'updated_at' => now(),
            ]
        );

        $panel = SvpPanel::query()->updateOrCreate(
            ['id' => 1],
            [
                'label' => 'Panel 1',
                'panel_url' => 'https://panel.test',
                'panel_username' => 'admin',
                'panel_password' => 'secret',
                'panel_api_base' => 'panel/api',
                'panel_api_flavor' => 'legacy_inbound',
                'sort_order' => 1,
                'active' => true,
                'created_at' => now(),
            ]
        );

        SvpPlanCategory::query()->updateOrCreate(
            ['id' => 1],
            ['panel_id' => $panel->id, 'slug' => 'normal', 'label' => 'Normal', 'active' => true, 'sort_order' => 1, 'created_at' => now()]
        );

        SvpPlan::query()->updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Plan 1',
                'category' => 'normal',
                'panel_id' => $panel->id,
                'inbound_id' => 1,
                'service_type' => 'xray',
                'active' => true,
                'price' => 10000,
                'duration_days' => 30,
                'traffic_gb' => 50,
                'created_at' => now(),
            ]
        );

        DB::table('svp_users')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'user1',
                'role' => 'user',
                'status' => 'approved',
                'tg_user_id' => 900001,
                'first_name' => 'User',
                'created_at' => now()->subDays(5),
            ]
        );

        DB::table('svp_users')->where('id', 101)->update([
            'tg_user_id' => 900101,
            'first_name' => 'Child',
        ]);

        DB::table('svp_services')->updateOrInsert(
            ['id' => 1],
            [
                'user_id' => 101,
                'panel_id' => $panel->id,
                'email' => 'child@local',
                'total_traffic' => 0,
                'used_traffic' => 0,
                'created_at' => now(),
            ]
        );

        DB::table('svp_receipts')->updateOrInsert(
            ['id' => 1],
            [
                'user_id' => 101,
                'amount' => 50000,
                'status' => 'pending',
                'created_at' => now(),
            ]
        );

        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'telegram_bot_token'],
            ['value' => 'test-telegram-token', 'updated_at' => now()]
        );
        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'enabled'],
            ['value' => '1', 'updated_at' => now()]
        );

        DB::table('svp_l2tp_servers')->updateOrInsert(
            ['id' => 1],
            [
                'label' => 'Test L2TP',
                'ssh_host' => '10.0.0.1',
                'ssh_port' => 22,
                'ssh_user' => 'root',
                'ssh_auth' => 'key',
                'l2tp_host' => 'vpn.test',
                'active' => true,
                'created_at' => now(),
            ]
        );

        DB::table('svp_transactions')->updateOrInsert(
            ['id' => 50],
            [
                'user_id' => 101,
                'amount' => 10000,
                'type' => 'purchase',
                'status' => 'pending',
                'meta_json' => json_encode([
                    'plan_id' => 1,
                    'nowpayments_payment_id' => 'np-pay-1',
                ]),
                'created_at' => now(),
            ]
        );

        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'crypto_ipn_path_secret'],
            ['value' => 'test-ipn-path-secret', 'updated_at' => now()]
        );
        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'crypto_nowpayments_ipn_secret'],
            ['value' => 'test-ipn-hmac-secret', 'updated_at' => now()]
        );

        DB::table('svp_marketing_rules')->updateOrInsert(
            ['id' => 1],
            [
                'owner_svp_user_id' => 0,
                'segment_key' => 'never_purchased',
                'enabled' => true,
                'after_days' => 1,
                'discount_type' => 'percent',
                'discount_value' => 10,
                'code_valid_days' => 7,
                'max_uses_per_user' => 1,
                'message_body' => 'سلام {name}! کد تخفیف: {code}',
                'channel_telegram' => true,
                'channel_bale' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('svp_cards')->updateOrInsert(
            ['id' => 1],
            [
                'owner_svp_user_id' => 0,
                'card_number' => '6037-0000-0000-0001',
                'holder_name' => 'Test Card',
                'active' => true,
                'priority' => 1,
                'created_at' => now(),
            ]
        );

        DB::table('svp_reseller_wholesale_lines')->updateOrInsert(
            ['id' => 1],
            ['panel_id' => 1, 'inbound_id' => 1, 'label' => 'Wholesale 1', 'active' => true, 'created_at' => now()]
        );

        DB::table('svp_broadcasts')->updateOrInsert(
            ['id' => 1],
            ['owner_svp_user_id' => 0, 'type' => 'text', 'content' => 'test', 'status' => 'queued', 'created_at' => now()]
        );

        DB::table('svp_users_bulk_jobs')->updateOrInsert(
            ['id' => 1],
            [
                'operation' => 'wallet',
                'scope' => 'selected',
                'payload_json' => '{}',
                'status' => 'paused',
                'created_by_svp_user_id' => 0,
                'created_at' => now(),
            ]
        );

        DB::table('svp_panel_economics_lines')->updateOrInsert(
            ['id' => 1],
            [
                'panel_id' => 1,
                'label' => 'hosting',
                'amount' => 100,
                'active' => 1,
                'expires_at' => now()->addDays(3)->toDateString(),
                'created_at' => now(),
            ]
        );
    }
}
