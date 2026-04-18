<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Imports cities from the MySQL dump in database/ (default: cities_202604181412.sql).
 * - Remaps country_id using your existing countries table (iso2 ↔ SQL country_code).
 * - Skips rows whose wikiDataId already exists (safe re-run).
 *
 * Usage: php artisan db:seed --class=CitiesFromSqlSeeder
 * Optional: CITIES_IMPORT_SQL=/full/path/to/file.sql
 */
class CitiesFromSqlSeeder extends Seeder
{
    private const DEFAULT_SQL = 'cities_202604181412.sql';

    private const EXPECTED_VALUE_COUNT = 18;

    private const BATCH_SIZE = 400;

    /** Update terminal every N rows parsed (same line). */
    private const PROGRESS_EVERY_SCANNED = 2500;

    public function run(): void
    {
        $path = $this->resolveSqlPath();
        if (! is_readable($path)) {
            $this->command?->error("Cities SQL file not readable: {$path}");

            return;
        }

        $this->command?->warn('Cities import: reading SQL dump (may take several minutes)…');

        $conn = (new City)->getConnection()->getName();
        $iso2ToId = Country::query()
            ->whereNotNull('iso2')
            ->where('iso2', '!=', '')
            ->get(['id', 'iso2'])
            ->mapWithKeys(fn ($c) => [strtoupper(trim((string) $c->iso2)) => (int) $c->id])
            ->all();

        if ($iso2ToId === []) {
            $this->command?->error('No countries with iso2 found — seed countries first.');

            return;
        }

        $existingWiki = City::query()
            ->whereNotNull('wikiDataId')
            ->where('wikiDataId', '!=', '')
            ->pluck('wikiDataId')
            ->flip()
            ->all();

        $inserted = 0;
        $skippedDup = 0;
        $skippedNoCountry = 0;
        $skippedBadRow = 0;
        $batch = [];
        $scanned = 0;

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->command?->error("Could not open: {$path}");

            return;
        }

        $collecting = false;
        $payload = '';

        try {
            while (($line = fgets($handle)) !== false) {
                if (! $collecting) {
                    if (
                        stripos($line, 'INSERT INTO') !== false
                        && stripos($line, 'cities') !== false
                        && stripos($line, 'VALUES') !== false
                    ) {
                        $collecting = true;
                        $payload = '';
                    }

                    continue;
                }

                $payload .= $line;
                if (preg_match('/\);\s*$/', rtrim($line))) {
                    $this->processValuesPayload(
                        $payload,
                        $iso2ToId,
                        $existingWiki,
                        $conn,
                        $batch,
                        $scanned,
                        $inserted,
                        $skippedDup,
                        $skippedNoCountry,
                        $skippedBadRow
                    );
                    $collecting = false;
                    $payload = '';
                }
            }
        } finally {
            fclose($handle);
        }

        $inserted += $this->flushBatch($conn, $batch);
        $this->writeCitiesProgressLine($scanned, $inserted, $skippedDup, $skippedNoCountry, $skippedBadRow, true);

