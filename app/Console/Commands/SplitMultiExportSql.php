<?php

namespace App\Console\Commands;

use App\Console\Concerns\BuildsMysqlCliConnection;
use App\Console\Concerns\FindsMysqlClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class SplitMultiExportSql extends Command
{
    use BuildsMysqlCliConnection;
    use FindsMysqlClient;

    protected $signature = 'db:split-multi:export
                            {--dir=db-split-export : Directory under storage/app for .sql files}
                            {--db-host= : mysql/mysqldump host override (e.g. 127.0.0.1)}
                            {--mysqldump= : Full path to mysqldump binary}
                            {--force : Overwrite existing .sql files}';

    protected $description = 'Export monolith tables to .sql files per target connection (then use db:split-multi:import on the server)';

    public function handle(): int
    {
        $mapPath = database_path('split_multidb_table_map.php');
        if (! is_file($mapPath)) {
            $this->error("Missing map file: {$mapPath}");

            return self::FAILURE;
        }

        /** @var array<string, string> $tableMap */
        $tableMap = require $mapPath;

        $mysqldump = $this->findMysqldumpBinary((string) $this->option('mysqldump'));
        if ($mysqldump === null) {
            $this->error('mysqldump not found. Install MySQL client tools, add to PATH, or pass --mysqldump=/full/path/to/mysqldump');

            return self::FAILURE;
        }

        $dir = trim((string) $this->option('dir'), '/');
        if ($dir === '' || str_contains($dir, '..')) {
            $this->error('Invalid --dir');

            return self::FAILURE;
        }

        $base = storage_path('app/'.$dir);
        File::ensureDirectoryExists($base);

        $monolithConn = 'monolith';
        $dbName = (string) config("database.connections.{$monolithConn}.database");
        $user = (string) config("database.connections.{$monolithConn}.username");
        $password = (string) config("database.connections.{$monolithConn}.password");

        $exported = 0;
        $skipped = 0;

        foreach ($tableMap as $table => $connKey) {
            if (! preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                continue;
            }
            if (! is_string($connKey) || ! preg_match('/^[a-zA-Z0-9_]+$/', $connKey)) {
                continue;
            }
            if (! config("database.connections.{$connKey}.database")) {
                $this->warn("Skip `{$table}`: unknown connection `{$connKey}` in config.");

                continue;
            }
            if (! Schema::connection($monolithConn)->hasTable($table)) {
                $this->line("  skip `{$table}` (not in monolith)");
                $skipped++;

                continue;
            }

            $outDir = $base.'/'.$connKey;
            File::ensureDirectoryExists($outDir);
            $outFile = $outDir.'/'.$table.'.sql';

            if (is_file($outFile) && ! $this->option('force')) {
                $this->warn("Skip `{$table}`: {$outFile} exists (use --force to overwrite).");

                continue;
            }

            $argv = array_merge(
                [$mysqldump],
                $this->mysqlCliNetworkArgvForConnection($monolithConn),
                ['-u', $user],
                $password !== '' ? ['-p'.$password] : [],
                [
                    '--single-transaction',
                    '--skip-add-locks',
                    '--set-charset',
                    '--default-character-set=utf8mb4',
                    '--no-tablespaces',
                    '--skip-comments',
                    $dbName,
                    $table,
                ]
            );

            $proc = new Process($argv, base_path(), null, null, 600.0);
            $proc->run();
            if (! $proc->isSuccessful()) {
                $this->error(trim($proc->getErrorOutput()."\n".$proc->getOutput()));

                return self::FAILURE;
            }

            File::put($outFile, $proc->getOutput());
            $this->line("  <fg=green>OK</> {$connKey}/{$table}.sql");
            $exported++;
        }

        $this->newLine();
        $this->info("Exported {$exported} table(s) to {$base}".($skipped > 0 ? " ({$skipped} skipped as missing in monolith)" : '').'.');
        $this->line('Next on this server: php artisan db:split-multi:import --dir='.$dir.' --force');

        return self::SUCCESS;
    }
}
