<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_id')->nullable()->unique()->index();
            $table->string('username')->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->enum('role', ['admin', 'reseller', 'affiliate', 'user'])->default('user')->index();
            $table->decimal('wallet_balance', 15, 2)->default(0);
            $table->decimal('credit_limit', 15, 2)->nullable()->comment('سقف بدهی نماینده');
            $table->decimal('current_debt', 15, 2)->default(0)->comment('بدهی جاری نماینده');
            $table->foreignId('parent_id')->nullable()->constrained('users')->onDelete('set null')->index();
            $table->rememberToken();
            $table->timestamps();
            
            $table->index(['role', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

