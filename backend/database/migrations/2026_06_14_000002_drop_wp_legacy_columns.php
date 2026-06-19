<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('svp_users') && Schema::hasColumn('svp_users', 'wp_user_id')) {
            Schema::table('svp_users', function (Blueprint $table) {
                if ($this->indexExists('svp_users', 'svp_users_wp')) {
                    $table->dropUnique('svp_users_wp');
                }
                $table->dropColumn('wp_user_id');
            });
        }

        if (Schema::hasTable('svp_audit_log') && Schema::hasColumn('svp_audit_log', 'actor_wp_user_id')) {
            Schema::table('svp_audit_log', function (Blueprint $table) {
                $table->dropColumn('actor_wp_user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('svp_users') && ! Schema::hasColumn('svp_users', 'wp_user_id')) {
            Schema::table('svp_users', function (Blueprint $table) {
                $table->unsignedBigInteger('wp_user_id')->nullable()->after('bale_user_id');
                $table->unique('wp_user_id', 'svp_users_wp');
            });
        }

        if (Schema::hasTable('svp_audit_log') && ! Schema::hasColumn('svp_audit_log', 'actor_wp_user_id')) {
            Schema::table('svp_audit_log', function (Blueprint $table) {
                $table->unsignedBigInteger('actor_wp_user_id')->default(0);
            });
        }
    }

    protected function indexExists(string $table, string $index): bool
    {
        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $meta) {
            if (($meta['name'] ?? '') === $index) {
                return true;
            }
        }

        return false;
    }
};
