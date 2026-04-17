<?php
// app/Models/SupportConversationRead.php
namespace App\Models;


class SupportConversationRead extends BaseModel
{
  protected $fillable = [
    'support_conversation_id',
    'admin_id',
    'last_read_message_id',
    'last_read_at',
  ];
}
