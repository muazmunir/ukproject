<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffDmThread extends Model
{
  protected $fillable = [
    'manager_id','agent_id','is_active','last_message_id','last_message_at','agent_last_read_id','manager_last_read_id',
  ];

  protected $casts = [
    'is_active' => 'boolean',
    'last_message_at' => 'datetime',
  ];

  public function manager(): BelongsTo { return $this->belongsTo(Users::class,'manager_id'); }
  public function agent(): BelongsTo { return $this->belongsTo(Users::class,'agent_id'); }

  public function messages(): HasMany
  {
    return $this->hasMany(StaffDmMessage::class, 'thread_id');
  }
}
