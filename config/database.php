<?php

use Illuminate\Support\Str;

$dbTopology = env('DB_TOPOLOGY', 'single');
$singleDatabase = env('DB_DATABASE', 'laravel');
$monolithSchemaName = (string) (env('DB_SPLIT_SOURCE') ?: env('DB_DATABASE', 'laravel'));

/*
| When monolith is Hostinger-style (e.g. u990716838_zaivias) and DB_*_DATABASE is still the short
| default (auth_db, pii_db, …), expand to u990716838_auth_db so split + multi connections work without
| duplicating prefixes in .env. Explicit non-default names in .env are always respected.
*/
$monolithForSplitPrefix = (string) (env('DB_SPLIT_SOURCE') ?: env('DB_DATABASE', ''));
$hostingerSplitPrefix = null;
foreach ([$monolithForSplitPrefix, (string) env('DB_USERNAME', '')] as $hostingerPrefixCandidate) {
    if ($hostingerPrefixCandidate !== '' && preg_match('/^(u\d+)_/', $hostingerPrefixCandidate, $hostingerSplitPrefixMatch)) {
        $hostingerSplitPrefix = $hostingerSplitPrefixMatch[1] . '_';

        break;
    }
}
$resolveHostingerSplitSchema = static function (string $envKey, string $shortDefault) use ($hostingerSplitPrefix): string {
    $explicit = env($envKey);
    if ($explicit !== null && $explicit !== '' && $explicit !== $shortDefault) {
        return (string) $explicit;
    }
    if ($hostingerSplitPrefix !== null && ($explicit === null || $explicit === '' || $explicit === $shortDefault)) {
        return $hostingerSplitPrefix . $shortDefault;
    }

    return ($explicit !== null && $explicit !== '') ? (string) $explicit : $shortDefault;
};

$resolvedAuthDatabase = $resolveHostingerSplitSchema('DB_AUTH_DATABASE', 'auth_db');
$multiEntryDatabase = $resolveHostingerSplitSchema('DB_DATABASE_MULTI_ENTRY', 'auth_db');
$activeMysqlDatabase = $dbTopology === 'multi' ? $multiEntryDatabase : $singleDatabase;

$resolvedSplitControlDatabase = (static function () use ($resolvedAuthDatabase, $hostingerSplitPrefix): string {
    $raw = env('DB_SPLIT_CONTROL_DATABASE');
    if (is_string($raw) && trim($raw) !== '') {
        return trim($raw);
    }

    $suffix = '_auth_db';
    if ($resolvedAuthDatabase !== '' && str_ends_with($resolvedAuthDatabase, $suffix) && strlen($resolvedAuthDatabase) > strlen($suffix)) {
        return substr($resolvedAuthDatabase, 0, -strlen($suffix)) . '_split_control';
    }

    if ($hostingerSplitPrefix !== null) {
        return $hostingerSplitPrefix . 'split_control';
    }

    return 'split_control';
})();

$resolvedPiiDatabase = $resolveHostingerSplitSchema('DB_PII_DATABASE', 'pii_db');
$resolvedKycDatabase = $resolveHostingerSplitSchema('DB_KYC_DATABASE', 'kyc_db');
$resolvedPaymentsDatabase = $resolveHostingerSplitSchema('DB_PAYMENTS_DATABASE', 'payments_db');
$resolvedAppDatabase = $resolveHostingerSplitSchema('DB_APP_DATABASE', 'app_db');
$resolvedCommsDatabase = $resolveHostingerSplitSchema('DB_COMMS_DATABASE', 'comms_db');
$resolvedMediaDatabase = $resolveHostingerSplitSchema('DB_MEDIA_DATABASE', 'media_db');
$resolvedAuditDatabase = $resolveHostingerSplitSchema('DB_AUDIT_DATABASE', 'audit_db');

/*
| Empty DB_*_PASSWORD in .env is treated as "use DB_PASSWORD" (same admin password everywhere).
| When DB_*_USERNAME is empty and the schema name is Hostinger-style (same prefix as monolith user),
| default the MySQL login name to the full schema name (hPanel often creates user = database name).
*/
$inheritMysqlPassword = static function (string $specificPasswordEnvKey): string {
    $v = env($specificPasswordEnvKey);
    if (is_string($v) && $v !== '') {
        return $v;
    }
    $main = env('DB_PASSWORD');

    return $main === null ? '' : (string) $main;
};

$hostingerMysqlUserForDatabase = static function (string $specificUsernameEnvKey, string $databaseName) use ($hostingerSplitPrefix): string {
    $explicit = env($specificUsernameEnvKey);
    if (is_string($explicit) && trim($explicit) !== '') {
        return trim($explicit);
    }
    if ($hostingerSplitPrefix !== null && $databaseName !== '' && str_starts_with($databaseName, $hostingerSplitPrefix)) {
        return $databaseName;
    }

    return (string) env('DB_USERNAME', 'root');
};

/*
| In multi topology the default `mysql` connection uses DB_DATABASE_MULTI_ENTRY (usually auth_db).
| Hostinger uses a different MySQL user per schema, so match username/password to that entry DB.
*/
$defaultMysqlUsername = (string) env('DB_USERNAME', 'root');
$defaultMysqlPassword = env('DB_PASSWORD') === null ? '' : (string) env('DB_PASSWORD');

