<?php
// app/Models/CoachWeeklyHour.php
namespace App\Models;


class CoachWeeklyHour extends BaseModel
{
    protected $fillable = ['coach_id','weekday','start_time','end_time'];
}
