<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reservation;
use App\Models\ServiceFee;
use Illuminate\Support\Facades\DB;

class BackfillCoachEarnings extends Command
{
    protected $signature = 'backfill:coach-earnings {--dry : Dry run only}';

    protected $description = 'Backfill coach earning fields for paid reservations';

    public function handle()
    {
        $dry = $this->option('dry');

        $this->info('Scanning paid reservations with missing coach earnings...');

        $rows = Reservation::query()
            ->where('settlement_status', 'paid')
            ->where(function ($q) {
                $q->whereNull('coach_earned_minor')
                  ->orWhere('coach_earned_minor', 0);
            })
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to fix.');
            return 0;
        }

        $this->warn("Found {$rows->count()} reservation(s).");

        foreach ($rows as $res) {

            DB::transaction(function () use ($res, $dry) {

                $res = Reservation::lockForUpdate()->find($res->id);

                $gross = (int)($res->subtotal_minor ?? 0);

                if ($gross <= 0) return;

                $coachFeeMinor = (int)($res->coach_fee_minor ?? 0);

                // fallback for old records
                if ($coachFeeMinor <= 0) {
                    $fee = ServiceFee::where('is_active', true)
                        ->where(function ($q) {
                            $q->where('party', 'coach')
                              ->orWhere('slug', 'coach_commission');
                        })
                        ->first();

                    if ($fee) {
                        $coachFeeMinor = $fee->type === 'percent'
                            ? (int) round($gross * ((float)$fee->amount / 100))
                            : (int) round(((float)$fee->amount) * 100);
                    }
                }

                $net = max(0, $gross - $coachFeeMinor);

                $clientFeeMinor = (int)($res->fees_minor ?? 0);
                $platformEarned = max(0, $clientFeeMinor + $coachFeeMinor);

                if ($dry) {
                    $this->line("DRY: #{$res->id} → net={$net}");
                    return;
                }

                $res->forceFill([
                    'coach_gross_minor'      => $gross,
                    'coach_commission_minor' => $coachFeeMinor,
                    'coach_earned_minor'     => $net,
                    'coach_net_minor'        => $net,
                    'platform_earned_minor'  => $platformEarned,
                ])->save();

                $this->line("Fixed reservation #{$res->id}");
            });
        }

        $this->info('Done.');

        return 0;
    }
}