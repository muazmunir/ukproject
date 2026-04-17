<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffInvite extends BaseModel
{
    protected $fillable = [
        'user_id','token','expires_at','used_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id');
    }

    public function isExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }

    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }
}
