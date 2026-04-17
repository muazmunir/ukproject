<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    protected $fillable = [
        'service_id',
        'package_id',
        'client_id',
        'coach_id',
        'client_tz',
        'environment',
        'note',

        'currency',
        'subtotal_minor',
        'fees_minor',
        'total_minor',
        'total_hours',
        'checkout_method',
'wallet_type',

        'status',
        'payment_status',
        'payment_intent_id',
        'provider',

        'service_title_snapshot',
        'package_name_snapshot',
        'package_hourly_rate',
        'package_total_price',
        'package_hours_per_day',
        'package_total_days',
        'package_total_hours',
        'priced_at',
        'booked_at',

        'coach_gross_minor',
        'coach_commission_minor',
        'platform_fee_minor',
        'platform_penalty_minor',
        'client_penalty_minor',
        'coach_penalty_minor',
        'platform_earned_minor',

        'refund_method',
        'refund_status',
        'refund_error',
        'refund_requested_at',
        'refund_processed_at',
        'refund_total_minor',
        'refund_wallet_minor',
        'refund_external_minor',

        'wallet_platform_credit_used_minor',
        'payable_minor',
        'funding_status',
        'wallet_hold_tx_id',

        'coach_fee_type',
        'coach_fee_amount',
        'coach_fee_minor',
        'coach_net_minor',

        'settlement_status',
        'cancelled_by',
        'cancelled_at',
        'completed_at',

        'platform_fee_refund_requested_minor',
        'platform_fee_refunded_minor',

        // payout lifecycle
        'earnings_status',
        'payout_status',
        'coach_payout_id',
        'earnings_released_at',
        'payout_queued_at',
        'payout_sent_at',
        'payout_provider',
        'provider_transfer_id',
        'provider_payout_id',
    ];

    protected $casts = [
        'total_hours' => 'decimal:2',
        'booked_at' => 'datetime',
        'priced_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',

        'platform_fee_refund_requested_minor' => 'integer',
        'platform_fee_refunded_minor' => 'integer',

        'coach_gross_minor' => 'integer',
        'coach_commission_minor' => 'integer',
        'platform_fee_minor' => 'integer',
        'platform_penalty_minor' => 'integer',
        'client_penalty_minor' => 'integer',
        'coach_penalty_minor' => 'integer',
        'platform_earned_minor' => 'integer',

        'wallet_platform_credit_used_minor' => 'integer',
        'payable_minor' => 'integer',
        'refund_total_minor' => 'integer',
        'refund_wallet_minor' => 'integer',
        'refund_external_minor' => 'integer',

        'coach_fee_amount' => 'decimal:2',
        'coach_fee_minor' => 'integer',
        'coach_net_minor' => 'integer',

        'refund_requested_at' => 'datetime',
        'refund_processed_at' => 'datetime',

        // payout lifecycle
        'coach_payout_id' => 'integer',
        'earnings_released_at' => 'datetime',
        'payout_queued_at' => 'datetime',
        'payout_sent_at' => 'datetime',

        'status' => 'string',
        'payment_status' => 'string',
        'refund_status' => 'string',
        'refund_error' => 'string',
        'settlement_status' => 'string',
        'funding_status' => 'string',
        'cancelled_by' => 'string',
        'earnings_status' => 'string',
        'payout_status' => 'string',
        'payout_provider' => 'string',
        'provider_transfer_id' => 'string',
        'provider_payout_id' => 'string',
    ];

    public function payment()
    {
        return $this->hasOne(Payment::class)
            ->whereIn('provider', ['stripe', 'paypal'])
            ->where('status', 'succeeded')
            ->latestOfMany();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class, 'package_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'client_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'coach_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ReservationSlot::class);
    }

    public function disputes()
    {
        return $this->hasMany(\App\Models\Dispute::class, 'reservation_id');
    }

    public function coachDispute()
    {
        return $this->hasOne(\App\Models\Dispute::class, 'reservation_id')
            ->where('opened_by_role', 'coach')
            ->latestOfMany();
    }

    public function clientDispute()
    {
        return $this->hasOne(\App\Models\Dispute::class, 'reservation_id')
            ->where('opened_by_role', 'client')
            ->latestOfMany();
    }

    public function dispute()
    {
        return $this->hasOne(\App\Models\Dispute::class, 'reservation_id')
            ->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function walletPayment()
    {
        return $this->hasOne(Payment::class, 'reservation_id')
            ->where('provider', 'wallet')
            ->where('status', 'succeeded')
            ->latestOfMany();
    }

    public function externalPayment()
    {
        return $this->hasOne(Payment::class, 'reservation_id')
            ->whereIn('provider', ['stripe', 'paypal'])
            ->where('status', 'succeeded')
            ->latestOfMany();
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(\App\Models\Refund::class);
    }

    public function latestRefund()
    {
        return $this->hasOne(\App\Models\Refund::class)->latestOfMany();
    }

    public function coachReview()
    {
        return $this->hasOne(\App\Models\ReservationReview::class, 'reservation_id')
            ->where('reviewer_role', 'coach')
            ->where('reviewee_role', 'client');
    }

    public function clientReview()
    {
        return $this->hasOne(\App\Models\ReservationReview::class, 'reservation_id')
            ->where('reviewer_role', 'client')
            ->where('reviewee_role', 'coach');
    }

    public function coachPayout(): BelongsTo
    {
        return $this->belongsTo(CoachPayout::class, 'coach_payout_id');
    }

    public function payoutItems(): HasMany
    {
        return $this->hasMany(CoachPayoutItem::class);
    }

    public function fundingLabel(): string
    {
        $wallet = $this->walletPaidMinor();
        $external = $this->externalPaidMinor();

        $externalProvider = strtolower((string) ($this->externalPayment?->provider ?? $this->payment?->provider ?? ''));
        $externalName = match ($externalProvider) {
            'stripe' => 'Stripe',
            'paypal' => 'PayPal',
            default  => 'Card',
        };

        if ($wallet > 0 && $external > 0) {
            return "Wallet + {$externalName}";
        }

        if ($wallet > 0) {
            return 'Wallet';
        }

        if ($external > 0) {
            return $externalName;
        }

        return 'Unknown';
    }

    public function fundingColor(): string
    {
        return match ($this->funding_status) {
            'wallet_only'   => 'info',
            'mixed'         => 'primary',
            'external_only' => 'success',
            default         => 'secondary',
        };
    }

    public function getIsCancellableAttribute(): bool
    {
        $this->loadMissing('slots');

        return app(\App\Services\CancellationService::class)->canCancel($this);
    }

    public function total(): float
    {
        return ($this->total_minor ?? 0) / 100;
    }

    public function subtotal(): float
    {
        return ($this->subtotal_minor ?? 0) / 100;
    }

    public function fees(): float
    {
        return ($this->fees_minor ?? 0) / 100;
    }

    public function localSpan(?string $tz = null): array
    {
        $tz = $tz ?: ($this->client_tz ?: config('app.timezone', 'UTC'));
        $first = $this->slots->sortBy('start_utc')->first();
        $last = $this->slots->sortByDesc('end_utc')->first();

        if (!$first || !$last) {
            return [null, null];
        }

        return [
            CarbonImmutable::parse($first->start_utc)->tz($tz),
            CarbonImmutable::parse($last->end_utc)->tz($tz),
        ];
    }

    public function localizedSlots(?string $tz = null): array
    {
        $tz = $tz ?: ($this->client_tz ?: config('app.timezone', 'UTC'));
        $out = [];

        foreach ($this->slots as $s) {
            $start = CarbonImmutable::parse($s->start_utc)->tz($tz);
            $end = CarbonImmutable::parse($s->end_utc)->tz($tz);
            $key = $start->toDateString();

            $out[$key][] = [
                'date'      => $start->toDateString(),
                'start'     => $start->format('H:i'),
                'end'       => $end->format('H:i'),
                'iso_start' => $start->toIso8601String(),
                'iso_end'   => $end->toIso8601String(),
            ];
        }

        ksort($out);

        return $out;
    }

    public function statusColor(): string
    {
        return match ($this->payment_status) {
            'paid'             => 'success',
            'requires_payment' => 'warning',
            'failed'           => 'danger',
            default            => 'secondary',
        };
    }

    public function walletPaidMinor(): int
    {
        if ($this->relationLoaded('payments')) {
            $sum = (int) $this->payments
                ->where('provider', 'wallet')
                ->where('status', 'succeeded')
                ->sum('amount_total');

            return $sum > 0 ? $sum : (int) ($this->wallet_platform_credit_used_minor ?? 0);
        }

        $sum = (int) $this->payments()
            ->where('provider', 'wallet')
            ->where('status', 'succeeded')
            ->sum('amount_total');

        return $sum > 0 ? $sum : (int) ($this->wallet_platform_credit_used_minor ?? 0);
    }

    public function externalPaidMinor(): int
    {
        if ($this->relationLoaded('payments')) {
            return (int) $this->payments
                ->whereIn('provider', ['stripe', 'paypal'])
                ->where('status', 'succeeded')
                ->sum('amount_total');
        }

        return (int) $this->payments()
            ->whereIn('provider', ['stripe', 'paypal'])
            ->where('status', 'succeeded')
            ->sum('amount_total');
    }

    public function totalPaidMinor(): int
    {
        return $this->walletPaidMinor() + $this->externalPaidMinor();
    }
}