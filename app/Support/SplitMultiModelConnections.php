<?php

namespace App\Support;

/**
 * Maps Eloquent table names to Laravel DB connections for the split multi-DB layout.
 */
final class SplitMultiModelConnections
{
    /** @var array<string, string>|null */
    private static ?array $tableToConnection = null;

    /**
     * @return array<string, string> table => connection name
     */
    public static function tableMap(): array
    {
        if (self::$tableToConnection === null) {
            $path = database_path('split_multidb_table_map.php');
            self::$tableToConnection = is_file($path) ? require $path : [];
        }

        return self::$tableToConnection;
    }

    /**
     * Resolved connection name for a table, or null if not part of the split map.
     */
    public static function connectionForTable(string $table): ?string
    {
        return self::tableMap()[$table] ?? null;
    }
}
