<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CoachProfile extends Model
{
    protected $fillable = [
        'user_id',
        'application_status',
        'applied_at',
        'review_started_at',
        'approved_at',
        'rejected_at',
        'reviewed_by',
        'review_notes',
        'rejection_reason',
        'can_accept_bookings',
        'can_receive_payouts',
        'preferred_payout_provider',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'review_started_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'can_accept_bookings' => 'boolean',
        'can_receive_payouts' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CoachVerificationDocument::class);
    }

    public function payoutAccounts(): HasMany
    {
        return $this->hasMany(CoachPayoutAccount::class);
    }

    public function defaultPayoutAccount(): HasOne
    {
        return $this->hasOne(CoachPayoutAccount::class)
            ->where('is_default', true);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(CoachPayout::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(CoachWithdrawal::class);
    }
}