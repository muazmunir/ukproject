<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Imports countries from database/countries_202604181308.sql (streaming parser).
 * Skips rows when wikiDataId or iso2 already exists (safe re-run).
 * If `regions` / `subregions` are empty or IDs from the dump do not exist, FK-safe
 * nulls are applied so inserts still succeed (string columns `region` / `subregion` stay).
 *
 * Usage: php artisan db:seed --class=CountriesFromSqlSeeder
 * Optional: COUNTRIES_IMPORT_SQL=/full/path/to/file.sql
 */
class CountriesFromSqlSeeder extends Seeder
{
    private const DEFAULT_SQL = 'countries_202604181308.sql';

    private const EXPECTED_VALUE_COUNT = 28;

    private const BATCH_SIZE = 50;

    private const PROGRESS_EVERY_SCANNED = 5;

    public function run(): void
    {
        $path = $this->resolveSqlPath();
        if (! is_readable($path)) {
            $this->command?->error("Countries SQL file not readable: {$path}");

            return;
        }

        $this->command?->warn('Countries import: reading SQL dump…');

        $conn = (new Country)->getConnection()->getName();

        $validRegionIds = $this->loadIdLookupSet($conn, 'regions');
        $validSubregionIds = $this->loadIdLookupSet($conn, 'subregions');

        $existingWiki = Country::query()
            ->whereNotNull('wikiDataId')
            ->where('wikiDataId', '!=', '')
            ->pluck('wikiDataId')
            ->flip()
            ->all();

        $existingIso2 = Country::query()
            ->whereNotNull('iso2')
            ->where('iso2', '!=', '')
            ->pluck('iso2')
            ->mapWithKeys(fn ($iso) => [strtoupper(trim((string) $iso)) => 1])
            ->all();

        $inserted = 0;
        $skippedDup = 0;
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
                        && stripos($line, 'countries') !== false
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
                        $existingWiki,
                        $existingIso2,
                        $conn,
                        $batch,
                        $scanned,
                        $inserted,
                        $skippedDup,
                        $skippedBadRow,
                        $validRegionIds,
                        $validSubregionIds
                    );
                    $collecting = false;
                    $payload = '';
                }
            }
        } finally {
            fclose($handle);
        }

        $inserted += $this->flushBatch($conn, $batch);
        $this->writeCountriesProgressLine($scanned, $inserted, $skippedDup, $skippedBadRow, true);

        $this->command?->newLine();
        $this->command?->info("Countries import done: inserted={$inserted}, skipped_duplicate={$skippedDup}, skipped_bad_row={$skippedBadRow}");
    }

    private function resolveSqlPath(): string
    {
        $env = env('COUNTRIES_IMPORT_SQL');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        return database_path(self::DEFAULT_SQL);
    }

    /**
     * @return array<int, true>|null null = table missing, skip FK checks
     */
    private function loadIdLookupSet(string $connection, string $table): ?array
    {
        if (! Schema::connection($connection)->hasTable($table)) {
            return null;
        }

        $ids = DB::connection($connection)->table($table)->pluck('id');
        $set = [];
        foreach ($ids as $id) {
            $set[(int) $id] = true;
        }

        return $set;
    }

    /**
     * @param  array<int, true>|null  $validRegionIds  null = no `regions` table on this connection
     * @param  array<int, true>|null  $validSubregionIds  null = no `subregions` table
     */
    private function alignCountryForeignKeys(array $row, ?array $validRegionIds, ?array $validSubregionIds): array
    {
        if ($validRegionIds === null || $validRegionIds === []) {
            $row['region_id'] = null;
            $row['subregion_id'] = null;
        } else {
            $rid = $row['region_id'];
            if ($rid !== null && ! isset($validRegionIds[(int) $rid])) {
                $row['region_id'] = null;
                $row['subregion_id'] = null;
            }
        }

        if ($validSubregionIds === null || $validSubregionIds === []) {
            $row['subregion_id'] = null;
        } else {
            $sid = $row['subregion_id'];
            if ($sid !== null && ! isset($validSubregionIds[(int) $sid])) {
                $row['subregion_id'] = null;
            }
        }

        return $row;
    }

    private function writeCountriesProgressLine(
        int $scanned,
        int $inserted,
        int $skippedDup,
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
            'Countries  scanned=%d  inserted=%d  skip_dup=%d  skip_bad=%d',
            $scanned,
            $inserted,
            $skippedDup,
            $skippedBadRow
        );
        $this->command->getOutput()->write("\r\033[K<fg=cyan>{$line}</>");
    }

    /**
     * @param  array<string, int>  $existingWiki
     * @param  array<string, int>  $existingIso2
     * @param  array<int, array<string, mixed>>  $batch
     * @param  array<int, true>|null  $validRegionIds
     * @param  array<int, true>|null  $validSubregionIds
     */
    private function processValuesPayload(
        string $payload,
        array &$existingWiki,
        array &$existingIso2,
        string $conn,
        array &$batch,
        int &$scanned,
        int &$inserted,
        int &$skippedDup,
        int &$skippedBadRow,
        ?array $validRegionIds,
        ?array $validSubregionIds
    ): void {
        $self = $this;
        $afterTuple = function (string $tuple) use (&$i, &$scanned, &$inserted, &$skippedDup, &$skippedBadRow, $self): void {
            $i += strlen($tuple);
            $scanned++;
            $self->writeCountriesProgressLine($scanned, $inserted, $skippedDup, $skippedBadRow, false);
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

            $iso2 = strtoupper(trim((string) ($vals[3] ?? '')));
            if ($iso2 === '') {
                $skippedBadRow++;
                $afterTuple($tuple);

                continue;
            }

            if (isset($existingIso2[$iso2])) {
                $skippedDup++;
                $afterTuple($tuple);

                continue;
            }

            $wiki = isset($vals[27]) && is_string($vals[27]) ? trim($vals[27]) : '';
            if ($wiki !== '' && isset($existingWiki[$wiki])) {
                $skippedDup++;
                $afterTuple($tuple);

                continue;
            }

            $row = $this->buildRow($vals, $iso2);
            $batch[] = $this->alignCountryForeignKeys($row, $validRegionIds, $validSubregionIds);
            $existingIso2[$iso2] = 1;
            if ($wiki !== '') {
                $existingWiki[$wiki] = 1;
            }

            if (count($batch) >= self::BATCH_SIZE) {
                $inserted += $this->flushBatch($conn, $batch);
                $this->writeCountriesProgressLine($scanned + 1, $inserted, $skippedDup, $skippedBadRow, true);
            }

            $afterTuple($tuple);
        }
    }

    /**
     * @param  array<int, mixed>  $vals
     * @return array<string, mixed>
     */
    private function buildRow(array $vals, string $iso2): array
    {
        return [
            'name' => (string) $vals[0],
            'iso3' => $vals[1] === null ? null : (string) $vals[1],
            'numeric_code' => $vals[2] === null ? null : (string) $vals[2],
            'iso2' => $iso2,
            'phonecode' => $vals[4] === null ? null : (string) $vals[4],
            'capital' => $vals[5] === null ? null : (string) $vals[5],
            'currency' => $vals[6] === null ? null : (string) $vals[6],
            'currency_name' => $vals[7] === null ? null : (string) $vals[7],
            'currency_symbol' => $vals[8] === null ? null : (string) $vals[8],
            'tld' => $vals[9] === null ? null : (string) $vals[9],
            'native' => $vals[10] === null ? null : (string) $vals[10],
            'population' => $vals[11] === null ? null : (int) $vals[11],
            'gdp' => $vals[12] === null ? null : (int) $vals[12],
            'region' => $vals[13] === null ? null : (string) $vals[13],
            'region_id' => $vals[14] === null ? null : (int) $vals[14],
            'subregion' => $vals[15] === null || $vals[15] === '' ? null : (string) $vals[15],
            'subregion_id' => $vals[16] === null ? null : (int) $vals[16],
            'nationality' => $vals[17] === null ? null : (string) $vals[17],
            'timezones' => $vals[18] === null ? null : (string) $vals[18],
            'translations' => $vals[19] === null ? null : (string) $vals[19],
            'latitude' => $vals[20] === null ? null : (float) $vals[20],
            'longitude' => $vals[21] === null ? null : (float) $vals[21],
            'emoji' => $vals[22] === null ? null : (string) $vals[22],
            'emojiU' => $vals[23] === null ? null : (string) $vals[23],
            'created_at' => $vals[24] === null ? null : (string) $vals[24],
            'updated_at' => $vals[25] === null ? null : (string) $vals[25],
            'flag' => $vals[26] === null ? null : (int) $vals[26],
            'wikiDataId' => $vals[27] === null ? null : (string) $vals[27],
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
        DB::connection($conn)->table('countries')->insert($batch);
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
