<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Drop ALL foreign keys that reference a specific column, regardless of FK name.
     */
    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        // If table/column doesn't exist, nothing to do
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $dbName = DB::getDatabaseName();

        // Find FK constraint name(s) for this column
        $rows = DB::select(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL",
            [$dbName, $table, $column]
        );

        foreach ($rows as $r) {
            $fk = $r->CONSTRAINT_NAME ?? null;
            if ($fk) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk}`");
            }
        }
    }

    /**
     * Drop index if exists (optional safety).
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table)) return;

        $dbName = DB::getDatabaseName();

        $rows = DB::select(
            "SELECT INDEX_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?",
            [$dbName, $table, $indexName]
        );

        if (!empty($rows)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
        }
    }

    /**
     * Rename column safely (no doctrine/dbal needed).
     * Keeps type as BIGINT UNSIGNED NULL (adjust if your ids are not unsigned).
     */
    private function renameBigintUnsignedNullable(string $table, string $from, string $to): void
    {
        if (!Schema::hasTable($table)) return;
        if (!Schema::hasColumn($table, $from)) return;
        if (Schema::hasColumn($table, $to)) return;

        // Drop FK on old column name first
        $this->dropForeignKeyIfExists($table, $from);

        // Rename via raw SQL
        DB::statement("ALTER TABLE `{$table}` CHANGE `{$from}` `{$to}` BIGINT UNSIGNED NULL");
    }

    public function up(): void
    {
        // ----------------------------
        // 1) Add new required columns
        // ----------------------------
        Schema::table('disputes', function (Blueprint $table) {
            if (!Schema::hasColumn('disputes', 'in_review_started_at')) {
                $table->timestamp('in_review_started_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('disputes', 'last_party_message_at')) {
                $table->timestamp('last_party_message_at')->nullable()->after('last_message_at');
            }
        });

        // ----------------------------
        // 2) Rename admin decision/resolution -> staff
        // (do NOT rely on doctrine/dbal)
        // ----------------------------
        $this->renameBigintUnsignedNullable('disputes', 'decided_by_admin_id', 'decided_by_staff_id');
        $this->renameBigintUnsignedNullable('disputes', 'resolved_by_admin_id', 'resolved_by_staff_id');

        // ----------------------------
        // 3) Drop legacy columns safely (FK first, then column)
        // ----------------------------
        $legacyCols = [
            'taken_by_admin_id',
            'taken_at',
            'assigned_admin_id',
        ];

        // Drop FK constraints for legacy FK cols
        $this->dropForeignKeyIfExists('disputes', 'taken_by_admin_id');
        $this->dropForeignKeyIfExists('disputes', 'assigned_admin_id');

        // Drop legacy indexes if you had any custom ones (optional)
        // $this->dropIndexIfExists('disputes', 'disputes_taken_by_admin_id_foreign'); // usually FK, not index

        Schema::table('disputes', function (Blueprint $table) use ($legacyCols) {
            foreach ($legacyCols as $col) {
                if (Schema::hasColumn('disputes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        // Reverse changes as best-effort (optional)
        // If you don’t need down, you can leave empty or throw.

        // 1) Re-add legacy columns (without FKs)
        Schema::table('disputes', function (Blueprint $table) {
            if (!Schema::hasColumn('disputes', 'taken_by_admin_id')) {
                $table->unsignedBigInteger('taken_by_admin_id')->nullable();
            }
            if (!Schema::hasColumn('disputes', 'taken_at')) {
                $table->timestamp('taken_at')->nullable();
            }
            if (!Schema::hasColumn('disputes', 'assigned_admin_id')) {
                $table->unsignedBigInteger('assigned_admin_id')->nullable();
            }
        });

        // 2) Rename staff -> admin (raw SQL)
        if (Schema::hasColumn('disputes', 'decided_by_staff_id') && !Schema::hasColumn('disputes', 'decided_by_admin_id')) {
            $this->dropForeignKeyIfExists('disputes', 'decided_by_staff_id');
            DB::statement("ALTER TABLE `disputes` CHANGE `decided_by_staff_id` `decided_by_admin_id` BIGINT UNSIGNED NULL");
        }

        if (Schema::hasColumn('disputes', 'resolved_by_staff_id') && !Schema::hasColumn('disputes', 'resolved_by_admin_id')) {
            $this->dropForeignKeyIfExists('disputes', 'resolved_by_staff_id');
            DB::statement("ALTER TABLE `disputes` CHANGE `resolved_by_staff_id` `resolved_by_admin_id` BIGINT UNSIGNED NULL");
        }

        // 3) Drop new columns
        Schema::table('disputes', function (Blueprint $table) {
            if (Schema::hasColumn('disputes', 'in_review_started_at')) {
                $table->dropColumn('in_review_started_at');
            }
            if (Schema::hasColumn('disputes', 'last_party_message_at')) {
                $table->dropColumn('last_party_message_at');
            }
        });
    }
};