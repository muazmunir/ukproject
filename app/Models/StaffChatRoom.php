<?php

namespace App\Models;


class StaffChatRoom extends BaseModel
{
  protected $fillable = [
    'room_type','name','team_id','last_message_at','last_message_id'
  ];

  public function users() {
    return $this->belongsToMany(Users::class, 'staff_chat_room_users', 'room_id', 'user_id')
      ->withPivot(['last_read_message_id','last_read_at'])
      ->withTimestamps();
  }

  public function messages() {
    return $this->hasMany(StaffChatMessage::class, 'room_id');
  }

  public function latestMessage() {
    return $this->belongsTo(StaffChatMessage::class, 'last_message_id');
  }
}
