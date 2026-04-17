<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachFavorite extends Model
{
    protected $fillable = [
        'user_id',
        'coach_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'user_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'coach_id');
    }
}
