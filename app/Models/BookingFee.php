<?php
// app/Models/BookingFee.php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class BookingFee extends BaseModel {
    protected $fillable = ['code','label','kind','value','applies_to','is_active','starts_at','ends_at'];

    protected $casts = [
        'is_active' => 'bool',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function scopeActive(Builder $q): Builder {
        $now = Carbon::now();
        return $q->where('is_active', true)
                 ->where(function($q) use ($now){
                     $q->whereNull('starts_at')->orWhere('starts_at','<=',$now);
                 })
                 ->where(function($q) use ($now){
                     $q->whereNull('ends_at')->orWhere('ends_at','>=',$now);
                 });
    }
}
