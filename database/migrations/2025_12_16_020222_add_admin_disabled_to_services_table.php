<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Admin lock flag
            $table->boolean('admin_disabled')
                  ->default(false)
                  ->after('is_active');

            // When admin disabled it
            $table->timestamp('admin_disabled_at')
                  ->nullable()
                  ->after('admin_disabled');

            // Optional reason shown to coach
            $table->string('admin_disabled_reason', 255)
                  ->nullable()
                  ->after('admin_disabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'admin_disabled',
                'admin_disabled_at',
                'admin_disabled_reason',
            ]);
        });
    }
};
