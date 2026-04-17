<?php

namespace App\Console\Commands;

use App\Support\SplitMultiDb;
use App\Support\SplitMultiSchemaPresence;
use Illuminate\Console\Command;

class SplitMultiDatabaseStatus extends Command
{
    protected $signature = 'db:split-multi:status
                            {--source= : Override monolith database name (default: config database.split_multi.monolith_database)}';

    protected $description = 'Verify each split-multi schema exists using the MySQL user configured for that Laravel connection';

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

        $this->line('Each schema is checked with the connection that carries its credentials (Hostinger: one MySQL user per database is supported).');
        $this->newLine();

        $missing = [];
        foreach ($required as $name) {
            $conn = SplitMultiSchemaPresence::connectionForSchema($source, $control, $name);
            $user = (string) config("database.connections.{$conn}.username");
            $result = SplitMultiSchemaPresence::schemaVisibility($conn, $name);
            $ok = $result['visible'];
            if (! $ok) {
                $missing[] = $name;
            }
            $tag = $ok ? '<fg=green>OK</>' : '<fg=red>MISS</>';
            $this->line(sprintf('  %s  %-38s  %-14s  %s', $tag, $name, $conn, $user));
            if ($result['error'] !== null) {
                $this->line('       '.$result['error']);
            }
        }

        $this->newLine();
        $this->line('db:split-multi: (1) apply SQL as split_control (or DB_SPLIT_CLI_*); (2) CALL procedures as <fg=cyan>DB_SPLIT_CALL_USERNAME</> or <fg=cyan>DB_USERNAME</> — that second user must read the monolith and write every split DB (add them in hPanel to all databases).');

        if ($missing === []) {
            $this->newLine();
            $this->info('All required schemas are visible to their connection users; the db:split-multi pre-check should pass.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('Missing or inaccessible: '.implode(', ', $missing));

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
}
