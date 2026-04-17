<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Users;


class SupportConversationRating extends BaseModel
{
    protected $table = 'support_conversation_ratings';

    protected $fillable = [
        'support_conversation_id',
        'user_id',   // customer id
        'admin_id',  // who was rated (admin/manager)
        'stars',
        'feedback',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'support_conversation_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'user_id');
    }

    public function ratedAdmin(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'admin_id');
    }
}
