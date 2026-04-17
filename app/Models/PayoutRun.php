<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class PayoutRun extends BaseModel
{
    protected $fillable = [
        'provider',
        'run_key',
        'scheduled_for',
        'started_at',
        'finished_at',
        'status',
        'total_coaches',
        'total_amount_minor',
        'success_count',
        'failed_count',
        'meta',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'total_coaches' => 'integer',
        'total_amount_minor' => 'integer',
        'success_count' => 'integer',
        'failed_count' => 'integer',
        'meta' => 'array',
    ];

    public function payouts(): HasMany
    {
        return $this->hasMany(CoachPayout::class, 'payout_batch_id');
    }
}
