<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add phone column to users table
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
        });

        // Add missing columns to transactions table
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'gateway_track_id')) {
                $table->string('gateway_track_id')->nullable()->after('ref_id');
            }
            if (!Schema::hasColumn('transactions', 'gateway_ref_id')) {
                $table->string('gateway_ref_id')->nullable()->after('gateway_track_id');
            }
        });

        // Update transactions type enum to include 'renewal'
        // PostgreSQL approach - alter enum type
        DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_check");
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check CHECK (type::text = ANY (ARRAY['deposit'::text, 'purchase'::text, 'reseller_payment'::text, 'commission'::text, 'renewal'::text]))");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn('phone');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'gateway_track_id')) {
                $table->dropColumn('gateway_track_id');
            }
            if (Schema::hasColumn('transactions', 'gateway_ref_id')) {
                $table->dropColumn('gateway_ref_id');
            }
        });

        // Revert enum change
        DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_check");
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check CHECK (type::text = ANY (ARRAY['deposit'::text, 'purchase'::text, 'reseller_payment'::text, 'commission'::text]))");
    }
};

