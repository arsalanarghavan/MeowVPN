<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** Runtime auth uses dashboard_users; default Laravel users table is unused. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('users');
    }

    public function down(): void
    {
        // Intentionally empty — users table was orphan; restore via 0001 migration if needed.
    }
};
