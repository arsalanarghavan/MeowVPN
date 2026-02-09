<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('servers', 'is_central')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->boolean('is_central')->default(false)->after('is_active')
                    ->comment('Whether this server is the central node (only one should be true)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('servers', 'is_central')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->dropColumn('is_central');
            });
        }
    }
};
