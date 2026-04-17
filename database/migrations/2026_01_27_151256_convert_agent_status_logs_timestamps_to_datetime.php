<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Ensure MySQL session is UTC so values stay correct
        DB::statement("SET time_zone = '+00:00'");

        Schema::table('agent_status_logs', function (Blueprint $table) {
            $table->dateTime('created_at')->nullable()->change();
            $table->dateTime('updated_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Revert back to TIMESTAMP if needed
        DB::statement("SET time_zone = '+00:00'");

        Schema::table('agent_status_logs', function (Blueprint $table) {
            $table->timestamp('created_at')->nullable()->change();
            $table->timestamp('updated_at')->nullable()->change();
        });
    }
};

