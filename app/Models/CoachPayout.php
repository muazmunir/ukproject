<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoachPayout extends Model
{
    protected $fillable = [
        'payout_batch_id',
        'coach_profile_id',
        'coach_payout_account_id',
        'provider',
        'currency',
        'amount_minor',
        'reservation_count',
        'status',
        'provider_transfer_id',
        'provider_payout_id',
        'provider_balance_txn_id',
        'paid_at',
        'failed_at',
        'failure_reason',
        'meta',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'reservation_count' => 'integer',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function payoutRun(): BelongsTo
    {
        return $this->belongsTo(PayoutRun::class, 'payout_batch_id');
    }

    public function coachProfile(): BelongsTo
    {
        return $this->belongsTo(CoachProfile::class);
    }

    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(CoachPayoutAccount::class, 'coach_payout_account_id');
    }

    public function payoutItems(): HasMany
    {
        return $this->hasMany(CoachPayoutItem::class);
    }

    public function getAmountAttribute(): float
    {
        return ((int) $this->amount_minor) / 100;
    }
}