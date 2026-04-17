<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachVerificationDocument extends BaseModel
{
    protected $fillable = [
        'coach_profile_id',
        'document_type',
        'storage_disk',
        'storage_path',
        'mime_type',
        'size_bytes',
        'status',
    ];

    public function coachProfile(): BelongsTo
    {
        return $this->belongsTo(CoachProfile::class);
    }

    public function getUrlAttribute(): ?string
    {
        if (!$this->storage_path) {
            return null;
        }

        return \Storage::disk($this->storage_disk ?: 'public')->url($this->storage_path);
    }
}
