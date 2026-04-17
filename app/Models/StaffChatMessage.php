<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffChatMessage extends Model
{
  protected $fillable = ['room_id','user_id','body','type','meta','edited_at','deleted_at'];
  protected $casts = ['meta' => 'array','edited_at' => 'datetime','deleted_at' => 'datetime'];

  public function room() { return $this->belongsTo(StaffChatRoom::class, 'room_id'); }
  public function user() { return $this->belongsTo(Users::class, 'user_id'); }
  public function attachments() { return $this->hasMany(StaffChatAttachment::class, 'message_id'); }
}
