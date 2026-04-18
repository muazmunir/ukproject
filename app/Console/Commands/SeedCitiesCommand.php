<?php

namespace App\Console\Commands;

use App\Models\City;
use Database\Seeders\CitiesFromSqlSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class SeedCitiesCommand extends Command
{
    protected $signature = 'seed:cities
                            {--truncate : Empty the cities table (TRUNCATE) before import}
                            {--force : Allow seeding in production (passed to db:seed)}';

    protected $description = 'Import cities from SQL (CitiesFromSqlSeeder). Use --truncate for a full reload.';

    public function handle(): int
    {
        $conn = (new City)->getConnection()->getName();

        if ($this->option('truncate')) {
            if (! Schema::connection($conn)->hasTable('cities')) {
                $this->error('cities table does not exist; run migrations first.');

                return self::FAILURE;
            }
            $this->warn('Truncating cities table…');
            CitiesFromSqlSeeder::truncateCitiesTable($conn);
            $this->info('cities table truncated.');
        }

        $params = [
            '--class' => CitiesFromSqlSeeder::class,
        ];
        if ($this->option('force')) {
            $params['--force'] = true;
        }

        $code = Artisan::call('db:seed', $params, $this->output);

        return $code === 0 ? self::SUCCESS : self::FAILURE;
    }
}
