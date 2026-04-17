<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffTeamMember extends BaseModel
{
  protected $fillable = ['team_id','agent_id','start_at','end_at'];

  protected $casts = [
    'start_at' => 'datetime',
    'end_at'   => 'datetime',
  ];

  public function team(): BelongsTo
  {
    return $this->belongsTo(StaffTeam::class, 'team_id');
  }

  public function agent(): BelongsTo
  {
    return $this->belongsTo(Users::class, 'agent_id');
  }
}
