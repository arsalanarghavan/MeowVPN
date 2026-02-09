<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track AEZA orders for provisioning flow (status, server id, IP, password when ready).
     */
    public function up(): void
    {
        Schema::create('aeza_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 100)->unique()->index();
            $table->string('status', 20)->default('pending')->index(); // pending, ready, failed
            $table->string('aeza_server_id', 100)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('root_password')->nullable(); // encrypted or plain for display once
            $table->json('meta')->nullable(); // product name, term, etc.
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aeza_orders');
    }
};
