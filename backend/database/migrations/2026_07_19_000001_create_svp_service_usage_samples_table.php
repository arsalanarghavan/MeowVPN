<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('svp_service_usage_samples')) {
            return;
        }
        Schema::create('svp_service_usage_samples', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->timestamp('sampled_at');
            $table->unsignedBigInteger('used_bytes')->default(0);
            $table->index(['service_id', 'sampled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('svp_service_usage_samples');
    }
};
