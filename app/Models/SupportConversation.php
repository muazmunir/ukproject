<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportConversation extends Model
{
    protected $table = 'support_conversations';

    protected $fillable = [
        'user_id',
        'user_type',
        'scope_role',
        'thread_id',

        // legacy
        'assigned_admin_id',

        // ✅ new owner (admin OR manager)
        'assigned_staff_id',
        'assigned_staff_role', // admin|manager

        // escalation/session
        'manager_id',
        'manager_requested_at',
        'manager_requested_by',
        'manager_joined_at',
        'manager_ended_at',

        // state/meta
        'status',
        'last_message_at',

        // close/rating
        'closed_at',
        'closed_by',
        'closed_by_role',
        'rating_required',
        'auto_closed',
        'sla_started_at',
    ];

    protected $casts = [
        'manager_requested_at' => 'datetime',
        'manager_joined_at'    => 'datetime',
        'manager_ended_at'     => 'datetime',
        'last_message_at'      => 'datetime',
        'closed_at'            => 'datetime',
        'rating_required'      => 'boolean',
        'auto_closed'          => 'boolean',
        'sla_started_at' => 'datetime',
    ];

    /* =========================
     | Relationships
     * ========================= */

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'user_id');
    }

    // legacy (kept)
    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'assigned_admin_id');
    }

    // ✅ NEW: current handler (admin OR manager)
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'assigned_staff_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'manager_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'support_conversation_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(SupportConversationRating::class, 'support_conversation_id');
    }

    /* =========================
     | Scopes
     * ========================= */

    public function scopeOpen($q)
    {
        return $q->whereIn('status', ['open', 'waiting_manager']);
    }

    public function scopeClosed($q)
    {
        return $q->whereIn('status', ['closed', 'auto_closed']);
    }

    /* =========================
     | Helpers
     * ========================= */

    public function isEscalated(): bool
    {
        return !is_null($this->manager_requested_at);
    }

    public function isOwnedBy(Users $staff): bool
    {
        return (int)$this->assigned_staff_id === (int)$staff->id;
    }

    public function canReply(Users $staff): bool
    {
        return $this->status === 'open' && $this->isOwnedBy($staff);
    }
}
