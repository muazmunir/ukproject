<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'sort_order',
        'is_active',
    ];

    /**
     * Casts for convenience
     */
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Relationship: Category has many Services
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }

    /**
     * Scope: Active only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Accessor: Proper display name
     */
    public function getDisplayNameAttribute(): string
    {
        return ucfirst($this->name);
    }

    /**
     * Mutator: Auto-generate slug if not set
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if (empty($model->slug) && !empty($model->name)) {
                $model->slug = \Str::slug($model->name);
            }
        });
    }
}
