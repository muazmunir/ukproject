<?php

namespace App\Support;

/**
 * Resolves the metadata database used by db:split-multi (procedures + map tables).
 */
final class SplitMultiDb
{
    /**
     * DB_SPLIT_CONTROL_DATABASE when set; otherwise if DB_AUTH_DATABASE ends with
     * "_auth_db" (e.g. u990716838_auth_db), uses "{prefix}_split_control"; else "split_control".
     */
    public static function controlDatabaseName(): string
    {
        $raw = env('DB_SPLIT_CONTROL_DATABASE');
        if (is_string($raw) && trim($raw) !== '') {
            return trim($raw);
        }

        $authDb = (string) config('database.connections.auth_db.database');
        $suffix = '_auth_db';
        if ($authDb !== '' && str_ends_with($authDb, $suffix) && strlen($authDb) > strlen($suffix)) {
            return substr($authDb, 0, -strlen($suffix)) . '_split_control';
        }

        return 'split_control';
    }
}
