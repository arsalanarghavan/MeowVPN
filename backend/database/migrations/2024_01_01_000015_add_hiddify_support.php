<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add panel_type and api_key to servers table
        Schema::table('servers', function (Blueprint $table) {
            $table->enum('panel_type', ['marzban', 'hiddify'])->default('marzban')->after('is_active');
            $table->text('api_key')->nullable()->after('admin_pass')->comment('Hiddify API key');
        });

        // Add max_devices to plans table
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('max_devices')->default(1)->after('max_concurrent_users')->comment('Maximum concurrent devices');
        });

        // Add max_devices and panel_username to subscriptions table
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->integer('max_devices')->default(1)->after('expire_date')->comment('Maximum concurrent devices');
            $table->string('panel_username')->nullable()->after('marzban_username')->comment('Hiddify user UUID or panel-specific identifier');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['panel_type', 'api_key']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_devices');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['max_devices', 'panel_username']);
        });
    }
};

