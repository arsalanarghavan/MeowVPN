<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add plan_id to subscriptions
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('user_id')->constrained('plans')->onDelete('set null');
        });

        // Add subscription_id and gateway fields to transactions
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('user_id')->constrained('subscriptions')->onDelete('set null');
            $table->string('gateway_track_id')->nullable()->after('ref_id');
            $table->string('gateway_ref_id')->nullable()->after('gateway_track_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn('plan_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->dropColumn(['subscription_id', 'gateway_track_id', 'gateway_ref_id']);
        });
    }
};

