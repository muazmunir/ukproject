<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\AgentAbsenceRequestFile;

class AgentAbsenceRequest extends Model
{
  protected $table = 'agent_absence_requests';

 protected $fillable = [
        'agent_id',
        'kind',          // ✅ REQUIRED
        'state',
        'type',
        'start_at',
        'end_at',
        'reason',
        'comments',
        'decided_by',
        'decided_at',
        'decision_note',
    ];

  protected $casts = [
    'start_at' => 'datetime',
    'end_at' => 'datetime',
    'decided_at' => 'datetime',
  ];

  
  public function agent() { return $this->belongsTo(Users::class, 'agent_id'); }
  public function decider() { return $this->belongsTo(Users::class, 'decided_by'); }

  public function hasProof(): bool
{
  return (bool) $this->proof_path;
}

public function files()
    {
        return $this->hasMany(AgentAbsenceRequestFile::class, 'request_id');
    }
public function proofUrl(): ?string
{
  if (!$this->proof_path) return null;
  return \Illuminate\Support\Facades\Storage::disk($this->proof_disk ?: 'public')->url($this->proof_path);
}

}
