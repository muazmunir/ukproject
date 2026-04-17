<?php
namespace App\Models;


class AgentAbsenceAudit extends BaseModel
{
  public $timestamps = false;
  protected $table = 'agent_absence_audits';

  protected $fillable = ['agent_id','actor_id','request_id','action','meta','ip','user_agent','created_at'];
  protected $casts = ['meta' => 'array','created_at' => 'datetime'];

  public function agent() { return $this->belongsTo(Users::class, 'agent_id'); }
  public function actor() { return $this->belongsTo(Users::class, 'actor_id'); }

  public function hasFile(): bool
{
  return (bool) $this->file_path;
}

public function fileUrl(): ?string
{
  if (!$this->file_path) return null;
  return \Illuminate\Support\Facades\Storage::disk($this->file_disk ?: 'public')->url($this->file_path);
}

}
