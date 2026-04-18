<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In split mode, `users` and `support_conversations` live on other schemas (auth_db, comms_db).
 * MySQL FKs only resolve inside one database, so inserts into pii_db tables fail even when
 * the logical user_id exists on auth_db.
 */
return new class extends Migration
{
    protected function conn(): string
    {
        return 'pii_db';
    }

    public function up(): void
    {
        $c = $this->conn();

        if (Schema::connection($c)->hasTable('coach_profiles')) {
            foreach (['user_id', 'reviewed_by'] as $column) {
                $this->dropForeignColumn($c, 'coach_profiles', $column);
            }
        }

        if (Schema::connection($c)->hasTable('visits')) {
            $this->dropForeignColumn($c, 'visits', 'user_id');
        }

        if (Schema::connection($c)->hasTable('support_conversation_reads')) {
            foreach (['support_conversation_id', 'admin_id'] as $column) {
                $this->dropForeignColumn($c, 'support_conversation_reads', $column);
            }
        }
    }

    private function dropForeignColumn(string $connection, string $table, string $column): void
    {
        try {
            Schema::connection($connection)->table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });
        } catch (\Throwable) {
            // Constraint missing or non-standard name; safe to ignore.
        }
    }

    public function down(): void
    {
        // Cross-schema FKs cannot be restored from pii_db alone.
    }
};
