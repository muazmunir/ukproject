<?php
namespace App\Models;


class AgentAbsenceRequestFile extends BaseModel
{
  protected $fillable = [
    'request_id','disk','path','original_name','mime','size'
  ];

  public function request()
  {
    return $this->belongsTo(AgentAbsenceRequest::class, 'request_id');
  }
}
