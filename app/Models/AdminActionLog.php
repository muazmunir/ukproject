<?php

namespace App\Models;


class AdminActionLog extends BaseModel
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
