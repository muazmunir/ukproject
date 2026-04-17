<?php

namespace App\Console\Concerns;

/**
 * Builds mysql CLI argv for shared hosts where "localhost" resolves to ::1
 * but grants exist only for user@127.0.0.1 or socket.
 */
trait BuildsMysqlCliConnection
{
    /**
     * Host / port / socket argv for a named Laravel connection (uses that connection’s config, not only .env defaults).
     *
     * @return list<string>
     */
    protected function mysqlCliNetworkArgvForConnection(string $connectionName): array
    {
        $override = trim((string) ($this->option('db-host') ?? ''));

        if ($override !== '') {
            $c = config("database.connections.{$connectionName}");

            return ['-h', $override, '-P', (string) ($c['port'] ?? env('DB_PORT', '3306'))];
        }

        $c = config("database.connections.{$connectionName}");
        $socket = $c['unix_socket'] ?? '';
        if (is_string($socket) && $socket !== '') {
            return ['--protocol=SOCKET', '-S', $socket];
        }

        $host = (string) ($c['host'] ?? '127.0.0.1');
        $lower = strtolower($host);
        if ($lower === 'localhost' || $lower === '::1') {
            $host = '127.0.0.1';
        }

        return ['-h', $host, '-P', (string) ($c['port'] ?? '3306')];
    }

    /**
     * @return list<string> fragments placed after the mysql binary and before -u
     */
    protected function mysqlCliHostAndPortArgv(): array
    {
        $override = trim((string) ($this->option('db-host') ?? ''));

        if ($override !== '') {
            return ['-h', $override, '-P', (string) env('DB_PORT', '3306')];
        }

        $socket = env('DB_SOCKET');
        if (is_string($socket) && $socket !== '') {
            return ['--protocol=SOCKET', '-S', $socket];
        }

        $host = (string) env('DB_HOST', '127.0.0.1');
        $lower = strtolower($host);
        if ($lower === 'localhost' || $lower === '::1') {
            $host = '127.0.0.1';
        }

        return ['-h', $host, '-P', (string) env('DB_PORT', '3306')];
    }

    /**
     * mysql CLI credentials for db:split-multi / drop.
     *
     * When DB_SPLIT_CLI_USERNAME is set (non-empty), it wins; password uses DB_SPLIT_CLI_PASSWORD or DB_PASSWORD.
     *
     * When DB_SPLIT_CLI_USERNAME is empty:
     * - $defaultToSplitControlCredentials true (db:split-multi **apply SQL only**): use the `split_control`
     *   connection user so CREATE PROCEDURE can run in that schema (admin often cannot USE split_control).
     * - false (db:split-multi:drop): use DB_USERNAME / DB_PASSWORD.
     *
     * **CALL copy_mapped_tables()** uses mysqlCliSplitProcedureCallCredentials() instead — the invoker
     * must read the monolith and write every target DB (usually DB_USERNAME after hPanel grants on all DBs).
     *
     * @return array{0: string, 1: string}
     */
    protected function mysqlCliCredentials(bool $defaultToSplitControlCredentials = false): array
    {
        $explicitUser = env('DB_SPLIT_CLI_USERNAME');
        if (is_string($explicitUser) && trim($explicitUser) !== '') {
            $user = trim($explicitUser);
            $cliPass = env('DB_SPLIT_CLI_PASSWORD');
            if (! is_string($cliPass) || $cliPass === '') {
                $cliPass = env('DB_PASSWORD');
            }

            return [$user, $cliPass === null ? '' : (string) $cliPass];
        }

        if ($defaultToSplitControlCredentials) {
            return [
                (string) config('database.connections.split_control.username'),
                (string) config('database.connections.split_control.password'),
            ];
        }

        $mainPass = env('DB_PASSWORD');

        return [(string) env('DB_USERNAME', 'root'), $mainPass === null ? '' : (string) $mainPass];
    }

    /**
     * mysql CLI user for CALL copy_mapped_tables() / create_compat_views() (procedure **invoker**).
     * Must: USE metadata DB, SELECT from monolith tables, CREATE/INSERT into every domain DB.
     * Defaults to DB_SPLIT_CALL_USERNAME or DB_USERNAME; password DB_SPLIT_CALL_PASSWORD or DB_PASSWORD.
     *
     * @return array{0: string, 1: string}
     */
    protected function mysqlCliSplitProcedureCallCredentials(): array
    {
        $user = env('DB_SPLIT_CALL_USERNAME');
        if (! is_string($user) || trim($user) === '') {
            $user = (string) env('DB_USERNAME', 'root');
        } else {
            $user = trim($user);
        }

        $pass = env('DB_SPLIT_CALL_PASSWORD');
        if (! is_string($pass) || $pass === '') {
            $pass = env('DB_PASSWORD');
        }

        return [$user, $pass === null ? '' : (string) $pass];
    }
}
