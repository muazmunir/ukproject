<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends BaseModel
{
    protected $table = 'support_messages';

    protected $fillable = [
        'support_conversation_id',
        'sender_id',
        'sender_type',
        'body',
        'type',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'support_conversation_id');
    }

    public function sender(): BelongsTo
    {
        // sender_id always points to users.id (except system messages if you use 0)
        return $this->belongsTo(Users::class, 'sender_id');
    }
}
