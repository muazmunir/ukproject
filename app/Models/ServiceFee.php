<?php

namespace App\Models;


class ServiceFee extends BaseModel
{
    protected $table = 'service_fees';

    protected $fillable = [
        'slug',
        'label',
        'party',
        'type',
        'amount',
        'is_active',
    ];
}
