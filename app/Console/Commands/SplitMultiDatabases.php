<?php

namespace App\Console\Commands;

use App\Console\Concerns\FindsMysqlClient;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class SplitMultiDatabases extends Command
{
    use FindsMysqlClient;

    private const PLACEHOLDER = '__SPLIT_SOURCE__';

    protected $signature = 'db:split-multi
                            {--source= : Monolith MySQL database name (default: DB_SPLIT_SOURCE or DB_DATABASE)}
                            {--mysql= : Full path to mysql client binary}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Run multi-database split: copy tables from monolith into domain DBs (monolith unchanged) + auth_db views';

    public function handle(): int
    {
        $source = $this->option('source')
            ?: env('DB_SPLIT_SOURCE')
            ?: env('DB_DATABASE');

        if (! is_string($source) || $source === '' || ! preg_match('/^[a-zA-Z0-9_]+$/', $source)) {
            $this->error('Invalid or missing source database. Use --source=my_db or set DB_SPLIT_SOURCE / DB_DATABASE (letters, digits, underscore only).');

            return self::FAILURE;
        }

        $sqlPath = database_path('scripts/split-databases.sql');
        if (! is_readable($sqlPath)) {
            $this->error("Cannot read: {$sqlPath}");

            return self::FAILURE;
        }

        $sql = file_get_contents($sqlPath);
        if ($sql === false || ! str_contains($sql, self::PLACEHOLDER)) {
            $this->error('split-databases.sql must contain the placeholder ' . self::PLACEHOLDER . ' for the monolith database name.');

            return self::FAILURE;
        }

        $sql = str_replace(self::PLACEHOLDER, $source, $sql);

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
            $this->warn('This creates domain DBs + split_control, COPIES each mapped table from the monolith (original DB unchanged), then creates compatibility views on auth_db. Take a backup first.');
            if (! $this->confirm('Continue?', false)) {
                return self::FAILURE;
            }
        }

        $host = (string) env('DB_HOST', '127.0.0.1');
        $port = (string) env('DB_PORT', '3306');
        $user = (string) env('DB_USERNAME', 'root');
        $password = env('DB_PASSWORD');
        $password = $password === null ? '' : (string) $password;

        $args = array_merge(
            [$mysql, '-h', $host, '-P', $port, '-u', $user],
            $password !== '' ? ['-p' . $password] : [],
            ['-D', $source]
        );

        $this->info('Applying database/scripts/split-databases.sql …');
        $proc = new Process($args, base_path(), null, $sql, 600.0);
        $proc->run();
        if (! $proc->isSuccessful()) {
            $this->error(trim($proc->getErrorOutput() . "\n" . $proc->getOutput()));

            return self::FAILURE;
        }

        $this->info('Calling copy_mapped_tables() and create_compat_views() on split_control …');
        $call = new Process(
            array_merge(
                [$mysql, '-h', $host, '-P', $port, '-u', $user],
                $password !== '' ? ['-p' . $password] : [],
                ['-D', 'split_control', '-e', 'CALL copy_mapped_tables(); CALL create_compat_views();']
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
}
