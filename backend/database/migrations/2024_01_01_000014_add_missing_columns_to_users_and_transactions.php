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
            $table->string('phone')->nullable()->after('email');
        });

        // Add gateway tracking columns to transactions table
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('gateway_track_id')->nullable()->after('ref_id')->comment('شناسه پیگیری درگاه');
            $table->string('gateway_ref_id')->nullable()->after('gateway_track_id')->comment('شماره مرجع درگاه');
        });

        // Update type enum to include 'renewal'
        // For PostgreSQL, we need to add the new value to the enum
        DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_check");
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check CHECK (type::text = ANY (ARRAY['deposit'::text, 'purchase'::text, 'reseller_payment'::text, 'commission'::text, 'renewal'::text]))");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['gateway_track_id', 'gateway_ref_id']);
        });

        // Revert type enum
        DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_check");
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check CHECK (type::text = ANY (ARRAY['deposit'::text, 'purchase'::text, 'reseller_payment'::text, 'commission'::text]))");
    }
};

