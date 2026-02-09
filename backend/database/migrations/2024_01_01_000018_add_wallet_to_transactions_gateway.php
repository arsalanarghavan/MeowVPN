<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add 'wallet' to transactions.gateway enum (used for purchase/renewal from wallet).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_gateway_check");
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_gateway_check CHECK (gateway::text = ANY (ARRAY['zibal'::text, 'card_to_card'::text, 'system'::text, 'wallet'::text]))");
    }

    /**
     * Revert to original gateway enum (without wallet).
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_gateway_check");
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_gateway_check CHECK (gateway::text = ANY (ARRAY['zibal'::text, 'card_to_card'::text, 'system'::text]))");
    }
};
