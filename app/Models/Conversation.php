<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'coach_id',
        'client_id',
        'service_id',
        'last_message_at',
    ];

    protected $dates = [
        'last_message_at',
    ];
    // app/Models/Conversation.php
protected $casts = [
    'last_message_at' => 'datetime',
];


    public function coach(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'coach_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'client_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasMany
    {
        return $this->messages()->latest();
    }
}
