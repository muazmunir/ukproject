<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Hostinger-style hosts: one MySQL user per database, so stored procedures invoked via a single
 * mysql CLI user cannot SELECT the monolith and INSERT into other schemas. This copier uses the
 * monolith connection for reads and each domain connection for writes.
 */
final class SplitMultiPhpCopier
{
    private const CHUNK_SIZE = 500;

    /**
     * @return array<string, string> resolved database name => Laravel connection name
     */
    private function databaseNameToConnection(): array
    {
        $map = [];
        foreach (['auth_db', 'pii_db', 'kyc_db', 'payments_db', 'app_db', 'comms_db', 'media_db', 'audit_db'] as $conn) {
            $db = config("database.connections.{$conn}.database");
            if (is_string($db) && $db !== '') {
                $map[$db] = $conn;
            }
        }

        return $map;
    }

    public function run(OutputInterface $output): void
    {
        $output->writeln('<info>Copying tables from monolith (PHP / per-connection users)…</info>');

        $dbToConn = $this->databaseNameToConnection();
        $map = DB::connection('split_control')->table('_split_multidb_table_map')->orderBy('target_db')->orderBy('table_name')->get();

        foreach ($map as $row) {
            $table = (string) $row->table_name;
            $targetDb = (string) $row->target_db;
            if (! preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                continue;
            }
            $targetConn = $dbToConn[$targetDb] ?? null;
            if ($targetConn === null) {
                $output->writeln("<comment>Skip `{$table}`: unknown target database `{$targetDb}`</comment>");

                continue;
            }
            if (! Schema::connection('monolith')->hasTable($table)) {
                $output->writeln("<comment>Skip `{$table}`: not present in monolith</comment>");

                continue;
            }

            $this->copyOneTable($output, $table, $targetConn);
        }

        $output->writeln('<info>Creating compatibility views on auth_db (optional; may fail without cross-DB grants)…</info>');
        $this->createCompatViews($output, $dbToConn);
    }

    private function copyOneTable(OutputInterface $output, string $table, string $targetConn): void
    {
        try {
            $createRow = DB::connection('monolith')->selectOne('SHOW CREATE TABLE `'.$table.'`');
        } catch (QueryException $e) {
            $output->writeln("<error>SHOW CREATE TABLE `{$table}`: {$e->getMessage()}</error>");

            return;
        }

        if ($createRow === null) {
            $output->writeln("<error>Skip `{$table}`: SHOW CREATE TABLE returned no row.</error>");

            return;
        }

        $createSql = $this->extractShowCreateTableDdl($createRow);
        if ($createSql === null) {
            $output->writeln("<error>Skip `{$table}`: could not read CREATE TABLE from monolith.</error>");

            return;
        }

        try {
            DB::connection($targetConn)->statement('SET FOREIGN_KEY_CHECKS=0');
            DB::connection($targetConn)->unprepared('DROP TABLE IF EXISTS `'.$table.'`');
            DB::connection($targetConn)->unprepared($createSql);
        } catch (Throwable $e) {
            $output->writeln("<error>`{$table}` on {$targetConn}: {$e->getMessage()}</error>");
            try {
                DB::connection($targetConn)->statement('SET FOREIGN_KEY_CHECKS=1');
            } catch (Throwable) {
            }

            return;
        }

        $offset = 0;
        $total = 0;
        while (true) {
            $rows = DB::connection('monolith')->table($table)->offset($offset)->limit(self::CHUNK_SIZE)->get();
            if ($rows->isEmpty()) {
                break;
            }
            $payload = $rows->map(static fn ($r) => json_decode(json_encode($r), true))->all();
            try {
                DB::connection($targetConn)->table($table)->insert($payload);
            } catch (Throwable $e) {
                $output->writeln("<error>INSERT `{$table}` chunk at offset {$offset}: {$e->getMessage()}</error>");
                try {
                    DB::connection($targetConn)->statement('SET FOREIGN_KEY_CHECKS=1');
                } catch (Throwable) {
                }

                return;
            }
            $total += count($payload);
            $offset += self::CHUNK_SIZE;
            if (count($payload) < self::CHUNK_SIZE) {
                break;
            }
        }

        try {
            DB::connection($targetConn)->statement('SET FOREIGN_KEY_CHECKS=1');
        } catch (Throwable) {
        }

        $output->writeln("  <fg=green>OK</> `{$table}` → {$targetConn} ({$total} rows)");
    }

    /**
     * @param  object|array<string, mixed>  $row
     */
    private function extractShowCreateTableDdl(object|array $row): ?string
    {
        $arr = (array) $row;
        foreach (['Create Table', 'create table'] as $key) {
            if (isset($arr[$key]) && is_string($arr[$key]) && $arr[$key] !== '') {
                return $arr[$key];
            }
        }

        $first = reset($arr);

        return is_string($first) ? $first : null;
    }

    /**
     * @param  array<string, string>  $dbToConn
     */
    private function createCompatViews(OutputInterface $output, array $dbToConn): void
    {
        $rows = DB::connection('split_control')
            ->table('_split_multidb_table_map as m')
            ->leftJoin('_split_multidb_auth_tables as a', 'a.table_name', '=', 'm.table_name')
            ->whereNull('a.table_name')
            ->select('m.table_name', 'm.target_db')
            ->orderBy('m.target_db')
            ->orderBy('m.table_name')
            ->get();

        foreach ($rows as $row) {
            $table = (string) $row->table_name;
            $targetDb = (string) $row->target_db;
            if (! preg_match('/^[a-zA-Z0-9_]+$/', $table) || ! preg_match('/^[a-zA-Z0-9_]+$/', $targetDb)) {
                continue;
            }
            $targetConn = $dbToConn[$targetDb] ?? null;
            if ($targetConn === null || ! Schema::connection($targetConn)->hasTable($table)) {
                continue;
            }

            try {
                DB::connection('auth_db')->statement('DROP VIEW IF EXISTS `'.$table.'`');
                DB::connection('auth_db')->unprepared(
                    'CREATE VIEW `'.$table.'` AS SELECT * FROM `'.$targetDb.'`.`'.$table.'`'
                );
                $output->writeln("  <fg=green>VIEW</> `{$table}` on auth_db");
            } catch (Throwable $e) {
                $output->writeln("<comment>VIEW `{$table}` skipped: {$e->getMessage()}</comment>");
            }
        }
    }
}
