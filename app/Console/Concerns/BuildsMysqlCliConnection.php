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
     * mysql CLI credentials for db:split-multi / drop.
     *
     * When DB_SPLIT_CLI_USERNAME is set (non-empty), it wins; password uses DB_SPLIT_CLI_PASSWORD or DB_PASSWORD.
     *
     * When DB_SPLIT_CLI_USERNAME is empty:
     * - $defaultToSplitControlCredentials true (db:split-multi apply): use the `split_control` connection user/password
     *   so Hostinger works when only per-database users exist (admin often cannot USE split_control).
     * - false (db:split-multi:drop): use DB_USERNAME / DB_PASSWORD (needs a user allowed to DROP every split DB).
     *
     * The split_control user must still be able to SELECT from the monolith and write all target DBs, or set
     * DB_SPLIT_CLI_USERNAME to a power user that has those grants.
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
}
