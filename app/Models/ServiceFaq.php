<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceFaq extends Model
{
    use HasFactory;

    // Table name is `service_faqs` by convention, no need to set $table

    protected $fillable = [
        'service_id',
        'question',
        'answer',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Each FAQ belongs to a Service
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Scope: order by sort_order then id
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Ensure sort_order is never null (optional convenience)
     */
    protected static function booted(): void
    {
        static::creating(function ($faq) {
            if ($faq->sort_order === null) {
                // place to the end by default
                $max = static::where('service_id', $faq->service_id)->max('sort_order');
                $faq->sort_order = is_null($max) ? 0 : $max + 1;
            }
        });
    }
}
