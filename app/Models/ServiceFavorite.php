<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceFavorite extends BaseModel
{
    protected $fillable = [
        'user_id',
        'service_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'user_id'); // or User::class if that's your auth model
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
