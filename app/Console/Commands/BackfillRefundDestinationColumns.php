<?php

namespace App\Console\Commands;

use App\Models\Refund;
use Illuminate\Console\Command;

class BackfillRefundDestinationColumns extends Command
{
    protected $signature = 'refunds:backfill-destinations';
    protected $description = 'Backfill refunded_to_wallet_minor and refunded_to_original_minor on refunds table';

    public function handle(): int
    {
        Refund::query()->chunkById(500, function ($refunds) {
            foreach ($refunds as $refund) {
                $method = strtolower((string) $refund->method);

                $toWallet = 0;
                $toOriginal = 0;

                if ($method === 'wallet_credit') {
                    $toWallet = (int) ($refund->actual_amount_minor ?? 0);
                    $toOriginal = 0;
                } elseif ($method === 'original_payment') {
                    $toWallet = (int) ($refund->wallet_amount_minor ?? 0);
                    $toOriginal = (int) ($refund->external_amount_minor ?? 0);
                }

                $refund->refunded_to_wallet_minor = max(0, $toWallet);
                $refund->refunded_to_original_minor = max(0, $toOriginal);
                $refund->save();
            }
        });

        $this->info('Refund destination columns backfilled successfully.');

        return self::SUCCESS;
    }
}