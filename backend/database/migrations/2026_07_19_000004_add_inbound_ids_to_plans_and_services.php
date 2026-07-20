<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP activator maybe_migrate_249_inbound_ids — multi-inbound scope on plans/services.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('svp_plans') && ! Schema::hasColumn('svp_plans', 'inbound_ids')) {
            Schema::table('svp_plans', function (Blueprint $table) {
                $table->longText('inbound_ids')->nullable()->after('inbound_id');
            });
            DB::table('svp_plans')
                ->where('inbound_id', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('inbound_ids')->orWhere('inbound_ids', '');
                })
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $iid = (int) ($row->inbound_id ?? 0);
                        if ($iid < 1) {
                            continue;
                        }
                        DB::table('svp_plans')->where('id', $row->id)->update([
                            'inbound_ids' => json_encode([$iid]),
                        ]);
                    }
                });
        }

        if (Schema::hasTable('svp_services') && ! Schema::hasColumn('svp_services', 'inbound_ids')) {
            Schema::table('svp_services', function (Blueprint $table) {
                $table->longText('inbound_ids')->nullable()->after('inbound_id');
            });
            DB::table('svp_services')
                ->where('inbound_id', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('inbound_ids')->orWhere('inbound_ids', '');
                })
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $iid = (int) ($row->inbound_id ?? 0);
                        if ($iid < 1) {
                            continue;
                        }
                        DB::table('svp_services')->where('id', $row->id)->update([
                            'inbound_ids' => json_encode([$iid]),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('svp_plans') && Schema::hasColumn('svp_plans', 'inbound_ids')) {
            Schema::table('svp_plans', function (Blueprint $table) {
                $table->dropColumn('inbound_ids');
            });
        }
        if (Schema::hasTable('svp_services') && Schema::hasColumn('svp_services', 'inbound_ids')) {
            Schema::table('svp_services', function (Blueprint $table) {
                $table->dropColumn('inbound_ids');
            });
        }
    }
};
