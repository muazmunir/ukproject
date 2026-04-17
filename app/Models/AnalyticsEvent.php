<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'coach_id',
        'type',
        'user_id',
        'client_id',
        'service_id',
        'reservation_id',
        'payment_id',
        'session_id',
        'visitor_token',
        'event_group',
        'page',
        'url',
        'method',
        'ip',
        'user_agent',
        'device_type',
        'platform',
        'country',
        'city',
        'meta',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}