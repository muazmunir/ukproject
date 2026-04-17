<?php

namespace App\Support;

/**
 * Resolves the metadata database used by db:split-multi (procedures + map tables).
 */
final class SplitMultiDb
{
    /**
     * Resolved in config/database.php (works with config:cache; do not use env() here).
     */
    public static function controlDatabaseName(): string
    {
        return (string) config('database.split_multi.control_database', 'split_control');
    }
}
