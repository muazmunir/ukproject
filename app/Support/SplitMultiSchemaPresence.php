<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Resolves whether split-multi schemas exist using each connection's own credentials
 * (Hostinger often uses a different MySQL user per database).
 */
final class SplitMultiSchemaPresence
{
    /**
     * Map schema name → Laravel connection used to verify visibility.
     *
     * @return array<string, string>
     */
    public static function schemaConnectionMap(string $source, string $control): array
    {
        $map = [
            $source => 'mysql',
            $control => 'split_control',
        ];

        foreach (['auth_db', 'pii_db', 'kyc_db', 'payments_db', 'app_db', 'comms_db', 'media_db', 'audit_db'] as $conn) {
            $db = config("database.connections.{$conn}.database");
            if (is_string($db) && $db !== '') {
                $map[$db] = $conn;
            }
        }

        return $map;
    }

    public static function connectionForSchema(string $source, string $control, string $schema): string
    {
        $map = self::schemaConnectionMap($source, $control);

        return $map[$schema] ?? 'mysql';
    }

    /**
     * @return array{visible: bool, error: ?string}
     */
    public static function schemaVisibility(string $connection, string $schema): array
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $schema)) {
            return ['visible' => false, 'error' => 'invalid schema name'];
        }

        try {
            $rows = DB::connection($connection)->select(
                'SELECT SCHEMA_NAME AS n FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1',
                [$schema]
            );

            return ['visible' => count($rows) > 0, 'error' => null];
        } catch (\Throwable $e) {
            return ['visible' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  list<string>  $domainDbs
     * @return list<string>
     */
    public static function missingSchemas(string $source, string $control, array $domainDbs): array
    {
        $required = array_values(array_unique(array_merge([$source, $control], $domainDbs)));
        $missing = [];
        foreach ($required as $name) {
            $conn = self::connectionForSchema($source, $control, $name);
            if (! self::schemaVisibility($conn, $name)['visible']) {
                $missing[] = $name;
            }
        }

        return $missing;
    }
}
