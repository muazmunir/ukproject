<?php

namespace App\Console\Commands;

use App\Console\Concerns\BuildsMysqlCliConnection;
use App\Console\Concerns\FindsMysqlClient;
use App\Support\SplitMultiDb;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class SplitMultiDatabases extends Command
{
    use BuildsMysqlCliConnection;
    use FindsMysqlClient;

    private const PLACEHOLDER_SOURCE = '__SPLIT_SOURCE__';

    private const PLACEHOLDER_CONTROL = '__SPLIT_CONTROL__';

    protected $signature = 'db:split-multi
                            {--source= : Monolith MySQL database name (default: DB_SPLIT_SOURCE or DB_DATABASE)}
                            {--db-host= : mysql CLI host override (use 127.0.0.1 if localhost gives Access denied on ::1)}
                            {--mysql= : Full path to mysql client binary}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Run multi-database split: copy tables from monolith into pre-created domain DBs (monolith unchanged) + auth_db views';

    public function handle(): int
    {
        $source = $this->option('source')
            ?: env('DB_SPLIT_SOURCE')
            ?: env('DB_DATABASE');

        if (! is_string($source) || $source === '' || ! preg_match('/^[a-zA-Z0-9_]+$/', $source)) {
            $this->error('Invalid or missing source database. Use --source=my_db or set DB_SPLIT_SOURCE / DB_DATABASE (letters, digits, underscore only).');

            return self::FAILURE;
        }

        $control = SplitMultiDb::controlDatabaseName();
        if ($control === '' || ! preg_match('/^[a-zA-Z0-9_]+$/', $control)) {
            $this->error('Invalid DB_SPLIT_CONTROL_DATABASE in .env (letters, digits, underscore only).');

            return self::FAILURE;
        }

        if ($source === $control) {
            $this->error('DB_SPLIT_CONTROL_DATABASE must be a different database than the monolith (DB_SPLIT_SOURCE / DB_DATABASE).');

            return self::FAILURE;
        }

        $domainDbs = $this->domainDatabaseNames();
        foreach ($domainDbs as $name) {
            if (! preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                $this->error("Invalid database name in config: {$name}");

                return self::FAILURE;
            }
        }

        if (! $this->assertSplitDatabasesExist($source, $control, $domainDbs)) {
            return self::FAILURE;
        }

        $sqlPath = database_path('scripts/split-databases.sql');
        if (! is_readable($sqlPath)) {
            $this->error("Cannot read: {$sqlPath}");

            return self::FAILURE;
        }

        $sql = file_get_contents($sqlPath);
        if ($sql === false || ! str_contains($sql, self::PLACEHOLDER_SOURCE) || ! str_contains($sql, self::PLACEHOLDER_CONTROL)) {
            $this->error('split-databases.sql is missing required placeholders.');

            return self::FAILURE;
        }

        $sql = $this->applySplitSqlReplacements($sql, $source, $control);

        $mysql = $this->findMysqlBinary((string) $this->option('mysql'));
        if ($mysql === null) {
            $this->error('mysql client not found. Add mysql to PATH or pass --mysql=C:\\path\\to\\mysql.exe');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            if (! $this->input->isInteractive()) {
                $this->error('Non-interactive mode requires --force (after backup). Example: php artisan db:split-multi --force');

                return self::FAILURE;
            }
            $this->warn('This fills pre-created empty databases (see .env), COPIES tables from the monolith (original unchanged), then creates compatibility views on the auth DB. Take a backup first.');
            if (! $this->confirm('Continue?', false)) {
                return self::FAILURE;
            }
        }

        [$user, $password] = $this->mysqlCliCredentials();

        $args = array_merge(
            [$mysql],
            $this->mysqlCliHostAndPortArgv(),
            ['-u', $user],
            $password !== '' ? ['-p' . $password] : [],
            ['-D', $control]
        );

        $this->info('Applying database/scripts/split-databases.sql …');
        $proc = new Process($args, base_path(), null, $sql, 600.0);
        $proc->run();
        if (! $proc->isSuccessful()) {
            $this->error(trim($proc->getErrorOutput() . "\n" . $proc->getOutput()));

            return self::FAILURE;
        }

        $this->info("Calling copy_mapped_tables() and create_compat_views() on `{$control}` …");
        $call = new Process(
            array_merge(
                [$mysql],
                $this->mysqlCliHostAndPortArgv(),
                ['-u', $user],
                $password !== '' ? ['-p' . $password] : [],
                ['-D', $control, '-e', 'CALL copy_mapped_tables(); CALL create_compat_views();']
            ),
            base_path(),
            null,
            null,
            600.0
        );
        $call->run();
        if (! $call->isSuccessful()) {
            $this->error(trim($call->getErrorOutput() . "\n" . $call->getOutput()));

            return self::FAILURE;
        }

        $this->info('Split finished. Set DB_TOPOLOGY=multi in .env if needed, then: php artisan config:clear');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $domainDbs
     */
    private function assertSplitDatabasesExist(string $source, string $control, array $domainDbs): bool
    {
        $required = array_values(array_unique(array_merge([$source, $control], $domainDbs)));
        $placeholders = implode(',', array_fill(0, count($required), '?'));
        $rows = DB::select(
            'SELECT SCHEMA_NAME AS n FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN (' . $placeholders . ')',
            $required
        );
        $found = array_map(static fn ($r) => $r->n, $rows);
        $missing = array_values(array_diff($required, $found));
        if ($missing !== []) {
            $this->error('These MySQL databases do not exist yet (or names in .env do not match the server).');
            $hints = $this->splitDatabaseEnvHints($source, $control);
            foreach ($missing as $m) {
                $hint = $hints[$m] ?? null;
                $this->line($hint !== null ? "  - {$m}  ← {$hint}" : "  - {$m}");
            }
            $this->newLine();
            $this->line('Hostinger / shared hosting: hPanel → Databases → MySQL databases → create each name above as an empty database, assign the same MySQL user (e.g. All Privileges).');
            $this->line('Override with DB_SPLIT_CONTROL_DATABASE=your_empty_metadata_db in .env if the default name is wrong, then php artisan config:clear.');

            return false;
        }

        return true;
    }

    /**
     * @return array<string, string> schema name => .env hint
     */
    private function splitDatabaseEnvHints(string $source, string $control): array
    {
        $hints = [
            $source => 'monolith: must already exist (DB_DATABASE or DB_SPLIT_SOURCE when splitting)',
            $control => 'empty metadata DB: DB_SPLIT_CONTROL_DATABASE',
        ];

        $pairs = [
            'auth_db' => 'DB_AUTH_DATABASE',
            'pii_db' => 'DB_PII_DATABASE',
            'kyc_db' => 'DB_KYC_DATABASE',
            'payments_db' => 'DB_PAYMENTS_DATABASE',
            'app_db' => 'DB_APP_DATABASE',
            'comms_db' => 'DB_COMMS_DATABASE',
            'media_db' => 'DB_MEDIA_DATABASE',
            'audit_db' => 'DB_AUDIT_DATABASE',
        ];
        foreach ($pairs as $conn => $envKey) {
            $name = (string) config("database.connections.{$conn}.database");
            if ($name !== '') {
                $hints[$name] = "empty domain DB: {$envKey}";
            }
        }

        return $hints;
    }

    /**
     * @return list<string>
     */
    private function domainDatabaseNames(): array
    {
        $keys = ['auth_db', 'pii_db', 'kyc_db', 'payments_db', 'app_db', 'comms_db', 'media_db', 'audit_db'];
        $names = [];
        foreach ($keys as $key) {
            $db = config("database.connections.{$key}.database");
            if (is_string($db) && $db !== '') {
                $names[] = $db;
            }
        }

        return array_values(array_unique($names));
    }

    private function applySplitSqlReplacements(string $sql, string $source, string $control): string
    {
        $map = [
            self::PLACEHOLDER_SOURCE => $source,
            self::PLACEHOLDER_CONTROL => $control,
            '__AUTH_DB__' => (string) config('database.connections.auth_db.database'),
            '__PII_DB__' => (string) config('database.connections.pii_db.database'),
            '__KYC_DB__' => (string) config('database.connections.kyc_db.database'),
            '__PAYMENTS_DB__' => (string) config('database.connections.payments_db.database'),
            '__APP_DB__' => (string) config('database.connections.app_db.database'),
            '__COMMS_DB__' => (string) config('database.connections.comms_db.database'),
            '__MEDIA_DB__' => (string) config('database.connections.media_db.database'),
            '__AUDIT_DB__' => (string) config('database.connections.audit_db.database'),
        ];

        foreach ($map as $token => $value) {
            $sql = str_replace($token, $value, $sql);
        }

        return $sql;
    }
}
