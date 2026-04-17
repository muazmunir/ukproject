<?php

namespace App\Console\Commands;

use App\Support\SplitMultiDb;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SplitMultiDatabaseStatus extends Command
{
    protected $signature = 'db:split-multi:status
                            {--source= : Override monolith database name (default: config database.split_multi.monolith_database)}';

    protected $description = 'List MySQL schemas your app user can see and compare to db:split-multi requirements';

    public function handle(): int
    {
        $source = $this->option('source')
            ?: config('database.split_multi.monolith_database');

        if (! is_string($source) || $source === '' || ! preg_match('/^[a-zA-Z0-9_]+$/', $source)) {
            $this->error('Invalid or missing monolith name. Set DB_DATABASE / DB_SPLIT_SOURCE or pass --source=');

            return self::FAILURE;
        }

        $control = SplitMultiDb::controlDatabaseName();
        $domainDbs = $this->domainDatabaseNames();
        $required = array_values(array_unique(array_merge([$source, $control], $domainDbs)));

        $username = (string) config('database.connections.mysql.username');
        $host = (string) config('database.connections.mysql.host');

        $this->line("Default connection: mysql  user=<fg=cyan>{$username}</>  host=<fg=cyan>{$host}</>");
        $this->newLine();

        try {
            $visible = $this->visibleMysqlSchemaNames();
        } catch (\Throwable $e) {
            $this->error('Could not run SHOW DATABASES: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Schemas this user can see (SHOW DATABASES):');
        foreach ($visible as $name) {
            $this->line('  '.$name);
        }
        $this->newLine();

        $this->warn('Required for db:split-multi:');
        foreach ($required as $name) {
            $ok = in_array($name, $visible, true);
            $this->line($ok ? "  <fg=green>OK</>   {$name}" : "  <fg=red>MISS</> {$name}");
        }
        $this->newLine();

        $missing = array_values(array_diff($required, $visible));
        if ($missing === []) {
            $this->info('All required schemas are visible; db:split-multi pre-check should pass.');

            return self::SUCCESS;
        }

        $this->error('Missing or no privilege: '.implode(', ', $missing));
        $this->line('Create each database in hPanel and assign the same MySQL user ('.$username.') to every database, or stay on DB_TOPOLOGY=single if your plan does not allow enough databases.');

        return self::FAILURE;
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
     * @return list<string>
     */
    private function visibleMysqlSchemaNames(): array
    {
        $rows = DB::connection('mysql')->select('SHOW DATABASES');
        $names = [];
        foreach ($rows as $row) {
            $names[] = (string) reset((array) $row);
        }
        sort($names);

        return array_values(array_unique($names));
    }
}