if ($dbTopology === 'multi') {
    $entryDb = $multiEntryDatabase;
    foreach (
        [
            [$resolvedAuthDatabase, 'DB_AUTH_USERNAME', 'DB_AUTH_PASSWORD'],
            [$resolvedPiiDatabase, 'DB_PII_USERNAME', 'DB_PII_PASSWORD'],
            [$resolvedKycDatabase, 'DB_KYC_USERNAME', 'DB_KYC_PASSWORD'],
            [$resolvedPaymentsDatabase, 'DB_PAYMENTS_USERNAME', 'DB_PAYMENTS_PASSWORD'],
            [$resolvedAppDatabase, 'DB_APP_USERNAME', 'DB_APP_PASSWORD'],
            [$resolvedCommsDatabase, 'DB_COMMS_USERNAME', 'DB_COMMS_PASSWORD'],
            [$resolvedMediaDatabase, 'DB_MEDIA_USERNAME', 'DB_MEDIA_PASSWORD'],
            [$resolvedAuditDatabase, 'DB_AUDIT_USERNAME', 'DB_AUDIT_PASSWORD'],
        ] as [$schema, $userEnvKey, $passEnvKey]
    ) {
        if ($entryDb === $schema) {
            $defaultMysqlUsername = $hostingerMysqlUserForDatabase($userEnvKey, $schema);
            $defaultMysqlPassword = $inheritMysqlPassword($passEnvKey);

            break;
        }
    }
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    | Metadata DB for db:split-multi (procedures + map tables). Prefer DB_SPLIT_CONTROL_DATABASE;
    | otherwise inferred from auth DB name or Hostinger-style u123…_ prefix (monolith, else DB_USERNAME).
    */
    'split_multi' => [
        'control_database' => $resolvedSplitControlDatabase,
        'monolith_database' => $monolithSchemaName,
        'topology' => $dbTopology,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        /*
        | Read-only style: always DB_USERNAME / DB_PASSWORD on the monolith schema (DB_SPLIT_SOURCE ?: DB_DATABASE).
        | Used by db:split-multi:status / presence checks — not the default `mysql` connection, which in multi mode
        | may point at auth_db with per-domain credentials.
        */
        'monolith' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $monolithSchemaName,
            'username' => (string) env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD') === null ? '' : (string) env('DB_PASSWORD'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'timezone' => '+00:00',
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $activeMysqlDatabase,
            'username' => $defaultMysqlUsername,
            'password' => $defaultMysqlPassword,
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'timezone' => '+00:00',
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        /*
        | Split metadata DB (db:split-multi). On Hostinger the DB often has its own user matching the DB name;
        | set DB_SPLIT_CONTROL_USERNAME / DB_SPLIT_CONTROL_PASSWORD when it differs from DB_USERNAME.
        */
        'split_control' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $resolvedSplitControlDatabase,
            'username' => $hostingerMysqlUserForDatabase('DB_SPLIT_CONTROL_USERNAME', $resolvedSplitControlDatabase),
            'password' => $inheritMysqlPassword('DB_SPLIT_CONTROL_PASSWORD'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'timezone' => '+00:00',
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'auth_db' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $resolvedAuthDatabase,
            'username' => $hostingerMysqlUserForDatabase('DB_AUTH_USERNAME', $resolvedAuthDatabase),
            'password' => $inheritMysqlPassword('DB_AUTH_PASSWORD'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pii_db' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $resolvedPiiDatabase,
            'username' => $hostingerMysqlUserForDatabase('DB_PII_USERNAME', $resolvedPiiDatabase),
            'password' => $inheritMysqlPassword('DB_PII_PASSWORD'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'kyc_db' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $resolvedKycDatabase,
            'username' => $hostingerMysqlUserForDatabase('DB_KYC_USERNAME', $resolvedKycDatabase),
            'password' => $inheritMysqlPassword('DB_KYC_PASSWORD'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'payments_db' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $resolvedPaymentsDatabase,
            'username' => $hostingerMysqlUserForDatabase('DB_PAYMENTS_USERNAME', $resolvedPaymentsDatabase),
            'password' => $inheritMysqlPassword('DB_PAYMENTS_PASSWORD'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'app_db' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $resolvedAppDatabase,
            'username' => $hostingerMysqlUserForDatabase('DB_APP_USERNAME', $resolvedAppDatabase),
            'password' => $inheritMysqlPassword('DB_APP_PASSWORD'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'comms_db' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $resolvedCommsDatabase,
            'username' => $hostingerMysqlUserForDatabase('DB_COMMS_USERNAME', $resolvedCommsDatabase),
            'password' => $inheritMysqlPassword('DB_COMMS_PASSWORD'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'media_db' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $resolvedMediaDatabase,
            'username' => $hostingerMysqlUserForDatabase('DB_MEDIA_USERNAME', $resolvedMediaDatabase),
            'password' => $inheritMysqlPassword('DB_MEDIA_PASSWORD'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'audit_db' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $resolvedAuditDatabase,
            'username' => $hostingerMysqlUserForDatabase('DB_AUDIT_USERNAME', $resolvedAuditDatabase),
            'password' => $inheritMysqlPassword('DB_AUDIT_PASSWORD'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
