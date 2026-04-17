<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffDocument extends Model
{
    protected $table = 'staff_documents';

    protected $fillable = [
        'user_id',
        'category',          // government_id | additional
        'label',             // e.g. Passport, NI Number
        'value_text',        // e.g. QQ123456C (text-only)
        'file_path',
        'file_original_name',
        'file_size',
        'file_mime',
    ];

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id');
    }

    // helpers
    public function getUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }

    public function scopeGovernmentId($q)
    {
        return $q->where('category', 'government_id');
    }

    public function scopeAdditional($q)
    {
        return $q->where('category', 'additional');
    }
}
