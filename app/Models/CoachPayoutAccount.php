<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoachPayoutAccount extends BaseModel
{
    protected $fillable = [
        'coach_profile_id',
        'provider',
        'provider_account_id',
        'status',
        'country',
        'default_currency',
        'charges_enabled',
        'payouts_enabled',
        'onboarding_started_at',
        'onboarding_completed_at',
        'verified_at',
        'requirements_currently_due',
        'requirements_eventually_due',
        'requirements_past_due',
        'capabilities',
        'raw_provider_payload',
        'is_default',
    ];

    protected $casts = [
        'charges_enabled' => 'boolean',
        'payouts_enabled' => 'boolean',
        'is_default' => 'boolean',
        'onboarding_started_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'requirements_currently_due' => 'array',
        'requirements_eventually_due' => 'array',
        'requirements_past_due' => 'array',
        'capabilities' => 'array',
        'raw_provider_payload' => 'array',
    ];

    public function coachProfile(): BelongsTo
    {
        return $this->belongsTo(CoachProfile::class);
    }

    public function payoutMethods(): HasMany
    {
        return $this->hasMany(CoachPayoutMethod::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(CoachPayout::class, 'coach_payout_account_id');
    }
}