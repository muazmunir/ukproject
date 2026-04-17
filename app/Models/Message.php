<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends BaseModel
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'sender_role',
        'body',
        'read_at',
        'service_id',
    ];

    protected $dates = [
        'read_at',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'sender_id');
    }
    public function service()
{
    return $this->belongsTo(Service::class);
}
}
