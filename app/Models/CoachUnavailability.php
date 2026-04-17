<?php
// app/Models/CoachUnavailability.php
namespace App\Models;


class CoachUnavailability extends BaseModel
{
    protected $fillable = ['coach_id','start_utc','end_utc','reason'];
    protected $casts = [
        'start_utc' => 'datetime',
        'end_utc'   => 'datetime',
    ];
}
