<?php
// app/Models/SupportConversationRead.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportConversationRead extends Model
{
  protected $fillable = [
    'support_conversation_id',
    'admin_id',
    'last_read_message_id',
    'last_read_at',
  ];
}
