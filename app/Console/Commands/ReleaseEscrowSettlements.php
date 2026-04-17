<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reservation;
use App\Services\ReservationSettlementService;

class ReleaseEscrowSettlements extends Command
{
    protected $signature = 'settlements:release-escrow';
    protected $description = 'Release reservations whose escrow_release_at has passed';

    public function handle(ReservationSettlementService $service): int
    {
        $due = Reservation::query()
            ->where('payment_status', 'paid')
            ->where('settlement_status', 'pending')
            ->whereNotNull('escrow_release_at')
            ->where('escrow_release_at', '<=', now('UTC'))
            ->pluck('id');

        foreach ($due as $id) {
            $service->recompute((int)$id);
        }

        $this->info("Processed {$due->count()} reservations.");
        return self::SUCCESS;
    }
}