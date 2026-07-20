<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('svp_plans')) {
            return;
        }

        Schema::table('svp_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('svp_plans', 'quota_display_mode')) {
                $table->string('quota_display_mode', 20)->default('show')->after('pricing_type');
            }
            if (! Schema::hasColumn('svp_plans', 'wholesale_line_id')) {
                $table->unsignedBigInteger('wholesale_line_id')->nullable()->after('panel_id');
                $table->index('wholesale_line_id', 'wholesale_line_id');
            }
            if (! Schema::hasColumn('svp_plans', 'owner_svp_user_id')) {
                $table->unsignedBigInteger('owner_svp_user_id')->default(0)->after('sort_order');
                $table->index('owner_svp_user_id', 'owner_svp_user');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('svp_plans')) {
            return;
        }

        Schema::table('svp_plans', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('svp_plans', 'owner_svp_user_id')) {
                $drop[] = 'owner_svp_user_id';
            }
            if (Schema::hasColumn('svp_plans', 'wholesale_line_id')) {
                $drop[] = 'wholesale_line_id';
            }
            if (Schema::hasColumn('svp_plans', 'quota_display_mode')) {
                $drop[] = 'quota_display_mode';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
