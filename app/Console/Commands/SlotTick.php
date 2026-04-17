<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReservationSlot;
use Carbon\CarbonImmutable;
use App\Services\ProcessSlotTimer;

class SlotTick extends Command
{
    protected $signature = 'slots:tick';
    protected $description = 'Process slot reminders, nudges, extensions, and auto-finalization';

    public function handle(ProcessSlotTimer $timer)
    {
        $now = CarbonImmutable::now('UTC');

        ReservationSlot::whereNull('finalized_at')
            ->whereNotNull('start_utc')
            ->chunkById(100, function ($slots) use ($timer, $now) {
                foreach ($slots as $slot) {
                    $timer->handle($slot, $now);
                }
            });

        return self::SUCCESS;
    }
}
