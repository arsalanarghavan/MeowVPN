<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('svp_panels')) {
            Schema::table('svp_panels', function (Blueprint $table) {
                if (! Schema::hasColumn('svp_panels', 'panel_provider')) {
                    $table->string('panel_provider', 32)->default('xui')->after('panel_api_flavor');
                }
                if (! Schema::hasColumn('svp_panels', 'panel_template_required')) {
                    $table->boolean('panel_template_required')->default(false)->after('panel_provider');
                }
            });
        }

        if (Schema::hasTable('svp_plans') && ! Schema::hasColumn('svp_plans', 'panel_template_id')) {
            Schema::table('svp_plans', function (Blueprint $table) {
                $table->unsignedInteger('panel_template_id')->nullable()->after('inbound_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('svp_panels')) {
            Schema::table('svp_panels', function (Blueprint $table) {
                if (Schema::hasColumn('svp_panels', 'panel_template_required')) {
                    $table->dropColumn('panel_template_required');
                }
                if (Schema::hasColumn('svp_panels', 'panel_provider')) {
                    $table->dropColumn('panel_provider');
                }
            });
        }
        if (Schema::hasTable('svp_plans') && Schema::hasColumn('svp_plans', 'panel_template_id')) {
            Schema::table('svp_plans', function (Blueprint $table) {
                $table->dropColumn('panel_template_id');
            });
        }
    }
};
