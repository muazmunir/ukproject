<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportQuestionAcknowledgement extends Model
{
  protected $fillable = [
    'support_question_id','admin_id','status','note'
  ];

  public function question()
  {
    return $this->belongsTo(SupportQuestion::class, 'support_question_id');
  }

  public function admin()
  {
    return $this->belongsTo(Users::class, 'admin_id');
  }
}
