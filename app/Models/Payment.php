<?php
// app/Models/Payment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends BaseModel
{
    protected $fillable = [
        'reservation_id',

        // provider identity
        'provider',
        'provider_payment_id',
        'provider_order_id',
        'provider_charge_id',
        'provider_capture_id',
        'provider_refund_id',
        // payment channel / wallet tracking
'payment_channel',
'wallet_type',
'network_brand',

        // payment state
        'method',
        'status',           // INTERNAL normalized status
        'provider_status',  // RAW provider status
        'currency',
        'payout_currency',

        // money
        'amount_total',
        'service_subtotal_minor',
        'client_fee_minor',
        'coach_fee_minor',
        'coach_earnings',
        'platform_fee',

        // refund tracking
        'refunded_minor',
        'refund_status',
        'refund_failure_reason',
        'refunded_at',

        // receipts / payload / webhooks
        'receipt_url',
        'meta',
        'last_webhook_event',
        'last_webhook_at',

        // timestamps
        'succeeded_at',
        'escrow_released_at',
    ];

    protected $casts = [
        'amount_total'            => 'integer',
        'service_subtotal_minor'  => 'integer',
        'client_fee_minor'        => 'integer',
        'coach_fee_minor'         => 'integer',
        'coach_earnings'          => 'integer',
        'platform_fee'            => 'integer',
        'refunded_minor'          => 'integer',

        'succeeded_at'            => 'datetime',
        'escrow_released_at'      => 'datetime',
        'refunded_at'             => 'datetime',
        'last_webhook_at'         => 'datetime',

        'meta'                    => 'array',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function remainingRefundableMinor(): int
    {
        $total = (int) ($this->amount_total ?? 0);
        $refunded = (int) ($this->refunded_minor ?? 0);

        return max(0, $total - $refunded);
    }

    public function isSucceeded(): bool
    {
        return strtolower((string) $this->status) === 'succeeded';
    }

    public function isPending(): bool
    {
        return in_array(strtolower((string) $this->status), ['pending', 'processing'], true);
    }

    public function isCancelled(): bool
    {
        return strtolower((string) $this->status) === 'cancelled';
    }

    public function isFailed(): bool
    {
        return strtolower((string) $this->status) === 'failed';
    }

    public function isRefunded(): bool
    {
        return (int) ($this->refunded_minor ?? 0) > 0;
    }

    public function markRefundAggregateStatus(): void
    {
        $captured = max(0, (int) ($this->amount_total ?? 0));
        $refunded = max(0, min((int) ($this->refunded_minor ?? 0), $captured));

        if ($refunded <= 0) {
            $this->refund_status = null;
            return;
        }

        if ($captured > 0 && $refunded >= $captured) {
            $this->refund_status = 'succeeded';
        } else {
            $this->refund_status = 'partial';
        }
    }
}