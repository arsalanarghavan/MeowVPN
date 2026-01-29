<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('server_id')->nullable()->constrained('servers')->onDelete('set null');
            $table->uuid('uuid')->unique()->index();
            $table->string('marzban_username')->unique();
            $table->enum('status', ['active', 'expired', 'banned'])->default('active')->index();
            $table->bigInteger('total_traffic')->default(0);
            $table->bigInteger('used_traffic')->default(0);
            $table->timestamp('expire_date')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['expire_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

