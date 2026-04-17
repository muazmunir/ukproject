<?php

namespace App\Console\Commands;

use App\Console\Concerns\FindsMysqlClient;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DropSplitMultiDatabases extends Command
{
    use FindsMysqlClient;

    protected $signature = 'db:split-multi:drop
                            {--source= : Monolith database where split helper tables/procedures live (default: DB_SPLIT_SOURCE or DB_DATABASE)}
                            {--mysql= : Full path to mysql client binary}
                            {--force : Required — permanently drops split databases}';

    protected $description = 'Drop multi-split databases (auth/pii/kyc/payments/app/comms/media/audit) and split helper objects on the monolith';

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

        foreach ($targets as $name) {
            if (! preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                $this->error("Invalid database name in config: {$name}");

                return self::FAILURE;
            }
        }

        $source = $this->option('source')
            ?: env('DB_SPLIT_SOURCE')
            ?: env('DB_DATABASE');

        if (! is_string($source) || $source === '' || ! preg_match('/^[a-zA-Z0-9_]+$/', $source)) {
            $this->error('Invalid or missing monolith database. Use --source=my_db or set DB_SPLIT_SOURCE / DB_DATABASE.');

            return self::FAILURE;
        }

        if (in_array($source, $targets, true)) {
            $this->error('Monolith database name matches a split target in .env — refusing to drop.');

            return self::FAILURE;
        }

        $mysql = $this->findMysqlBinary((string) $this->option('mysql'));
        if ($mysql === null) {
            $this->error('mysql client not found. Add mysql to PATH or pass --mysql=C:\\path\\to\\mysql.exe');

            return self::FAILURE;
        }

        $this->warn('This will DROP these databases (all data inside is lost):');
        foreach ($targets as $db) {
            $this->line('  - ' . $db);
        }
        $this->line('');
        $this->warn("Plus on `{$source}`: split helper tables + procedures (move_mapped_tables, create_compat_views).");

        if ($this->input->isInteractive() && ! $this->confirm('Really drop these databases? This cannot be undone.', false)) {
            return self::FAILURE;
        }

        $host = (string) env('DB_HOST', '127.0.0.1');
        $port = (string) env('DB_PORT', '3306');
        $user = (string) env('DB_USERNAME', 'root');
        $password = env('DB_PASSWORD');
        $password = $password === null ? '' : (string) $password;

        $baseArgs = array_merge(
            [$mysql, '-h', $host, '-P', $port, '-u', $user],
            $password !== '' ? ['-p' . $password] : []
        );

        $drops = 'SET FOREIGN_KEY_CHECKS=0;';
        foreach ($targets as $db) {
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

        $clean = sprintf(
            'DROP PROCEDURE IF EXISTS move_mapped_tables; DROP PROCEDURE IF EXISTS create_compat_views; '
            . 'DROP TABLE IF EXISTS `%s`.`_split_multidb_table_map`; DROP TABLE IF EXISTS `%s`.`_split_multidb_auth_tables`;',
            str_replace('`', '``', $source),
            str_replace('`', '``', $source)
        );

        $this->info("Cleaning split helpers on `{$source}` …");
        $proc2 = new Process(
            array_merge($baseArgs, ['-D', $source, '-e', $clean]),
            base_path(),
            null,
            null,
            120.0
        );
        $proc2->run();
        if (! $proc2->isSuccessful()) {
            $this->error(trim($proc2->getErrorOutput() . "\n" . $proc2->getOutput()));

            return self::FAILURE;
        }

        $this->info('Done. Point DB_TOPOLOGY=single at your monolith (or restore from backup) and run: php artisan config:clear');

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
