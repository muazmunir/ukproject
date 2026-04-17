<?php
// app/Models/StaffDeletionAudit.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffDeletionAudit extends Model
{
    protected $fillable = [
        'user_id','performed_by','reason',
        'image_path','image_original_name','image_size','image_mime',
        'ip','user_agent',
    ];

    public function user() { return $this->belongsTo(Users::class, 'user_id'); }
    public function performer() { return $this->belongsTo(Users::class, 'performed_by'); }
}
