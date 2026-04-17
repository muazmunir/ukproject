<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Active / inactive flag
            $table->boolean('is_active')
                  ->default(true)
                  ->after('role');

            // Creator (superadmin/admin who created this user)
            $table->foreignId('created_by')
                  ->nullable()
                  ->after('is_active')
                  ->constrained('users')
                  ->nullOnDelete(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop FK first
            $table->dropForeign(['created_by']);

            // Drop columns
            $table->dropColumn(['created_by', 'is_active']);
        });
    }
};
