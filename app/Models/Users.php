<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;
use Laragear\WebAuthn\WebAuthnData;
use App\Notifications\AdminResetPasswordNotification;

class Users extends Authenticatable implements WebAuthnAuthenticatable
{
    use Notifiable;
    use SoftDeletes;
    use WebAuthnAuthentication;

    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'dob',
        'email',
        'password',
        'country',
        'city',
        'phone_code',
        'phone',
        'timezone',
        'short_bio',
        'description',
        'languages',
        'coach_service_areas',
        'coach_gallery',
        'coach_qualifications',
        'facebook_url',
        'instagram_url',
        'linkedin_url',
        'twitter_url',
        'youtube_url',
        'avatar_path',
        'role',
        'is_client',
        'is_coach',
        'active_role',
        'platform_credit_minor',
        'withdrawable_minor',
        'pending_escrow_minor',
        'google_id',
        'google_token',
        'google_avatar',
        'onboarding_completed',
        'coach_rating_avg',
        'coach_rating_count',
        'client_rating_avg',
        'client_rating_count',
        'emergency_contact_name',
        'emergency_contact_phone',
        'next_of_kin_name',
        'next_of_kin_phone',
        'next_of_kin_address',
        'support_status_since',
        'shift_enabled',
        'shift_start',
        'shift_end',
        'shift_days',
        'shift_grace_minutes',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'languages' => 'array',
        'coach_service_areas' => 'array',
        'coach_gallery' => 'array',
        'coach_qualifications' => 'array',
        'is_approved' => 'integer',
        'approved_at' => 'datetime',
        'dob' => 'date',
        'is_client' => 'boolean',
        'is_coach' => 'boolean',
        'coach_kyc_submitted' => 'boolean',
        'platform_credit_minor' => 'integer',
        'withdrawable_minor' => 'integer',
        'pending_escrow_minor' => 'integer',
        'locked_at' => 'datetime',
        'soft_locked_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'admin_last_seen_at' => 'datetime',
        'admin_soft_locked_at' => 'datetime',
        'absence_start_at' => 'datetime',
        'absence_end_at' => 'datetime',
        'absence_set_at' => 'datetime',
        'support_status_since' => 'datetime',
        'shift_enabled' => 'boolean',
        'shift_days' => 'array',
        'started_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'coach_rating_avg' => 'decimal:2',
        'coach_rating_count' => 'integer',
        'client_rating_avg' => 'decimal:2',
        'client_rating_count' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($user) {
            if (empty($user->started_at)) {
                $user->started_at = now();
            }
        });
    }

    public function staffDocuments()
    {
        return $this->hasMany(StaffDocument::class, 'user_id');
    }

    public function staffGovernmentIdDocuments()
    {
        return $this->hasMany(StaffDocument::class, 'user_id')
            ->where('category', 'government_id');
    }

    public function staffAdditionalDocuments()
    {
        return $this->hasMany(StaffDocument::class, 'user_id')
            ->where('category', 'additional');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function getAvatarUrlAttribute()
    {
        return $this->avatar_path
            ? asset('storage/' . $this->avatar_path)
            : asset('assets/user.png');
    }

    public function coachConversations()
    {
        return $this->hasMany(Conversation::class, 'coach_id');
    }

    public function clientConversations()
    {
        return $this->hasMany(Conversation::class, 'client_id');
    }

    public function favoriteCoaches()
    {
        return $this->belongsToMany(
            Users::class,
            'coach_favorites',
            'user_id',
            'coach_id'
        );
    }

    public function favoritedByUsers()
    {
        return $this->belongsToMany(
            Users::class,
            'coach_favorites',
            'coach_id',
            'user_id'
        );
    }

    public function getWalletBalanceAttribute(): float
    {
        return ($this->platform_credit_minor ?? $this->wallet_balance_minor ?? 0) / 100;
    }

    public function getPlatformCreditAttribute(): float
    {
        return ((int) ($this->platform_credit_minor ?? 0)) / 100;
    }

    public function getWithdrawableBalanceAttribute(): float
    {
        return ((int) ($this->withdrawable_minor ?? 0)) / 100;
    }

    public function getPendingEscrowBalanceAttribute(): float
    {
        return ((int) ($this->pending_escrow_minor ?? 0)) / 100;
    }

    public function isCoach(): bool
    {
        return (bool) $this->is_coach;
    }

    public function isClient(): bool
    {
        return (bool) $this->is_client;
    }

    public function actingAs(string $role): bool
    {
        return strtolower((string) $this->active_role) === $role;
    }

    public function latestStaffInvite()
    {
        return $this->hasOne(\App\Models\StaffInvite::class, 'user_id')->latestOfMany();
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'Agent',
            'manager' => 'Manager',
            'superadmin' => 'Admin',
            default => ucfirst(str_replace('_', ' ', $this->role)),
        };
    }

    public function reviewsReceivedAsCoach()
    {
        return $this->hasMany(\App\Models\ReservationReview::class, 'reviewee_id')
            ->where('reviewee_role', 'coach');
    }

    public function reviewsReceivedAsClient()
    {
        return $this->hasMany(\App\Models\ReservationReview::class, 'reviewee_id')
            ->where('reviewee_role', 'client');
    }

    public function reviewsWrittenAsCoach()
    {
        return $this->hasMany(\App\Models\ReservationReview::class, 'reviewer_id')
            ->where('reviewer_role', 'coach');
    }

    public function reviewsWrittenAsClient()
    {
        return $this->hasMany(\App\Models\ReservationReview::class, 'reviewer_id')
            ->where('reviewer_role', 'client');
    }

    public function staffDeletionAudits()
    {
        return $this->hasMany(\App\Models\StaffDeletionAudit::class, 'user_id');
    }

    public function supportConversationsAsCustomer()
    {
        return $this->hasMany(\App\Models\SupportConversation::class, 'user_id');
    }

    public function supportConversationsAsAdmin()
    {
        return $this->hasMany(\App\Models\SupportConversation::class, 'assigned_admin_id');
    }

    public function supportConversationsAsManager()
    {
        return $this->hasMany(\App\Models\SupportConversation::class, 'manager_id');
    }

    public function agentStatusLogs()
    {
        return $this->hasMany(\App\Models\AgentStatusLog::class, 'user_id');
    }

    public function isSupportAvailable(): bool
    {
        return ($this->support_status ?? 'available') === 'available';
    }

    public function displayName(): string
    {
        return $this->username ?? '—';
    }

    public function assignedDisputes()
    {
        return $this->hasMany(\App\Models\Dispute::class, 'assigned_admin_id');
    }

    public function getCoachRatingAttribute(): float
    {
        return (float) ($this->coach_rating_avg ?? 0);
    }

    public function getClientRatingAttribute(): float
    {
        return (float) ($this->client_rating_avg ?? 0);
    }

    public function coachProfile()
    {
        return $this->hasOne(\App\Models\CoachProfile::class, 'user_id');
    }

    public function activeCoachPayoutAccount()
    {
        return $this->hasOneThrough(
            \App\Models\CoachPayoutAccount::class,
            \App\Models\CoachProfile::class,
            'user_id',
            'coach_profile_id',
            'id',
            'id'
        )->where('is_default', true);
    }

    public function walletTransactions()
    {
        return $this->hasMany(\App\Models\WalletTransaction::class, 'user_id');
    }

    public function coachPayouts()
    {
        return $this->hasManyThrough(
            \App\Models\CoachPayout::class,
            \App\Models\CoachProfile::class,
            'user_id',
            'coach_profile_id',
            'id',
            'id'
        );
    }

    public function isStaffPasskeyRole(): bool
    {
        return in_array(strtolower((string) $this->role), ['admin', 'manager'], true);
    }

    public function isActiveStaffAccount(): bool
    {
        return ! $this->trashed() && (bool) ($this->is_active ?? true);
    }

   public function webAuthnData(): WebAuthnData
{
    return WebAuthnData::make(
        $this->email ?: (string) $this->getAuthIdentifier(),
        $this->full_name ?: $this->username ?: $this->email ?: 'User'
    );
}

public function sendAdminPasswordResetNotification($token): void
{
    $this->notify(new AdminResetPasswordNotification($token));
}
}


