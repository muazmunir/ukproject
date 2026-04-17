<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachPayoutItem extends BaseModel
{
    protected $fillable = [
        'coach_payout_id',
        'reservation_id',
        'gross_minor',
        'platform_fee_minor',
        'net_minor',
        'released_at',
        'meta',
    ];

    protected $casts = [
        'gross_minor' => 'integer',
        'platform_fee_minor' => 'integer',
        'net_minor' => 'integer',
        'released_at' => 'datetime',
        'meta' => 'array',
    ];

    public function payout(): BelongsTo
    {
        return $this->belongsTo(CoachPayout::class, 'coach_payout_id');
    }

    public function coachPayout(): BelongsTo
    {
        return $this->belongsTo(CoachPayout::class, 'coach_payout_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }
}
