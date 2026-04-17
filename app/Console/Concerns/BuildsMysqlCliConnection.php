<?php

namespace App\Console\Concerns;

/**
 * Builds mysql CLI argv for shared hosts where "localhost" resolves to ::1
 * but grants exist only for user@127.0.0.1 or socket.
 */
trait BuildsMysqlCliConnection
{
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
     * mysql CLI user for db:split-multi / drop. Must be able to read the monolith and write all split DBs.
     * Defaults to DB_USERNAME / DB_PASSWORD; override with DB_SPLIT_CLI_USERNAME / DB_SPLIT_CLI_PASSWORD.
     *
     * @return array{0: string, 1: string}
     */
    protected function mysqlCliCredentials(): array
    {
        $user = (string) env('DB_SPLIT_CLI_USERNAME', env('DB_USERNAME', 'root'));
        $password = env('DB_SPLIT_CLI_PASSWORD', env('DB_PASSWORD'));

        return [$user, $password === null ? '' : (string) $password];
    }
}
