<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCoachFeeSnapshots extends Command
{
    protected $signature = 'reservations:backfill-coach-fee {--dry-run : Show counts only, no updates} {--chunk=500}';
    protected $description = 'Backfill coach fee snapshot fields on reservations (coach_fee_type/amount/minor/net_minor).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk  = max(50, (int) $this->option('chunk'));

        // ✅ get active coach commission rule
        $fee = DB::table('service_fees')
            ->where('is_active', 1)
            ->where(function ($q) {
                $q->where('party', 'coach')
                  ->orWhere('slug', 'coach_commission');
            })
            ->orderByDesc('id')
            ->first();

        if (! $fee) {
            $this->error('No active coach_commission fee found in service_fees.');
            return self::FAILURE;
        }

        $feeType   = (string) ($fee->type ?? 'percent');          // percent|fixed
        $feeAmount = (float)  ($fee->amount ?? 0);                // 10 or 2.50 etc

        $this->info("Using coach fee rule: type={$feeType} amount={$feeAmount}");

        // Only backfill rows where snapshot is missing/empty
        $baseQuery = DB::table('reservations')
            ->whereNotNull('subtotal_minor')
            ->where('subtotal_minor', '>', 0)
            ->where(function ($q) {
                $q->whereNull('coach_fee_type')
                  ->orWhereNull('coach_fee_amount')
                  ->orWhere('coach_fee_minor', 0)
                  ->orWhere('coach_net_minor', 0);
            });

        $total = (clone $baseQuery)->count();
        $this->info("Reservations to process: {$total}");

        if ($dryRun) {
            $this->warn('Dry-run enabled: no updates will be performed.');
            return self::SUCCESS;
        }

        $processed = 0;

        $baseQuery->orderBy('id')->chunkById($chunk, function ($rows) use (&$processed, $feeType, $feeAmount) {
            DB::transaction(function () use ($rows, $feeType, $feeAmount, &$processed) {

                foreach ($rows as $r) {
                    $subtotalMinor = (int) ($r->subtotal_minor ?? 0);
                    if ($subtotalMinor <= 0) continue;

                    // compute coach fee minor from subtotal
                    $coachFeeMinor = 0;

                    if ($feeType === 'percent') {
                        $coachFeeMinor = (int) round($subtotalMinor * ($feeAmount / 100));
                    } else { // fixed
                        $coachFeeMinor = (int) round($feeAmount * 100);
                        if ($coachFeeMinor > $subtotalMinor) {
                            $coachFeeMinor = $subtotalMinor;
                        }
                    }

                    $coachNetMinor = max(0, $subtotalMinor - $coachFeeMinor);

                    DB::table('reservations')
                        ->where('id', $r->id)
                        ->update([
                            'coach_fee_type'   => $feeType,
                            'coach_fee_amount' => $feeAmount,
                            'coach_fee_minor'  => $coachFeeMinor,
                            'coach_net_minor'  => $coachNetMinor,
                            'updated_at'       => now(),
                        ]);

                    $processed++;
                }

            });
        });

        $this->info("Done. Updated: {$processed} reservations.");
        return self::SUCCESS;
    }
}