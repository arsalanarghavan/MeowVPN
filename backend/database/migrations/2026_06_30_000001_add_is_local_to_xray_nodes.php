<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('svp_xray_nodes')) {
            return;
        }
        Schema::table('svp_xray_nodes', function (Blueprint $table) {
            if (! Schema::hasColumn('svp_xray_nodes', 'is_local')) {
                $table->boolean('is_local')->default(false)->after('is_primary');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('svp_xray_nodes') && Schema::hasColumn('svp_xray_nodes', 'is_local')) {
            Schema::table('svp_xray_nodes', fn (Blueprint $t) => $t->dropColumn('is_local'));
        }
    }
};
