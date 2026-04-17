<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffDmMessage extends Model
{
  protected $fillable = ['thread_id','sender_id','body'];

  public function thread(): BelongsTo { return $this->belongsTo(StaffDmThread::class,'thread_id'); }
  public function sender(): BelongsTo { return $this->belongsTo(Users::class,'sender_id'); }
}
