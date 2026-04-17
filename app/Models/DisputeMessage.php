<?php

namespace App\Models;


class DisputeMessage extends BaseModel
{
    protected $fillable = [
  'dispute_id',
    'sender_user_id',
    'sender_role',
    'message',
    'target_role',
    'channel',
    'thread',

];
protected $casts = [
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
];



    public function dispute() { return $this->belongsTo(Dispute::class); }
    public function sender() { return $this->belongsTo(Users::class, 'sender_user_id'); }
    public function attachments() { return $this->hasMany(DisputeAttachment::class, 'message_id'); }
}
