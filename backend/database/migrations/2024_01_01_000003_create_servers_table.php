<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('flag_emoji')->nullable();
            $table->string('ip_address');
            $table->string('api_domain');
            $table->string('admin_user');
            $table->string('admin_pass');
            $table->integer('capacity')->default(0)->comment('ظرفیت کل');
            $table->integer('active_users_count')->default(0)->comment('تعداد کاربران فعال');
            $table->enum('type', ['single', 'multi_relay'])->default('single');
            $table->string('location_tag', 10)->index()->comment('DE, TR, FR, etc.');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};

