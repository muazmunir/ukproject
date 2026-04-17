<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffChatAttachment extends Model
{
  protected $fillable = ['message_id','disk','path','name','mime','size'];

  public function message() {
    return $this->belongsTo(StaffChatMessage::class, 'message_id');
  }
}

