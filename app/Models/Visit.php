<?php

// app/Models/Visit.php

namespace App\Models;


class Visit extends BaseModel
{
    protected $fillable = [
        'visitor_id',
        'user_id',
        'ip',
        'user_agent',
        'path',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(Users::class);
    }
}
