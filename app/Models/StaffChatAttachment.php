<?php

namespace App\Models;


class StaffChatAttachment extends BaseModel
{
  protected $fillable = ['message_id','disk','path','name','mime','size'];

  public function message() {
    return $this->belongsTo(StaffChatMessage::class, 'message_id');
  }
}

