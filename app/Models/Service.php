<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ServiceCategory;
use App\Models\Reservation;
use App\Models\Users; // <-- fix
use Illuminate\Database\Eloquent\SoftDeletes;


class Service extends BaseModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'coach_id','category_id','title','description','thumbnail_path','images',
        'environments','environment_other','accessibility','accessibility_other',
        'disability_accessible','service_level','is_active','meta','is_approved',
        'admin_disabled',
        'admin_disabled_at',
        'admin_disabled_reason',
    ];

    protected $casts = [
        'environments' => 'array',
        'accessibility' => 'array',
        'images' => 'array',
        'meta' => 'array',
        'is_active' => 'boolean',
        'disability_accessible' => 'boolean',
        'archived_at' => 'datetime',
        'approved_at' => 'datetime',
        'is_approved' => 'integer',   // or 'boolean' after you convert the column
        'admin_disabled_at'=> 'datetime',
        'admin_disabled'   => 'boolean',
  
    ];

    /** Relationships */
    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'coach_id')
            ->select(['id','first_name','last_name','username','avatar_path','city','country','timezone','email','phone']);
    }
    

    public function packages(): HasMany
    {
        return $this->hasMany(ServicePackage::class);
    }

    public function activePackages()
{
    return $this->hasMany(ServicePackage::class)->where('is_active', true);
}

    public function scopeLive($q)
    {
        return $q->where('is_active', 1)->where('is_approved', 1);
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(ServiceFaq::class);
    }

    public function gallery(): HasMany
    {
        return $this->hasMany(ServiceImage::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'service_id');
    }

    /** Scopes */
    public function scopeActive($q)
    {
        return $q->where('is_active', 1);
    }

    /** -------- Card helpers (to keep your markup the same) -------- */

    // Thumbnail full URL
    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path
            ? asset('storage/' . ltrim($this->thumbnail_path, '/'))
            : asset('assets/placeholder-service.png');
    }

    public function getLevelLabelAttribute(): string
    {
        return match ($this->service_level) {
            'beginner' => 'Beginner',
            'intermediate' => 'Intermediate',
            'advanced' => 'Advanced',
            default => ucfirst((string)$this->service_level),
        };
    }

    public function getCityNameAttribute(): string
    {
        return optional($this->coach)->city ?: '—';
    }

    public function getCoachAvatarUrlAttribute(): string
{
    $avatar = optional($this->coach)->avatar_path;

    // Case 1: Full URL (starts with http or https)
    if ($avatar && str_starts_with($avatar, ['http://', 'https://'])) {
        return $avatar;
    }

    // Case 2: Stored in assets/storage/avatars/
    if ($avatar && file_exists(public_path('assets/storage/avatars/' . ltrim($avatar, '/')))) {
        return asset('assets/storage/avatars/' . ltrim($avatar, '/'));
    }

    // Case 3: Default fallback
    return asset('assets/storage/avatars/default.png');
}


    // Read from meta first; otherwise derive from packages (min price)
    public function getPriceValueAttribute(): ?float
    {
        $metaPrice = data_get($this->meta, 'price');
        if (!is_null($metaPrice)) return (float) $metaPrice;

        if ($this->relationLoaded('packages')) {
            $min = $this->packages->pluck('total_price')->filter()->min()
                ?? $this->packages->pluck('hourly_rate')->filter()->min();
            return $min ? (float) $min : null;
        }
        return null;
    }

    public function getPriceUnitAttribute(): string
    {
        return (string) (data_get($this->meta, 'unit', 'Per Session'));
    }

    public function getRatingValueAttribute(): float
    {
        // plug your real ratings later; this makes UI happy today
        return (float) data_get($this->meta, 'rating', 0);
    }

    public function getBadgeAttribute(): ?string
    {
        return data_get($this->meta, 'badge');
    }

    public function getIsLikedAttribute(): bool
    {
        // Wire to favorites later; default false keeps your heart UI stable
        return false;
    }

    public function conversations()
{
    return $this->hasMany(Conversation::class);
}

public function favorites(): HasMany
    {
        return $this->hasMany(ServiceFavorite::class);
    }

    // Optional helper: is favorited by current user
    public function getIsFavoritedAttribute(): bool
    {
        $userId = auth()->id();
        if (!$userId) {
            return false;
        }

        // assumes favorites are eager-loaded; otherwise it will query per service
        return $this->favorites->contains('user_id', $userId);
    }
}
