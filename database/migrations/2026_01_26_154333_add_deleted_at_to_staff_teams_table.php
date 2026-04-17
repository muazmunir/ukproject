<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_teams', function (Blueprint $table) {
            // Soft delete column
            $table->softDeletes()->after('is_active');

            // Optional but recommended for clarity
            $table->boolean('is_active')->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('staff_teams', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
