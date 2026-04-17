<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',                 // credit|debit
        'status',               // hold|posted|reversed ✅ ADD
        'balance_type',         // platform_credit|withdrawable|pending_escrow ✅ ADD
        'reason',
        'payment_id',
        'reservation_id',
        'payout_id',
        'amount_minor',
        'balance_after_minor',
        'currency',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
