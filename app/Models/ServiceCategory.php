<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends BaseModel
{
    use HasFactory;

    protected $table = 'service_categories';

    protected $fillable = [
        'name','slug','description','cover_image','icon_path','sort_order','is_active','show_in_scrollbar',
      ];

    protected $casts = [
        'is_active'  => 'boolean',
        'show_in_scrollbar' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }
    public function scopeActive($query)
{
    return $query->where('is_active', 1)
                 ->orderBy('sort_order')
                 ->orderBy('name');
}

}
