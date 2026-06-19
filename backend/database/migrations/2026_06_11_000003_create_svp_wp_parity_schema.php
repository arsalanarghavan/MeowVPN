<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // PHPUnit builds minimal svp_* tables via CreatesSvpTestSchema; full DDL is MySQL-only.
            return;
        }

        $path = database_path('schema/svp_schema.sql');
        if (! File::exists($path)) {
            throw new RuntimeException('Missing schema file: '.$path);
        }

        $sql = File::get($path);
        $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));

        foreach ($statements as $statement) {
            if ($statement === '' || str_starts_with($statement, '--')) {
                continue;
            }
            DB::unprepared($statement.';');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        $tables = [
            'svp_users', 'svp_cards', 'svp_transactions', 'svp_receipts', 'svp_pending_approvals',
            'svp_sync_codes', 'svp_texts', 'svp_broadcasts', 'svp_broadcast_queue', 'svp_users_bulk_jobs',
            'svp_users_bulk_job_items', 'svp_logs', 'svp_inbound_queue', 'svp_plans', 'svp_plan_categories',
            'svp_services', 'svp_l2tp_servers', 'svp_panels', 'svp_panel_online_daily', 'svp_monitor_hosts',
            'svp_marketing_rules', 'svp_marketing_offers', 'svp_discount_codes', 'svp_discount_redemptions',
            'svp_referral_events', 'svp_user_activity', 'svp_service_ip_log', 'svp_panel_inbound_clients',
            'svp_panel_inbound_api', 'svp_reseller_panel_prices', 'svp_reseller_parent_panel_floors',
            'svp_reseller_bot_profiles', 'svp_reseller_inbound_display_names', 'svp_reseller_closure',
            'svp_audit_log', 'svp_unit_economics_config', 'svp_unit_economics_servers', 'svp_panel_economics_lines',
            'svp_reseller_wholesale_lines', 'svp_reseller_wholesale_tiers', 'svp_reseller_wholesale_line_assignments',
            'svp_reseller_wholesale_accruals',
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            DB::statement("DROP TABLE IF EXISTS `{$table}`");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
