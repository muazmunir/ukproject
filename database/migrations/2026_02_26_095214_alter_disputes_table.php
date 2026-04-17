<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            // ✅ Support-style ownership fields
            if (!Schema::hasColumn('disputes', 'assigned_staff_id')) {
                $table->unsignedBigInteger('assigned_staff_id')->nullable()->index()
                    ->after('assigned_admin_id');
            }

            if (!Schema::hasColumn('disputes', 'assigned_staff_role')) {
                $table->string('assigned_staff_role', 20)->nullable()->index()
                    ->after('assigned_staff_id'); // 'admin'|'manager'
            }

            // ✅ Support-style SLA timer start (0m starts here)
            if (!Schema::hasColumn('disputes', 'sla_started_at')) {
                $table->timestamp('sla_started_at')->nullable()->index()
                    ->after('assigned_at');
            }
        });

        // Optional backfill: if you already have assigned_admin_id, mirror it into assigned_staff_id
        // (This is safe and helps old records)
        DB::table('disputes')
            ->whereNull('assigned_staff_id')
            ->whereNotNull('assigned_admin_id')
            ->update([
                'assigned_staff_id'   => DB::raw('assigned_admin_id'),
                'assigned_staff_role' => DB::raw("'admin'"),
                'sla_started_at'      => DB::raw('COALESCE(assigned_at, NOW())'),
            ]);
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            if (Schema::hasColumn('disputes', 'assigned_staff_id')) {
                $table->dropIndex(['assigned_staff_id']);
                $table->dropColumn('assigned_staff_id');
            }

            if (Schema::hasColumn('disputes', 'assigned_staff_role')) {
                $table->dropIndex(['assigned_staff_role']);
                $table->dropColumn('assigned_staff_role');
            }

            if (Schema::hasColumn('disputes', 'sla_started_at')) {
                $table->dropIndex(['sla_started_at']);
                $table->dropColumn('sla_started_at');
            }
        });
    }
};