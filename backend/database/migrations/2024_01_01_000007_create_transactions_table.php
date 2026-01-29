<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['deposit', 'purchase', 'reseller_payment', 'commission'])->index();
            $table->enum('gateway', ['zibal', 'card_to_card', 'system'])->default('system');
            $table->enum('status', ['pending', 'completed', 'failed', 'rejected'])->default('pending')->index();
            $table->string('proof_image')->nullable()->comment('برای کارت به کارت');
            $table->string('ref_id')->nullable()->unique();
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'type', 'status']);
            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

