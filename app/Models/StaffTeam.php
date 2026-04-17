<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffTeam extends BaseModel
{
    use SoftDeletes;
  protected $fillable = ['name','manager_id','is_active','is_active'];

  public function manager(): BelongsTo
  {
    return $this->belongsTo(Users::class, 'manager_id');
  }

  public function members(): HasMany
  {
    return $this->hasMany(StaffTeamMember::class, 'team_id');
  }

  public function activeMembers(): HasMany
  {
    return $this->members()->whereNull('end_at');
  }
}
