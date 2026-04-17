<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use Illuminate\Console\Command;

class BackfillReservationPlatformFeeRefunds extends Command
{
    /**
     * Run examples:
     * php artisan reservations:backfill-platform-fee-refunds
     * php artisan reservations:backfill-platform-fee-refunds --dry-run
     * php artisan reservations:backfill-platform-fee-refunds --only-missing
     * php artisan reservations:backfill-platform-fee-refunds --chunk=200
     */
    protected $signature = 'reservations:backfill-platform-fee-refunds
                            {--dry-run : Show what would change without saving}
                            {--only-missing : Only process rows where both fee refund columns are zero/null}
                            {--chunk=100 : Chunk size}';

    protected $description = 'Backfill platform_fee_refund_requested_minor and platform_fee_refunded_minor on reservations';

    public function handle(): int
    {
        $dryRun      = (bool) $this->option('dry-run');
        $onlyMissing = (bool) $this->option('only-missing');
        $chunkSize   = max(1, (int) $this->option('chunk'));

        $query = Reservation::query()->orderBy('id');

        if ($onlyMissing) {
            $query->where(function ($q) {
                $q->whereNull('platform_fee_refund_requested_minor')
                  ->orWhereNull('platform_fee_refunded_minor')
                  ->orWhere(function ($qq) {
                      $qq->where('platform_fee_refund_requested_minor', 0)
                         ->where('platform_fee_refunded_minor', 0);
                  });
            });
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No reservations matched.');
            return self::SUCCESS;
        }

        $this->info("Processing {$total} reservations...");
        if ($dryRun) {
            $this->warn('Dry run enabled. No changes will be saved.');
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;
        $changed   = 0;

        $query->chunkById($chunkSize, function ($rows) use (&$processed, &$changed, $dryRun, $bar) {
            foreach ($rows as $res) {
                $processed++;

                [$requestedMinor, $refundedMinor, $reason] = $this->computeBackfillValues($res);

                $currentRequested = (int) ($res->platform_fee_refund_requested_minor ?? 0);
                $currentRefunded  = (int) ($res->platform_fee_refunded_minor ?? 0);

                $isChanged = $currentRequested !== $requestedMinor || $currentRefunded !== $refundedMinor;

                if ($isChanged) {
                    $changed++;

                    if ($dryRun) {
                        $this->newLine();
                        $this->line(sprintf(
                            '#%d | requested: %d -> %d | refunded: %d -> %d | reason: %s',
                            (int) $res->id,
                            $currentRequested,
                            $requestedMinor,
                            $currentRefunded,
                            $refundedMinor,
                            $reason
                        ));
                    } else {
                        $res->forceFill([
                            'platform_fee_refund_requested_minor' => $requestedMinor,
                            'platform_fee_refunded_minor'         => $refundedMinor,
                        ])->save();
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Processed: {$processed}");
        $this->info("Changed: {$changed}");

        return self::SUCCESS;
    }

    /**
     * Backfill rules:
     *
     * requested:
     * - full refund path => fees_minor
     * - otherwise 0
     *
     * actual refunded:
     * - full refund completed => fees_minor
     * - refunded_partial => infer refundable fee portion from actual refund over subtotal
     * - otherwise 0
     *
     * Notes:
     * - This intentionally stays conservative.
     * - It does not try to reconstruct every edge case from historical retries beyond
     *   currently stored reservation totals/status.
     */
    private function computeBackfillValues(Reservation $res): array
    {
        $feesMinor        = max(0, (int) ($res->fees_minor ?? 0));
        $subtotalMinor    = max(0, (int) ($res->subtotal_minor ?? 0));
        $totalMinor       = max(0, (int) ($res->total_minor ?? 0));
        $refundTotalMinor = max(0, (int) ($res->refund_total_minor ?? 0));

        $settlementStatus = strtolower((string) ($res->settlement_status ?? ''));
        $refundStatus     = strtolower((string) ($res->refund_status ?? ''));
        $cancelledBy      = strtolower((string) ($res->cancelled_by ?? ''));

        $requestedMinor = 0;
        $refundedMinor  = 0;
        $reason         = 'default_zero';

        $isFullRefundRequested = $refundTotalMinor > 0 && $totalMinor > 0 && $refundTotalMinor === $totalMinor;
        $isPartialRefundRequested = $refundTotalMinor > 0 && $refundTotalMinor < $totalMinor;

        // -----------------------------------
        // 1) Requested fee refund
        // -----------------------------------
        if ($isFullRefundRequested) {
            $requestedMinor = $feesMinor;
            $reason = 'full_refund_requested';
        } elseif (
            in_array($settlementStatus, ['refund_pending'], true)
            && in_array($refundStatus, ['pending_choice', 'processing'], true)
            && $refundTotalMinor === $totalMinor
        ) {
            $requestedMinor = $feesMinor;
            $reason = 'pending_full_refund_requested';
        } elseif (
            in_array($cancelledBy, ['coach', 'admin', 'system'], true)
            && $refundTotalMinor === $totalMinor
        ) {
            $requestedMinor = $feesMinor;
            $reason = 'cancel_by_platform_side_full_refund';
        } else {
            $requestedMinor = 0;
        }

        // -----------------------------------
        // 2) Actual fee refunded
        // -----------------------------------
        if ($settlementStatus === 'refunded' && $isFullRefundRequested) {
            // fully refunded => fee refunded too
            $refundedMinor = $feesMinor;
            $reason .= '|full_refund_completed';
        } elseif ($settlementStatus === 'refunded_partial' && $isPartialRefundRequested) {
            /**
             * Infer fee component conservatively:
             * If actual refund exceeds subtotal, that excess can only be fee refund.
             *
             * Example:
             * subtotal = 10000
             * fees     = 1000
             * refund   = 10500
             * => fee refunded = 500
             *
             * If refund <= subtotal => no fee refunded.
             */
            $possibleFeeRefund = max(0, $refundTotalMinor - $subtotalMinor);
            $refundedMinor = min($feesMinor, $possibleFeeRefund);
            $reason .= '|partial_refund_inferred';
        } elseif ($settlementStatus === 'refund_pending' && $refundStatus === 'succeeded') {
            // historical inconsistent state fallback
            if ($isFullRefundRequested) {
                $refundedMinor = $feesMinor;
                $reason .= '|pending_state_but_succeeded_full';
            } elseif ($isPartialRefundRequested) {
                $possibleFeeRefund = max(0, $refundTotalMinor - $subtotalMinor);
                $refundedMinor = min($feesMinor, $possibleFeeRefund);
                $reason .= '|pending_state_but_succeeded_partial';
            }
        } else {
            $refundedMinor = 0;
        }

        // Safety clamp
        $requestedMinor = max(0, min($feesMinor, $requestedMinor));
        $refundedMinor  = max(0, min($requestedMinor > 0 ? $requestedMinor : $feesMinor, $refundedMinor));

        return [$requestedMinor, $refundedMinor, $reason];
    }
}