        $this->command?->newLine();
        $this->command?->info("Cities import done: inserted={$inserted}, skipped_duplicate={$skippedDup}, skipped_unknown_country={$skippedNoCountry}, skipped_bad_row={$skippedBadRow}");
    }

    private function resolveSqlPath(): string
    {
        $env = env('CITIES_IMPORT_SQL');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        return database_path(self::DEFAULT_SQL);
    }

    private function writeCitiesProgressLine(
        int $scanned,
        int $inserted,
        int $skippedDup,
        int $skippedNoCountry,
        int $skippedBadRow,
        bool $force = false
    ): void {
        if ($this->command === null) {
            return;
        }

        if (
            ! $force
            && $scanned !== 1
            && ($scanned <= 0 || $scanned % self::PROGRESS_EVERY_SCANNED !== 0)
        ) {
            return;
        }

        $line = sprintf(
            'Cities  scanned=%d  inserted=%d  skip_dup=%d  skip_no_country=%d  skip_bad=%d',
            $scanned,
            $inserted,
            $skippedDup,
            $skippedNoCountry,
            $skippedBadRow
        );
        $this->command->getOutput()->write("\r\033[K<fg=cyan>{$line}</>");
    }

    /**
     * @param  array<string, int>  $iso2ToId
     * @param  array<string, int>  $existingWiki
     * @param  array<int, array<string, mixed>>  $batch
     */
    private function processValuesPayload(
        string $payload,
        array $iso2ToId,
        array &$existingWiki,
        string $conn,
        array &$batch,
        int &$scanned,
        int &$inserted,
        int &$skippedDup,
        int &$skippedNoCountry,
        int &$skippedBadRow
    ): void {
        $self = $this;
        $afterTuple = function (string $tuple) use (&$i, &$scanned, &$inserted, &$skippedDup, &$skippedNoCountry, &$skippedBadRow, $self): void {
            $i += strlen($tuple);
            $scanned++;
            $self->writeCitiesProgressLine($scanned, $inserted, $skippedDup, $skippedNoCountry, $skippedBadRow, false);
        };

        $n = strlen($payload);
        $i = 0;

        while ($i < $n) {
            while ($i < $n && $payload[$i] !== '(') {
                $i++;
            }
            if ($i >= $n) {
                break;
            }

            $tuple = $this->extractBalancedTuple($payload, $i);
            if ($tuple === null) {
                break;
            }

            $inner = substr($tuple, 1, -1);
            try {
                $vals = $this->parseSqlValuesList($inner);
            } catch (\Throwable) {
                $skippedBadRow++;
                $afterTuple($tuple);

                continue;
            }

            if (count($vals) !== self::EXPECTED_VALUE_COUNT) {
                $skippedBadRow++;
                $afterTuple($tuple);

                continue;
            }

            $countryCode = strtoupper((string) ($vals[4] ?? ''));
            $countryId = $iso2ToId[$countryCode] ?? null;
            if ($countryId === null) {
                $skippedNoCountry++;
                $afterTuple($tuple);

                continue;
            }

            $wiki = isset($vals[17]) && is_string($vals[17]) ? trim($vals[17]) : '';
            if ($wiki !== '') {
                if (isset($existingWiki[$wiki])) {
                    $skippedDup++;
                    $afterTuple($tuple);

                    continue;
                }
            } else {
                $name = (string) ($vals[0] ?? '');
                if (
                    DB::connection($conn)->table('cities')
                        ->where('country_code', $countryCode)
                        ->where('name', $name)
                        ->exists()
                ) {
                    $skippedDup++;
                    $afterTuple($tuple);

                    continue;
                }
            }

            $row = $this->buildRow($vals, $countryId, $countryCode);
            $batch[] = $row;
            if ($wiki !== '') {
                $existingWiki[$wiki] = 1;
            }

            if (count($batch) >= self::BATCH_SIZE) {
                $inserted += $this->flushBatch($conn, $batch);
                $this->writeCitiesProgressLine($scanned + 1, $inserted, $skippedDup, $skippedNoCountry, $skippedBadRow, true);
            }

            $afterTuple($tuple);
        }
    }

    /**
     * @param  array<int, mixed>  $vals
     * @return array<string, mixed>
     */
    private function buildRow(array $vals, int $countryId, string $countryCode): array
    {
        return [
            'name' => (string) $vals[0],
            'state_id' => $vals[1] === null ? null : (int) $vals[1],
            'state_code' => $vals[2] === null ? null : (string) $vals[2],
            'country_id' => $countryId,
            'country_code' => $countryCode,
            'type' => $vals[5],
            'level' => $vals[6],
            'parent_id' => $vals[7] === null ? null : (int) $vals[7],
            'latitude' => $vals[8] === null ? null : (float) $vals[8],
            'longitude' => $vals[9] === null ? null : (float) $vals[9],
            'native' => $vals[10] === null ? null : (string) $vals[10],
            'population' => $vals[11] === null ? null : (int) $vals[11],
            'timezone' => $vals[12] === null ? null : (string) $vals[12],
            'translations' => $vals[13] === null ? null : (string) $vals[13],
            'created_at' => $vals[14] === null ? null : (string) $vals[14],
            'updated_at' => $vals[15] === null ? null : (string) $vals[15],
            'flag' => $vals[16] === null ? null : (int) $vals[16],
            'wikiDataId' => $vals[17] === null ? null : (string) $vals[17],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $batch
     */
    private function flushBatch(string $conn, array &$batch): int
    {
        if ($batch === []) {
            return 0;
        }

        $n = count($batch);
        DB::connection($conn)->table('cities')->insert($batch);
        $batch = [];

        return $n;
    }

    private function extractBalancedTuple(string $body, int $openPos): ?string
    {
        $n = strlen($body);
        if ($openPos >= $n || $body[$openPos] !== '(') {
            return null;
        }

        $depth = 1;
        $inString = false;

        for ($i = $openPos + 1; $i < $n; $i++) {
            $c = $body[$i];
            if ($inString) {
                if ($c === "'" && $i + 1 < $n && $body[$i + 1] === "'") {
                    $i++;

                    continue;
                }
                if ($c === "'") {
                    $inString = false;
                }

                continue;
            }
            if ($c === "'") {
                $inString = true;

                continue;
            }
            if ($c === '(') {
                $depth++;

                continue;
            }
            if ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    return substr($body, $openPos, $i - $openPos + 1);
                }
            }
        }

        return null;
    }

    /**
     * @return list<mixed>
     */
    private function parseSqlValuesList(string $inner): array
    {
        $out = [];
        $n = strlen($inner);
        $i = 0;

        while ($i < $n) {
            while ($i < $n && (ctype_space($inner[$i]) || $inner[$i] === ',')) {
                $i++;
            }
            if ($i >= $n) {
                break;
            }

            if (substr($inner, $i, 4) === 'NULL' && ($i + 4 >= $n || ! ctype_alpha($inner[$i + 4]))) {
                $out[] = null;
                $i += 4;

                continue;
            }

            if ($inner[$i] === '-' || ctype_digit($inner[$i])) {
                $j = $i;
                if ($inner[$j] === '-') {
                    $j++;
                }
                while ($j < $n && ctype_digit($inner[$j])) {
                    $j++;
                }
                if ($j < $n && $inner[$j] === '.') {
                    $j++;
                    while ($j < $n && ctype_digit($inner[$j])) {
                        $j++;
                    }
                    $out[] = (float) substr($inner, $i, $j - $i);
                } else {
                    $out[] = (int) substr($inner, $i, $j - $i);
                }
                $i = $j;

                continue;
            }

            if ($inner[$i] === "'") {
                $i++;
                $buf = '';
                while ($i < $n) {
                    if ($inner[$i] === "'" && $i + 1 < $n && $inner[$i + 1] === "'") {
                        $buf .= "'";
                        $i += 2;

                        continue;
                    }
                    if ($inner[$i] === "'") {
                        $i++;
                        break;
                    }
                    $buf .= $inner[$i];
                    $i++;
                }
                $out[] = $buf;

                continue;
            }

            throw new \InvalidArgumentException('Unexpected token in SQL values at offset '.$i);
        }

        return $out;
    }
}
