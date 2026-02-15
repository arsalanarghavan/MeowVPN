<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('session_token', 64)->unique()->index();
            $table->string('ip_address', 45)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending')->index();
            $table->text('api_token')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_confirmations');
    }
};
