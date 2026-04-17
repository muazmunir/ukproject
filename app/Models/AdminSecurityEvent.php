<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminSecurityEvent extends BaseModel
{
    protected $fillable = [
        'admin_user_id','type','status','message','meta','reviewed_at','reviewed_by'
    ];

    protected $casts = [
        'meta' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(Users::class, 'admin_user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(Users::class, 'reviewed_by');
    }
}
