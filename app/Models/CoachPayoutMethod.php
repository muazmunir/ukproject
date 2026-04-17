<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachPayoutMethod extends Model
{
    protected $fillable = [
        'coach_payout_account_id',
        'provider',
        'provider_external_account_id',
        'type',
        'brand',
        'bank_name',
        'last4',
        'country',
        'currency',
        'is_default',
        'status',
        'raw_provider_payload',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'raw_provider_payload' => 'array',
    ];

    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(CoachPayoutAccount::class, 'coach_payout_account_id');
    }
}