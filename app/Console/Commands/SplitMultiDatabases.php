<?php

namespace App\Console\Commands;

use App\Console\Concerns\BuildsMysqlCliConnection;
use App\Console\Concerns\FindsMysqlClient;
use App\Support\SplitMultiDb;
use App\Support\SplitMultiSchemaPresence;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
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
                            {--create-databases : Try CREATE DATABASE IF NOT EXISTS for missing split schemas (often blocked on shared hosting)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Run multi-database split: copy tables from monolith into pre-created domain DBs (monolith unchanged) + auth_db views';

    public function handle(): int
    {
        $source = $this->option('source')
            ?: config('database.split_multi.monolith_database');

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

        if (! $this->ensureSplitDatabasesExist($source, $control, $domainDbs)) {
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

        [$user, $password] = $this->mysqlCliCredentials(true);
        $this->line("mysql CLI user for this run: <fg=cyan>{$user}</> (set DB_SPLIT_CLI_USERNAME to override)");

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
    private function ensureSplitDatabasesExist(string $source, string $control, array $domainDbs): bool
    {
        $missing = $this->missingSplitSchemaNames($source, $control, $domainDbs);

        if ($missing !== [] && in_array($source, $missing, true)) {
            $this->error("The monolith database `{$source}` is not visible to this MySQL user (wrong DB_DATABASE, user not granted, or schema does not exist).");
            $this->line('Fix DB_DATABASE / DB_SPLIT_SOURCE and DB_USERNAME / DB_PASSWORD, or assign the user to the monolith in hPanel.');

            return false;
        }

        if ($missing !== [] && $this->option('create-databases')) {
            $this->warn('Trying CREATE DATABASE IF NOT EXISTS for missing schemas (shared hosts often deny this; use hPanel if it fails)…');
            if (! $this->tryCreateMissingMysqlSchemas($missing)) {
                return false;
            }
            $missing = $this->missingSplitSchemaNames($source, $control, $domainDbs);
        }

        if ($missing === []) {
            return true;
        }

        $this->printSplitSchemasMissingHelp($source, $control, $missing);

        return false;
    }

    /**
     * @param  list<string>  $domainDbs
     * @return list<string>
     */
    private function missingSplitSchemaNames(string $source, string $control, array $domainDbs): array
    {
        return SplitMultiSchemaPresence::missingSchemas($source, $control, $domainDbs);
    }

    /**
     * @param  list<string>  $missing
     */
    private function tryCreateMissingMysqlSchemas(array $missing): bool
    {
        $charset = (string) config('database.connections.mysql.charset', 'utf8mb4');
        $collation = (string) config('database.connections.mysql.collation', 'utf8mb4_unicode_ci');
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $charset) || ! preg_match('/^[a-zA-Z0-9_]+$/', $collation)) {
            $this->error('Invalid mysql charset/collation in config; cannot run CREATE DATABASE.');

            return false;
        }

        foreach ($missing as $name) {
            try {
                DB::connection('mysql')->unprepared(
                    'CREATE DATABASE IF NOT EXISTS `'.$name.'` CHARACTER SET '.$charset.' COLLATE '.$collation
                );
                $this->line("  Created (or already existed): `{$name}`");
            } catch (QueryException $e) {
                $this->error("CREATE DATABASE failed for `{$name}`: ".$e->getMessage());
                $this->newLine();
                $this->line('On Hostinger shared hosting you usually must create each database in hPanel (Websites → Manage → Databases), then attach your MySQL user to every database with full privileges.');

                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $missing
     */
    private function printSplitSchemasMissingHelp(string $source, string $control, array $missing): void
    {
        $this->error('These MySQL schemas are not visible using each connection’s credentials (schema missing, wrong password, or user not assigned in hPanel).');
        $this->newLine();
        $this->warn('Required names (each is checked with its Laravel connection — e.g. auth_db uses DB_AUTH_USERNAME):');
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
        if (! in_array($source, $missing, true)) {
            $this->line('Hostinger often creates a separate MySQL user per database. Set DB_AUTH_USERNAME / DB_AUTH_PASSWORD, DB_PII_USERNAME, … and DB_SPLIT_CONTROL_USERNAME / DB_SPLIT_CONTROL_PASSWORD to match hPanel (defaults fall back to DB_USERNAME).');
            $this->line('Run: php artisan db:split-multi:status — it shows which connection user sees each schema.');
            $this->newLine();
            $this->line('The mysql client uses DB_SPLIT_CLI_USERNAME if set; otherwise the split_control connection user. That account must read the monolith and write every split DB (or set DB_SPLIT_CLI_* to a power user, e.g. admin after hPanel grants on all DBs).');
            $this->newLine();
            $this->line('Hostinger plans limit how many MySQL databases you can create; this split needs the monolith plus nine extra empty schemas. If you are at the limit, upgrade the plan or stay on DB_TOPOLOGY=single.');
            $this->newLine();
            $this->line('Optional: php artisan db:split-multi --force --create-databases tries SQL CREATE DATABASE (works on many VPS installs; shared hosting often blocks it).');
            $this->newLine();
        }
        $this->line('If names in hPanel differ from this list, set DB_AUTH_DATABASE, DB_PII_DATABASE, … and DB_SPLIT_CONTROL_DATABASE in .env, then php artisan config:clear.');
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
