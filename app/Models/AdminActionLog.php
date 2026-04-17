<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActionLog extends Model
{
    protected $fillable = [
        'admin_user_id','action','target_type','target_id','meta','ip','user_agent'
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function admin()
    {
        return $this->belongsTo(Users::class, 'admin_user_id');
    }
}
