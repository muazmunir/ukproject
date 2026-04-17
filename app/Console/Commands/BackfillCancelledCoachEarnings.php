<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class BackfillCancelledCoachEarnings extends Command
{
    protected $signature = 'backfill:cancelled-coach-earnings {--dry}';
    protected $description = 'Zero out coach earning fields for cancelled reservations (coach gets only comp/penalty)';

    public function handle()
    {
        $dry = (bool)$this->option('dry');

        $rows = Reservation::query()
            ->whereIn('status', ['cancelled','canceled'])
            ->where(function($q){
                $q->where('coach_net_minor', '>', 0)
                  ->orWhere('coach_earned_minor', '>', 0)
                  ->orWhere('coach_gross_minor', '>', 0);
            })
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to fix.');
            return 0;
        }

        $this->warn("Found {$rows->count()} cancelled reservation(s) with non-zero coach earnings.");

        foreach ($rows as $r) {
            DB::transaction(function () use ($r, $dry) {

                $res = Reservation::lockForUpdate()->find($r->id);
                if (!$res) return;

                if ($dry) {
                    $this->line("DRY: #{$res->id} coach_net={$res->coach_net_minor} coach_earned={$res->coach_earned_minor}");
                    return;
                }

                $res->forceFill([
                    'coach_gross_minor'      => 0,
                    'coach_commission_minor' => 0,
                    'coach_earned_minor'     => 0,
                    'coach_net_minor'        => 0,
                ])->save();

                $this->line("Fixed #{$res->id}");
            });
        }

        $this->info('Done.');
        return 0;
    }
}