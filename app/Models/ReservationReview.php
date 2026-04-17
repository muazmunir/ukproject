<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationReview extends BaseModel
{
    protected $fillable = [
        'reservation_id',
        'reviewer_id',
        'reviewee_id',
        'reviewer_role',
        'reviewee_role',
        'stars',
        'description',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(Users::class, 'reviewer_id');
    }

    public function reviewee()
    {
        return $this->belongsTo(Users::class, 'reviewee_id');
    }


    public function scopeForRevieweeRole($query, string $role)
{
    return $query->where('reviewee_role', strtolower(trim($role)));
}

public function scopeForReviewerRole($query, string $role)
{
    return $query->where('reviewer_role', strtolower(trim($role)));
}
}