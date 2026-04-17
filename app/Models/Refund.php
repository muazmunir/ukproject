<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends BaseModel
{
    protected $fillable = [
        'reservation_id',
        'payment_id',
        'requested_by_user_id',

        'provider',
        'method',

        'requested_amount_minor',
        'actual_amount_minor',
        'wallet_amount_minor',
        'external_amount_minor',

        'currency',

        'status',
        'wallet_status',
        'external_status',

        'provider_order_id',
        'provider_capture_id',
        'provider_refund_id',

        'idempotency_key',
        'failure_reason',
        'meta',

        'requested_at',
        'processed_at',
        'refunded_to_wallet_minor',
'refunded_to_original_minor',
    ];

    protected $casts = [
        'requested_amount_minor' => 'integer',
        'actual_amount_minor'    => 'integer',
        'wallet_amount_minor'    => 'integer',
        'external_amount_minor'  => 'integer',
        'refunded_to_wallet_minor' => 'integer',
'refunded_to_original_minor' => 'integer',

        'meta'                   => 'array',
        'requested_at'           => 'datetime',
        'processed_at'           => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
