<?php
namespace App\Models;


class SupportQuestion extends BaseModel
{
  protected $fillable = [
    'asked_by_admin_id','assigned_manager_id','title','question',
    'status','answered_at' ,'taken_at','acknowledged_at','closed_at'
  ];

  protected $casts = [
    'answered_at' => 'datetime',
    'acknowledged_at' => 'datetime',
    'closed_at' => 'datetime',
    'taken_at' => 'datetime',
    
  ];

  public function askedBy()
  {
    return $this->belongsTo(Users::class, 'asked_by_admin_id');
  }

  public function assignedManager()
  {
    return $this->belongsTo(Users::class, 'assigned_manager_id');
  }

  public function messages()
  {
    return $this->hasMany(SupportQuestionMessage::class, 'support_question_id');
  }

  public function acknowledgements()
  {
    return $this->hasMany(SupportQuestionAcknowledgement::class, 'support_question_id');
  }
}
