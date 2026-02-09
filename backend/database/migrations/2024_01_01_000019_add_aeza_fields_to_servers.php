<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add AEZA provisioning fields to servers table.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('provider', 50)->nullable()->after('panel_type')->comment('e.g. aeza');
            $table->string('aeza_server_id', 100)->nullable()->after('provider')->index();
            $table->string('aeza_order_id', 100)->nullable()->after('aeza_server_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['provider', 'aeza_server_id', 'aeza_order_id']);
        });
    }
};
