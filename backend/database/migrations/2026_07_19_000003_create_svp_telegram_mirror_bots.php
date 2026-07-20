<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('svp_telegram_mirror_bots')) {
            Schema::create('svp_telegram_mirror_bots', function (Blueprint $table) {
                $table->id();
                $table->string('label')->default('');
                $table->text('telegram_token')->nullable();
                $table->string('telegram_bot_username', 128)->default('');
                $table->string('webhook_secret', 512)->default('');
                $table->string('telegram_secret_token', 255)->default('');
                $table->boolean('enabled')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamp('updated_at')->nullable();
                $table->index(['enabled', 'sort_order'], 'enabled_sort');
            });
        }

        if (Schema::hasTable('svp_inbound_queue') && ! Schema::hasColumn('svp_inbound_queue', 'mirror_bot_id')) {
            Schema::table('svp_inbound_queue', function (Blueprint $table) {
                $table->unsignedBigInteger('mirror_bot_id')->default(0)->after('reseller_svp_user_id');
            });
        }

        if (Schema::hasTable('svp_users') && ! Schema::hasColumn('svp_users', 'last_tg_mirror_bot_id')) {
            Schema::table('svp_users', function (Blueprint $table) {
                $table->unsignedBigInteger('last_tg_mirror_bot_id')->default(0)->after('signup_reseller_svp_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('svp_users') && Schema::hasColumn('svp_users', 'last_tg_mirror_bot_id')) {
            Schema::table('svp_users', function (Blueprint $table) {
                $table->dropColumn('last_tg_mirror_bot_id');
            });
        }

        if (Schema::hasTable('svp_inbound_queue') && Schema::hasColumn('svp_inbound_queue', 'mirror_bot_id')) {
            Schema::table('svp_inbound_queue', function (Blueprint $table) {
                $table->dropColumn('mirror_bot_id');
            });
        }

        Schema::dropIfExists('svp_telegram_mirror_bots');
    }
};
