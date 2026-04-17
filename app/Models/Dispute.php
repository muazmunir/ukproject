<?php

namespace App\Models;


class Dispute extends BaseModel
{
    protected $fillable = [
        'reservation_id',

        'opened_by_role', 'opened_by_user_id',
        'client_id', 'coach_id',

        'title_key', 'title_label',
        'description',

        'status',

        // ✅ assignment (source of truth)
        'assigned_staff_id', 'assigned_staff_role', 'assigned_at',

        // ✅ review timer + party activity
        'in_review_started_at',
        'last_party_message_at',

        // ✅ SLA
        'sla_started_at',
        'sla_total_seconds',

        // SLA (optional/legacy fields if you still keep them)
        'sla_first_response_due_at',
        'sla_resolution_due_at',
        'first_admin_response_at',

        // ✅ summary
        'latest_summary',
        'latest_summary_by_id',
        'latest_summary_at',

        // ✅ decision/finalization (staff, not admin)
        'decision_action',
        'decision_note',
        'decided_by_staff_id',
        'decided_at',

        // ✅ resolution/final status (staff, not admin)
        'resolved_by_staff_id',
        'resolved_at',

        'last_message_at',
    ];

    protected $casts = [
        'assigned_at'               => 'datetime',

        // review & activity
        'in_review_started_at'      => 'datetime',
        'last_party_message_at'     => 'datetime',

        // SLA
        'sla_total_seconds'         => 'integer',
        'sla_first_response_due_at' => 'datetime',
        'sla_resolution_due_at'     => 'datetime',
        'first_admin_response_at'   => 'datetime',
        'sla_started_at'            => 'datetime',

        // summary
        'latest_summary_at'         => 'datetime',

        // decision/resolution
        'decided_at'                => 'datetime',
        'resolved_at'               => 'datetime',

        // timestamps
        'created_at'                => 'datetime',
        'updated_at'                => 'datetime',
        'last_message_at'           => 'datetime',
    ];

    protected $appends = ['status_label'];

    public function getStatusLabelAttribute(): string
    {
        $st = strtolower((string) ($this->status ?? 'open'));

        return match ($st) {
            'in_review' => 'Pending / Unresolved',
            'opened'    => 'Opened',
            'open'      => 'Open',
            'resolved'  => 'Resolved',
            'rejected'  => 'Rejected',
            default     => ucwords(str_replace('_', ' ', $st)),
        };
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function messages()
    {
        return $this->hasMany(DisputeMessage::class);
    }

    public function attachments()
    {
        return $this->hasMany(DisputeAttachment::class);
    }

    public function opener()
    {
        return $this->belongsTo(\App\Models\Users::class, 'opened_by_user_id');
    }

    /** ✅ Current assignee (admin or manager) */
    public function assignedStaff()
    {
        return $this->belongsTo(\App\Models\Users::class, 'assigned_staff_id');
    }

    /** ✅ Summary history */
    public function summaries()
    {
        return $this->hasMany(\App\Models\DisputeSummary::class);
    }

    public function latestSummaryBy()
    {
        return $this->belongsTo(\App\Models\Users::class, 'latest_summary_by_id');
    }

    /** ✅ Decision maker (admin OR manager) */
    public function decidedBy()
    {
        return $this->belongsTo(\App\Models\Users::class, 'decided_by_staff_id');
    }

    /** ✅ Resolver (admin OR manager) */
    public function resolvedBy()
    {
        return $this->belongsTo(\App\Models\Users::class, 'resolved_by_staff_id');
    }
}
