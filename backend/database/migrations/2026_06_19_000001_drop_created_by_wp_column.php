<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('svp_users_bulk_jobs') && Schema::hasColumn('svp_users_bulk_jobs', 'created_by_wp')) {
            Schema::table('svp_users_bulk_jobs', function (Blueprint $table) {
                $table->dropColumn('created_by_wp');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('svp_users_bulk_jobs') && ! Schema::hasColumn('svp_users_bulk_jobs', 'created_by_wp')) {
            Schema::table('svp_users_bulk_jobs', function (Blueprint $table) {
                $table->unsignedBigInteger('created_by_wp')->default(0)->after('status');
            });
        }
    }
};
