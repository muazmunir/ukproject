<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same intent as 2026_04_18_130000: split mode keeps `users` on auth_db while
 * `coach_profiles` (etc.) live on pii_db — MySQL FKs to `users` in pii_db break inserts.
 *
 * The earlier migration used Schema::dropForeign() inside try/catch; if Laravel's
 * inferred constraint name did not match (imported DB, renamed FK), the exception
 * was swallowed and the FK stayed. This migration drops FKs by querying
 * information_schema so the real constraint names are used.
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
                $this->dropForeignKeysForColumn($c, 'coach_profiles', $column);
            }
        }

        if (Schema::connection($c)->hasTable('visits')) {
            $this->dropForeignKeysForColumn($c, 'visits', 'user_id');
        }

        if (Schema::connection($c)->hasTable('support_conversation_reads')) {
            foreach (['support_conversation_id', 'admin_id'] as $column) {
                $this->dropForeignKeysForColumn($c, 'support_conversation_reads', $column);
            }
        }
    }

    private function dropForeignKeysForColumn(string $connection, string $table, string $column): void
    {
        $schema = Schema::connection($connection);
        $conn = $schema->getConnection();

        if ($conn->getDriverName() !== 'mysql') {
            try {
                $schema->table($table, function (Blueprint $blueprint) use ($column) {
                    $blueprint->dropForeign([$column]);
                });
            } catch (\Throwable) {
                //
            }

            return;
        }

        $database = $conn->getDatabaseName();
        $rows = $conn->select(
            <<<'SQL'
            SELECT DISTINCT kcu.CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE kcu
            INNER JOIN information_schema.TABLE_CONSTRAINTS tc
                ON tc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                AND tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                AND tc.TABLE_NAME = kcu.TABLE_NAME
            WHERE kcu.CONSTRAINT_SCHEMA = ?
                AND kcu.TABLE_NAME = ?
                AND kcu.COLUMN_NAME = ?
                AND tc.CONSTRAINT_TYPE = 'FOREIGN KEY'
            SQL,
            [$database, $table, $column]
        );

        $safeTable = str_replace('`', '``', $table);
        foreach ($rows as $row) {
            $name = $row->CONSTRAINT_NAME ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }
            $safeName = str_replace('`', '``', $name);
            $conn->statement("ALTER TABLE `{$safeTable}` DROP FOREIGN KEY `{$safeName}`");
        }
    }

    public function down(): void
    {
        //
    }
};
