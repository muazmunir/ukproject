<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportQuestionMessage extends Model
{
  protected $fillable = [
    'support_question_id','sender_id','sender_role','body','type','meta'
  ];

  protected $casts = ['meta' => 'array'];

  public function question()
  {
    return $this->belongsTo(SupportQuestion::class, 'support_question_id');
  }

  public function sender()
  {
    return $this->belongsTo(Users::class, 'sender_id');
  }
}
