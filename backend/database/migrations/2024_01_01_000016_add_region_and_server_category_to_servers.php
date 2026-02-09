<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('servers', 'region')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('region', 20)->default('foreign')->after('location_tag')
                    ->comment('iran or foreign');
            });
        }

        if (!Schema::hasColumn('servers', 'server_category')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('server_category', 30)->default('direct')->after('region')
                    ->comment('tunnel_entry, tunnel_exit, or direct');
            });
        }

        if (Schema::hasColumn('servers', 'region') && Schema::hasColumn('servers', 'server_category')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('CREATE INDEX IF NOT EXISTS servers_region_index ON servers (region)');
                DB::statement('CREATE INDEX IF NOT EXISTS servers_server_category_index ON servers (server_category)');
                DB::statement('CREATE INDEX IF NOT EXISTS servers_region_server_category_index ON servers (region, server_category)');
            } else {
                Schema::table('servers', function (Blueprint $table) {
                    $table->index('region');
                    $table->index('server_category');
                    $table->index(['region', 'server_category']);
                });
            }
        }
    }

    public function down(): void
    {
        $cols = [];
        if (Schema::hasColumn('servers', 'region')) {
            $cols[] = 'region';
        }
        if (Schema::hasColumn('servers', 'server_category')) {
            $cols[] = 'server_category';
        }
        if (empty($cols)) {
            return;
        }
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS servers_region_index');
            DB::statement('DROP INDEX IF EXISTS servers_server_category_index');
            DB::statement('DROP INDEX IF EXISTS servers_region_server_category_index');
        } else {
            Schema::table('servers', function (Blueprint $table) {
                if (Schema::hasColumn('servers', 'region')) {
                    $table->dropIndex(['region']);
                }
                if (Schema::hasColumn('servers', 'server_category')) {
                    $table->dropIndex(['server_category']);
                }
                if (Schema::hasColumn('servers', 'region') && Schema::hasColumn('servers', 'server_category')) {
                    $table->dropIndex(['region', 'server_category']);
                }
            });
        }
        Schema::table('servers', function (Blueprint $table) use ($cols) {
            $table->dropColumn($cols);
        });
    }
};
