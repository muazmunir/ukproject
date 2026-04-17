<?php

namespace App\Console\Commands;

use App\Console\Concerns\BuildsMysqlCliConnection;
use App\Console\Concerns\FindsMysqlClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class SplitMultiImportSql extends Command
{
    use BuildsMysqlCliConnection;
    use FindsMysqlClient;

    private const CONNECTION_ORDER = ['auth_db', 'pii_db', 'kyc_db', 'payments_db', 'app_db', 'comms_db', 'media_db', 'audit_db'];

    protected $signature = 'db:split-multi:import
                            {--dir=db-split-export : Directory under storage/app that contains export subfolders}
                            {--connection= : Import only this connection folder (e.g. auth_db)}
                            {--db-host= : mysql CLI host override}
                            {--mysql= : Full path to mysql binary}
                            {--dry-run : List files that would be imported}
                            {--force : Run without confirmation (required in non-interactive mode)}';

    protected $description = 'Import .sql files from db:split-multi:export into each Laravel connection database (one DB user per file batch)';

    public function handle(): int
    {
        $dir = trim((string) $this->option('dir'), '/');
        if ($dir === '' || str_contains($dir, '..')) {
            $this->error('Invalid --dir');

            return self::FAILURE;
        }

        $base = storage_path('app/'.$dir);
        if (! is_dir($base)) {
            $this->error("Directory not found: {$base}");

            return self::FAILURE;
        }

        $onlyConn = $this->option('connection');
        if ($onlyConn !== null && $onlyConn !== '' && ! preg_match('/^[a-zA-Z0-9_]+$/', (string) $onlyConn)) {
            $this->error('Invalid --connection');

            return self::FAILURE;
        }

        $mysql = $this->findMysqlBinary((string) $this->option('mysql'));
        if ($mysql === null) {
            $this->error('mysql client not found. Add mysql to PATH or pass --mysql=');

            return self::FAILURE;
        }

        $jobs = $this->collectImportJobs($base, $onlyConn ? (string) $onlyConn : null);
        if ($jobs === []) {
            $this->warn('No .sql files found under '.$base);

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($jobs as [$connKey, $path]) {
                $this->line("  [dry-run] {$connKey} ← {$path}");
            }
            $this->info('Dry run: '.count($jobs).' file(s).');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->input->isInteractive()) {
                $this->error('Non-interactive mode requires --force (imports run against live databases).');

                return self::FAILURE;
            }
            if (! $this->confirm('Import '.count($jobs).' SQL file(s) into configured databases?', false)) {
                return self::FAILURE;
            }
        }

        foreach ($jobs as [$connKey, $path]) {
            $cfg = config("database.connections.{$connKey}");
            $db = (string) ($cfg['database'] ?? '');
            $user = (string) ($cfg['username'] ?? '');
            $password = (string) ($cfg['password'] ?? '');

            if ($db === '' || ! preg_match('/^[a-zA-Z0-9_]+$/', $db)) {
                $this->error("Invalid database for connection {$connKey}");

                return self::FAILURE;
            }

            $handle = fopen($path, 'rb');
            if ($handle === false) {
                $this->error("Cannot read: {$path}");

                return self::FAILURE;
            }

            $argv = array_merge(
                [$mysql],
                $this->mysqlCliNetworkArgvForConnection($connKey),
                ['-u', $user],
                $password !== '' ? ['-p'.$password] : [],
                ['-D', $db]
            );

            $this->line("Importing <fg=cyan>{$connKey}</> ← ".basename($path));
            $proc = new Process($argv, base_path(), null, $handle, 600.0);
            try {
                $proc->run();
            } finally {
                fclose($handle);
            }

            if (! $proc->isSuccessful()) {
                $this->error(trim($proc->getErrorOutput()."\n".$proc->getOutput()));

                return self::FAILURE;
            }
        }

        $this->info('Import finished.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: string}>  [connection key, absolute path]
     */
    private function collectImportJobs(string $base, ?string $onlyConn): array
    {
        $jobs = [];
        foreach (self::CONNECTION_ORDER as $connKey) {
            if ($onlyConn !== null && $onlyConn !== '' && $connKey !== $onlyConn) {
                continue;
            }
            $folder = $base.'/'.$connKey;
            if (! is_dir($folder)) {
                continue;
            }
            $files = File::glob($folder.'/*.sql') ?: [];
            sort($files);
            foreach ($files as $path) {
                if (is_file($path)) {
                    $jobs[] = [$connKey, $path];
                }
            }
        }

        return $jobs;
    }
}
