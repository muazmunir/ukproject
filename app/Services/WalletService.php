<?php

namespace App\Services;

use App\Models\Users;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WalletService
{
    public const BAL_PLATFORM   = 'platform_credit';
    public const BAL_WITHDRAW   = 'withdrawable';
    public const BAL_ESCROW     = 'pending_escrow';

    public function credit(
        int $userId,
        int $amountMinor,
        string $reason,
        ?int $reservationId = null,
        ?int $paymentId = null,
        array $meta = [],
        string $currency = 'USD',
        string $balanceType = self::BAL_PLATFORM,
        bool $allowNegative = true,
    ): int {
        if ($amountMinor <= 0) {
            return $this->balance($userId, $balanceType);
        }

        return DB::transaction(function () use (
            $userId, $amountMinor, $reason, $reservationId, $paymentId, $meta, $currency, $balanceType
        ) {
            $user = Users::lockForUpdate()->findOrFail($userId);

            $col = $this->balanceColumn($balanceType);
            $user->{$col} = (int) ($user->{$col} ?? 0) + $amountMinor;
            $user->save();

            WalletTransaction::create([
                'user_id'             => $userId,
                'type'                => 'credit',
                'balance_type'        => $balanceType,
                'reason'              => $reason,
                'payment_id'          => $paymentId,
                'reservation_id'      => $reservationId,
                'amount_minor'        => $amountMinor,
                'balance_after_minor' => (int) $user->{$col},
                'currency'            => $currency,
                'meta'                => !empty($meta) ? $meta : null,
            ]);

            return (int) $user->{$col};
        });
    }

    public function debit(
        int $userId,
        int $amountMinor,
        string $reason,
        ?int $reservationId = null,
        ?int $paymentId = null,
        array $meta = [],
        string $currency = 'USD',
        string $balanceType = self::BAL_PLATFORM,
        bool $allowNegative = true,
    ): int {
        if ($amountMinor <= 0) {
            return $this->balance($userId, $balanceType);
        }

        return DB::transaction(function () use (
            $userId, $amountMinor, $reason, $reservationId, $paymentId, $meta, $currency, $balanceType, $allowNegative
        ) {
            $user = Users::lockForUpdate()->findOrFail($userId);

            $col = $this->balanceColumn($balanceType);
            $current = (int) ($user->{$col} ?? 0);

            if (!$allowNegative && $current < $amountMinor) {
                throw new InvalidArgumentException("Insufficient balance for {$balanceType}");
            }

            $user->{$col} = $current - $amountMinor;
            $user->save();

            WalletTransaction::create([
                'user_id'             => $userId,
                'type'                => 'debit',
                'balance_type'        => $balanceType,
                'reason'              => $reason,
                'payment_id'          => $paymentId,
                'reservation_id'      => $reservationId,
                'amount_minor'        => $amountMinor,
                'balance_after_minor' => (int) $user->{$col},
                'currency'            => $currency,
                'meta'                => !empty($meta) ? $meta : null,
            ]);

            return (int) $user->{$col};
        });
    }

    public function creditPlatform(
        int $userId,
        int $amountMinor,
        string $reason,
        ?int $reservationId = null,
        ?int $paymentId = null,
        array $meta = [],
        string $currency = 'USD',
    ): int {
        return $this->credit(
            $userId,
            $amountMinor,
            $reason,
            $reservationId,
            $paymentId,
            $meta,
            $currency,
            self::BAL_PLATFORM
        );
    }

    public function creditWithdrawable(
        int $userId,
        int $amountMinor,
        string $reason,
        ?int $reservationId = null,
        ?int $paymentId = null,
        array $meta = [],
        string $currency = 'USD',
    ): int {
        return $this->credit(
            $userId,
            $amountMinor,
            $reason,
            $reservationId,
            $paymentId,
            $meta,
            $currency,
            self::BAL_WITHDRAW
        );
    }

    public function creditPendingEscrow(
        int $userId,
        int $amountMinor,
        string $reason,
        ?int $reservationId = null,
        ?int $paymentId = null,
        array $meta = [],
        string $currency = 'USD',
    ): int {
        return $this->credit(
            $userId,
            $amountMinor,
            $reason,
            $reservationId,
            $paymentId,
            $meta,
            $currency,
            self::BAL_ESCROW
        );
    }

    public function balance(int $userId, string $balanceType = self::BAL_PLATFORM): int
    {
        $col = $this->balanceColumn($balanceType);

        return (int) (Users::whereKey($userId)->value($col) ?? 0);
    }

    private function balanceColumn(string $balanceType): string
    {
        $bt = strtolower(trim($balanceType));

        return match ($bt) {
            self::BAL_PLATFORM => 'platform_credit_minor',
            self::BAL_WITHDRAW => 'withdrawable_minor',
            self::BAL_ESCROW   => 'pending_escrow_minor',
            default            => throw new InvalidArgumentException("Unknown balance_type: {$balanceType}"),
        };
    }

    public function holdPlatformCredit(
        int $userId,
        int $amountMinor,
        string $reason = 'reservation_hold',
        ?int $reservationId = null,
        ?int $paymentId = null,
        array $meta = [],
        string $currency = 'USD',
    ): ?int {
        return $this->holdBalance(
            userId: $userId,
            amountMinor: $amountMinor,
            balanceType: self::BAL_PLATFORM,
            reason: $reason,
            reservationId: $reservationId,
            paymentId: $paymentId,
            meta: $meta,
            currency: $currency
        );
    }

    public function holdWithdrawableForPayout(
        int $userId,
        int $amountMinor,
        string $reason = 'coach_payout_reserved',
        ?int $reservationId = null,
        ?int $paymentId = null,
        array $meta = [],
        string $currency = 'USD',
    ): ?int {
        return $this->holdBalance(
            userId: $userId,
            amountMinor: $amountMinor,
            balanceType: self::BAL_WITHDRAW,
            reason: $reason,
            reservationId: $reservationId,
            paymentId: $paymentId,
            meta: $meta,
            currency: $currency
        );
    }

    private function holdBalance(
        int $userId,
        int $amountMinor,
        string $balanceType,
        string $reason,
        ?int $reservationId = null,
        ?int $paymentId = null,
        array $meta = [],
        string $currency = 'USD',
    ): ?int {
        if ($amountMinor <= 0) {
            return null;
        }

        return DB::transaction(function () use (
            $userId, $amountMinor, $balanceType, $reason, $reservationId, $paymentId, $meta, $currency
        ) {
            $user = Users::lockForUpdate()->findOrFail($userId);

            $col = $this->balanceColumn($balanceType);
            $current = (int) ($user->{$col} ?? 0);

            if ($current < $amountMinor) {
                throw new InvalidArgumentException("Insufficient balance for {$balanceType}");
            }

            $user->{$col} = $current - $amountMinor;
            $user->save();

            $tx = WalletTransaction::create([
                'user_id'             => $userId,
                'type'                => 'debit',
                'status'              => 'hold',
                'balance_type'        => $balanceType,
                'reason'              => $reason,
                'payment_id'          => $paymentId,
                'reservation_id'      => $reservationId,
                'amount_minor'        => $amountMinor,
                'balance_after_minor' => (int) $user->{$col},
                'currency'            => $currency,
                'meta'                => array_merge($meta, [
                    'hold_amount_minor' => $amountMinor,
                    'hold_created_at'   => now()->toIso8601String(),
                ]),
            ]);

            return (int) $tx->id;
        });
    }

    public function postHold(int $walletTxId): array
    {
        return DB::transaction(function () use ($walletTxId) {
            $tx = WalletTransaction::lockForUpdate()->find($walletTxId);

            if (!$tx) {
                return ['ok' => false, 'status' => 'missing'];
            }

            if (($tx->status ?? 'posted') === 'posted') {
                return ['ok' => true, 'status' => 'already_posted'];
            }

            if (($tx->status ?? '') === 'reversed') {
                return ['ok' => false, 'status' => 'already_reversed'];
            }

            if (($tx->status ?? '') !== 'hold') {
                return ['ok' => false, 'status' => 'invalid_state'];
            }

            $tx->status = 'posted';
            $tx->meta = array_merge((array) $tx->meta, [
                'posted_at' => now()->toIso8601String(),
            ]);
            $tx->save();

            return ['ok' => true, 'status' => 'posted'];
        });
    }

    public function postWithdrawablePayoutHold(int $walletTxId): array
    {
        return $this->postHold($walletTxId);
    }

    public function reverseHold(int $walletTxId, string $reason = 'reservation_hold_reversed'): array
    {
        return DB::transaction(function () use ($walletTxId, $reason) {
            $tx = WalletTransaction::lockForUpdate()->find($walletTxId);

            if (!$tx) {
                return ['ok' => false, 'status' => 'missing'];
            }

            if (($tx->status ?? '') === 'reversed') {
                return ['ok' => true, 'status' => 'already_reversed'];
            }

            if (($tx->status ?? '') === 'posted') {
                return ['ok' => false, 'status' => 'already_posted'];
            }

            if (($tx->status ?? '') !== 'hold') {
                return ['ok' => false, 'status' => 'invalid_state'];
            }

            $user = Users::lockForUpdate()->findOrFail($tx->user_id);

            $col = $this->balanceColumn($tx->balance_type);
            $current = (int) ($user->{$col} ?? 0);

            $amount = (int) $tx->amount_minor;
            $user->{$col} = $current + $amount;
            $user->save();

            $tx->status = 'reversed';
            $tx->meta = array_merge((array) $tx->meta, [
                'reversed_at'    => now()->toIso8601String(),
                'reverse_reason' => $reason,
            ]);
            $tx->save();

            WalletTransaction::create([
                'user_id'             => $tx->user_id,
                'type'                => 'credit',
                'status'              => 'posted',
                'balance_type'        => $tx->balance_type,
                'reason'              => $reason,
                'payment_id'          => $tx->payment_id,
                'reservation_id'      => $tx->reservation_id,
                'amount_minor'        => $amount,
                'balance_after_minor' => (int) $user->{$col},
                'currency'            => $tx->currency ?? 'USD',
                'meta'                => array_merge((array) $tx->meta, [
                    'reversal_of_wallet_tx_id' => $tx->id,
                ]),
            ]);

            return ['ok' => true, 'status' => 'reversed'];
        });
    }

    public function reverseWithdrawablePayoutHold(
        int $walletTxId,
        string $reason = 'coach_payout_reservation_reversed'
    ): array {
        return $this->reverseHold($walletTxId, $reason);
    }
}