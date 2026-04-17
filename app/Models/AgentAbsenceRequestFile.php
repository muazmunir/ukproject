<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
