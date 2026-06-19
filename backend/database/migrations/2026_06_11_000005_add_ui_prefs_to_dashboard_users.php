<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_users', function (Blueprint $table) {
            $table->string('ui_accent', 32)->nullable()->after('permissions_json');
            $table->string('ui_theme', 32)->nullable()->after('ui_accent');
            $table->string('ui_sidebar', 32)->nullable()->after('ui_theme');
            $table->string('ui_lang', 8)->nullable()->after('ui_sidebar');
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_users', function (Blueprint $table) {
            $table->dropColumn(['ui_accent', 'ui_theme', 'ui_sidebar', 'ui_lang']);
        });
    }
};
