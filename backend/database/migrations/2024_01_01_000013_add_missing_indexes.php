<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes to users table
        Schema::table('users', function (Blueprint $table) {
            $table->index('telegram_id');
            $table->index('parent_id');
            $table->index('role');
            $table->index(['role', 'parent_id']);
        });

        // Add indexes to subscriptions table
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index('uuid');
            $table->index('status');
            $table->index('expire_date');
            $table->index(['user_id', 'status']);
        });

        // Add indexes to transactions table
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('status');
            $table->index('type');
            $table->index(['user_id', 'status']);
            $table->index('gateway_track_id');
        });

        // Add indexes to servers table
        Schema::table('servers', function (Blueprint $table) {
            $table->index('is_active');
            $table->index('location_tag');
            $table->index(['is_active', 'location_tag']);
        });

        // Add indexes to subscription_links table
        Schema::table('subscription_links', function (Blueprint $table) {
            $table->index('subscription_id');
        });

        // Add indexes to invoices table
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('reseller_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['telegram_id']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['role']);
            $table->dropIndex(['role', 'parent_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['uuid']);
            $table->dropIndex(['status']);
            $table->dropIndex(['expire_date']);
            $table->dropIndex(['user_id', 'status']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['type']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['gateway_track_id']);
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['location_tag']);
            $table->dropIndex(['is_active', 'location_tag']);
        });

        Schema::table('subscription_links', function (Blueprint $table) {
            $table->dropIndex(['subscription_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['reseller_id']);
            $table->dropIndex(['status']);
        });
    }
};

