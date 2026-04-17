<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachWithdrawal extends Model
{
    protected $fillable = [
        'coach_profile_id',
        'coach_payout_account_id',
        'provider',
        'currency',
        'amount_minor',
        'status',
        'requested_at',
        'processed_at',
        'paid_at',
        'failed_at',
        'failure_reason',
        'provider_transfer_id',
        'provider_payout_id',
        'provider_balance_txn_id',
        'meta',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function coachProfile(): BelongsTo
    {
        return $this->belongsTo(CoachProfile::class);
    }

    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(CoachPayoutAccount::class, 'coach_payout_account_id');
    }

    public function getAmountAttribute(): float
    {
        return ((int) $this->amount_minor) / 100;
    }
}