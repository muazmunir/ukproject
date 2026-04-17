<?php

// app/Models/City.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends BaseModel
{
    protected $table = 'cities';
    // no fillable needed for reading
}
