<?php

namespace App\Console\Commands;

use App\Console\Concerns\BuildsMysqlCliConnection;
use App\Console\Concerns\FindsMysqlClient;
use App\Support\SplitMultiDb;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DropSplitMultiDatabases extends Command
{
    use BuildsMysqlCliConnection;
    use FindsMysqlClient;

    protected $signature = 'db:split-multi:drop
                            {--db-host= : mysql CLI host override (e.g. 127.0.0.1)}
                            {--mysql= : Full path to mysql client binary}
                            {--force : Required — permanently drops split databases}';

    protected $description = 'Drop multi-split databases (domain DBs + split_control). Does not modify the monolith.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to run without --force. This permanently deletes the split databases.');

            return self::FAILURE;
        }

        $targets = $this->splitTargetNames();
        if ($targets === []) {
            $this->error('Could not resolve split database names from config.');

            return self::FAILURE;
        }

        $control = SplitMultiDb::controlDatabaseName();
        if ($control === '' || ! preg_match('/^[a-zA-Z0-9_]+$/', $control)) {
            $this->error('Invalid DB_SPLIT_CONTROL_DATABASE in .env.');

            return self::FAILURE;
        }

        $toDrop = array_values(array_unique(array_merge([$control], $targets)));

        foreach ($toDrop as $name) {
            if (! preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                $this->error("Invalid database name: {$name}");

                return self::FAILURE;
            }
        }

        $mysql = $this->findMysqlBinary((string) $this->option('mysql'));
        if ($mysql === null) {
            $this->error('mysql client not found. Add mysql to PATH or pass --mysql=C:\\path\\to\\mysql.exe');

            return self::FAILURE;
        }

        $this->warn('This will DROP these databases (all data inside them is lost). Your monolith database is not touched:');
        foreach ($toDrop as $db) {
            $this->line('  - ' . $db);
        }

        if ($this->input->isInteractive() && ! $this->confirm('Really drop these databases? This cannot be undone.', false)) {
            return self::FAILURE;
        }

        [$user, $password] = $this->mysqlCliCredentials();

        $baseArgs = array_merge(
            [$mysql],
            $this->mysqlCliHostAndPortArgv(),
            ['-u', $user],
            $password !== '' ? ['-p' . $password] : []
        );

        $drops = 'SET FOREIGN_KEY_CHECKS=0;';
        foreach ($toDrop as $db) {
            $drops .= 'DROP DATABASE IF EXISTS `' . str_replace('`', '``', $db) . '`;';
        }
        $drops .= 'SET FOREIGN_KEY_CHECKS=1;';

        $this->info('Dropping split databases …');
        $proc = new Process(
            array_merge($baseArgs, ['-D', 'mysql', '-e', $drops]),
            base_path(),
            null,
            null,
            600.0
        );
        $proc->run();
        if (! $proc->isSuccessful()) {
            $this->error(trim($proc->getErrorOutput() . "\n" . $proc->getOutput()));

            return self::FAILURE;
        }

        $this->info('Done. Monolith unchanged. Set DB_TOPOLOGY=single if needed, then: php artisan config:clear');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function splitTargetNames(): array
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
}
