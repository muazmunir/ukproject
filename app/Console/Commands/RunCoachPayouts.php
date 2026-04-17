<?php

namespace App\Console\Commands;

use App\Services\CoachPayoutRunService;
use Illuminate\Console\Command;
use Throwable;

class RunCoachPayouts extends Command
{
    protected $signature = 'payouts:run-coach
                            {provider : Payout provider (e.g. stripe, payoneer)}
                            {--date= : Optional run date/time reference}
                            {--dry-run : Prepare and validate only without sending provider payouts}';

    protected $description = 'Run coach payout batch for payout-ready coaches';

    public function handle(CoachPayoutRunService $service): int
    {
        $provider = (string) $this->argument('provider');
        $dryRun = (bool) $this->option('dry-run');
        $date = $this->option('date');

        $this->newLine();
        $this->info(sprintf('Running %s coach payout batch...', ucfirst($provider)));

        if ($dryRun) {
            $this->comment('Dry run enabled. No payouts will be sent to the provider.');
        }

        if (! empty($date)) {
            $this->line('Reference date: ' . $date);
        }

        try {
            $run = $service->run([
                'provider' => $provider,
                'date' => $date,
                'dry_run' => $dryRun,
            ]);

            $this->newLine();
            $this->info('Coach payout run completed successfully.');
            $this->line('Run ID: ' . $run->id);
            $this->line('Provider: ' . (string) $run->provider);
            $this->line('Status: ' . (string) $run->status);
            $this->line('Success count: ' . (int) $run->success_count);
            $this->line('Failed count: ' . (int) $run->failed_count);
            $this->line('Total coaches: ' . (int) $run->total_coaches);
            $this->line('Total amount minor: ' . (int) $run->total_amount_minor);

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);

            $this->newLine();
            $this->error('Coach payout run failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}