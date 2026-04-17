<?php
// app/Models/CoachAvailabilityOverride.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachAvailabilityOverride extends BaseModel
{
    protected $fillable = ['coach_id','start_utc','end_utc','reason'];
    protected $casts = [
        'start_utc' => 'datetime',
        'end_utc'   => 'datetime',
    ];
}
