<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReservationSlot;
use Carbon\CarbonImmutable;
use App\Services\ProcessSlotTimer;

class ProcessSlotTimers extends Command
{
    protected $signature = 'slots:process';
    protected $description = 'Process reservation slot timers (reminders, nudges, finalization, settlement)';

    public function handle(): int
    {
        $now = CarbonImmutable::now('UTC');

        $stopSettlementStatuses = [
            'refunded',
            'refunded_partial',
            'cancelled',
            'in_dispute',
        ];

        ReservationSlot::query()
            ->whereNull('finalized_at')
            ->whereNotNull('start_utc')
            ->whereHas('reservation', function ($q) use ($stopSettlementStatuses) {
                $q->whereNotIn('status', ['cancelled', 'canceled'])
                  ->whereIn('payment_status', ['paid'])
                  ->whereNotIn('settlement_status', $stopSettlementStatuses);
            })
            ->chunkById(100, function ($slots) use ($now) {
                $timer = app(ProcessSlotTimer::class);

                foreach ($slots as $slot) {
                    try {
                        $timer->handle($slot, $now);
                    } catch (\Throwable $e) {
                        logger()->error('Slot timer failed', [
                            'slot_id' => $slot->id,
                            'reservation_id' => $slot->reservation_id ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return self::SUCCESS;
    }
}
