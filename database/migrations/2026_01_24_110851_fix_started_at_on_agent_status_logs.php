<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_status_logs', function (Blueprint $table) {
            // Change TIMESTAMP → DATETIME to avoid auto-update behavior
            $table->dateTime('started_at')->change();
            $table->dateTime('ended_at')->nullable()->change();
        });

        // Safety: ensure started_at is NOT auto-updated by MySQL
        DB::statement("
            ALTER TABLE agent_status_logs
            MODIFY started_at DATETIME NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('agent_status_logs', function (Blueprint $table) {
            // Restore original structure (not recommended, but reversible)
            $table->timestamp('started_at')->useCurrent()->useCurrentOnUpdate()->change();
            $table->timestamp('ended_at')->nullable()->change();
        });
    }
};
