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
            $this->newLine();
            $this->warn('Laravel is checking THESE exact schema names (from DB_*_DATABASE / DB_SPLIT_CONTROL_DATABASE — not from DB_*_USERNAME):');
            $this->line("  Monolith:  {$source}");
            $this->line("  Metadata:  {$control}");
            foreach ($this->domainDatabaseEnvLabels() as $label => $dbName) {
                $this->line("  {$label}: {$dbName}");
            }
            $this->newLine();
            $hints = $this->splitDatabaseEnvHints($source, $control);
            foreach ($missing as $m) {
                $hint = $hints[$m] ?? null;
                $this->line($hint !== null ? "  - {$m}  ← {$hint}" : "  - {$m}");
            }
            $this->newLine();
            $this->line('If you created u990716838_auth_db but this list shows auth_db, set DB_AUTH_DATABASE=u990716838_auth_db (same for DB_PII_DATABASE, …) in .env, then php artisan config:clear.');
            $this->line('DB_AUTH_USERNAME is only the MySQL login; it does not set which database name is checked.');
            $this->newLine();
            $this->line('Hostinger: hPanel → MySQL databases → create empty databases with the names in the list above, or fix .env to match names you already created.');
            $this->line('Metadata DB: set DB_SPLIT_CONTROL_DATABASE if it should not be inferred (e.g. u990716838_split_control).');

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

    /**
     * Labels for resolved DB names (error output).
     *
     * @return array<string, string> label => database name
     */
    private function domainDatabaseEnvLabels(): array
    {
        return [
            'DB_AUTH_DATABASE' => (string) config('database.connections.auth_db.database'),
            'DB_PII_DATABASE' => (string) config('database.connections.pii_db.database'),
            'DB_KYC_DATABASE' => (string) config('database.connections.kyc_db.database'),
            'DB_PAYMENTS_DATABASE' => (string) config('database.connections.payments_db.database'),
            'DB_APP_DATABASE' => (string) config('database.connections.app_db.database'),
            'DB_COMMS_DATABASE' => (string) config('database.connections.comms_db.database'),
            'DB_MEDIA_DATABASE' => (string) config('database.connections.media_db.database'),
            'DB_AUDIT_DATABASE' => (string) config('database.connections.audit_db.database'),
        ];
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
