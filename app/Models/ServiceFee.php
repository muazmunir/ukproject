<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceFee extends Model
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
