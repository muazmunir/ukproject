<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            // Add after sla_started_at if it exists; otherwise add normally
            if (!Schema::hasColumn('disputes', 'sla_total_seconds')) {
                // If your table already has sla_started_at, this placement is nice
                if (Schema::hasColumn('disputes', 'sla_started_at')) {
                    $table->unsignedBigInteger('sla_total_seconds')->default(0)->after('sla_started_at');
                } else {
                    $table->unsignedBigInteger('sla_total_seconds')->default(0);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            if (Schema::hasColumn('disputes', 'sla_total_seconds')) {
                $table->dropColumn('sla_total_seconds');
            }
        });
    }
};