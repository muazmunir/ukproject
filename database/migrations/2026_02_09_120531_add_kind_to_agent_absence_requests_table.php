<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_absence_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('agent_absence_requests', 'kind')) {
                $table->string('kind', 20)->default('absence')->after('agent_id');
            }

            // helpful indexes (safe to add; ignore if you already have them)
            $table->index(['agent_id', 'state'], 'aar_agent_state_idx');
            $table->index(['agent_id', 'kind'],  'aar_agent_kind_idx');
            $table->index(['start_at', 'end_at'], 'aar_window_idx');
        });
    }

    public function down(): void
    {
        Schema::table('agent_absence_requests', function (Blueprint $table) {
            if (Schema::hasColumn('agent_absence_requests', 'kind')) {
                $table->dropColumn('kind');
            }

            // drop indexes if exist
            try { $table->dropIndex('aar_agent_state_idx'); } catch (\Throwable $e) {}
            try { $table->dropIndex('aar_agent_kind_idx'); } catch (\Throwable $e) {}
            try { $table->dropIndex('aar_window_idx'); } catch (\Throwable $e) {}
        });
    }
};
