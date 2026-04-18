<?php

namespace App\Support\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops MySQL FOREIGN KEY constraints by real names from information_schema.
 * Used after multi-DB split: parent tables often live on another Laravel connection/schema.
 */
final class DropsMysqlSplitForeignKeys
{
    /**
     * @param  array<string, list<string>>  $tableToColumns  e.g. ['reservations' => ['client_id', 'coach_id']]
     */
    public static function dropForTables(string $connection, array $tableToColumns): void
    {
        foreach ($tableToColumns as $table => $columns) {
            foreach ($columns as $column) {
                self::dropForColumn($connection, $table, $column);
            }
        }
    }

    public static function dropForColumn(string $connection, string $table, string $column): void
    {
        if (! Schema::connection($connection)->hasTable($table)) {
            return;
        }

        $schema = Schema::connection($connection);
        $conn = $schema->getConnection();

        if ($conn->getDriverName() !== 'mysql') {
            try {
                $schema->table($table, function (Blueprint $blueprint) use ($column): void {
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
}
