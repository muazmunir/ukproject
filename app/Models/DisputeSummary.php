<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisputeSummary extends Model
{
    protected $fillable = [
        'dispute_id',
        'staff_id',
        'staff_role',
        'summary',
    ];

    public function dispute()
    {
        return $this->belongsTo(Dispute::class);
    }

    public function staff()
    {
        return $this->belongsTo(Users::class, 'staff_id');
    }
}