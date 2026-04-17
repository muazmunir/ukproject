<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;


    




class ServicePackage extends Model
{
    use SoftDeletes;
    use HasFactory;

    // By default, Eloquent expects table name "service_packages"
    // so no need to set $table manually unless it’s different.

    protected $fillable = [
        'service_id',
        'name',
        'hours_per_day',
        'total_days',
        'total_hours',
        'hourly_rate',
        'total_price',
        'equipments',
        'description',
        'sort_order',
        
    ];

    protected $casts = [
        'hours_per_day' => 'decimal:2',
        'total_days'    => 'integer',
        'total_hours'   => 'decimal:2',
        'hourly_rate'   => 'decimal:2',
        'total_price'   => 'decimal:2',
        'sort_order'    => 'integer',
        'is_active'   => 'boolean',
        'archived_at' => 'datetime',
    ];

    /**
     * Each package belongs to a Service
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Scope: Order by sort_order then ID
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Automatically assign incremental sort_order per service
     */
    protected static function booted(): void
    {
        static::creating(function ($pkg) {
            if ($pkg->sort_order === null) {
                $max = static::where('service_id', $pkg->service_id)->max('sort_order');
                $pkg->sort_order = is_null($max) ? 0 : $max + 1;
            }
        });
    }

    /**
     * Accessor: formatted total price
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->total_price, 2) . ' USD';
    }
}
