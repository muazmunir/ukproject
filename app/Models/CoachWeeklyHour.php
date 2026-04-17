<?php
// app/Models/CoachWeeklyHour.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachWeeklyHour extends BaseModel
{
    protected $fillable = ['coach_id','weekday','start_time','end_time'];
}
