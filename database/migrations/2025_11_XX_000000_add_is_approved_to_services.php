<?php
// database/migrations/2025_11_XX_000000_add_is_approved_to_services.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // 0 = pending, 1 = approved, -1 = rejected
            $table->tinyInteger('is_approved')
                  ->default(0)
                  ->after('is_active');
            $table->timestamp('approved_at')
                  ->nullable()
                  ->after('is_approved');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['is_approved', 'approved_at']);
        });
    }
};
