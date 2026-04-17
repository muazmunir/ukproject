<?php
// app/Http/Controllers/ReserveController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;

use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\ServiceFee;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Support\AnalyticsLogger;

use App\Services\WalletService;
use Illuminate\Support\Facades\Mail;
use App\Mail\BookingConfirmedClientMail;
use App\Mail\BookingConfirmedCoachMail;
use App\Models\Payment;

class ReserveController extends Controller
{
    public function show(Request $r)
    {
        $data = $r->validate([
            'service_id'        => ['required', 'integer', 'exists:services,id'],
            'package_id'        => ['required', 'integer', 'exists:service_packages,id'],
            'client_tz'         => ['nullable', 'string'],
            'days'              => ['required', 'array', 'min:1'],
            'days.*.date'       => ['required', 'date_format:Y-m-d'],
            'days.*.start'      => ['required', 'date'],
            'days.*.end'        => ['required', 'date'],
        ]);

        $service = Service::with(['coach', 'packages'])->findOrFail($data['service_id']);
        $package = ServicePackage::findOrFail($data['package_id']);

        if ($package->service_id !== $service->id) {
            abort(409, 'This package no longer belongs to the selected service.');
        }

        AnalyticsLogger::log($r, 'booking_page_visit', [
            'group'      => 'booking',
            'client_id'  => (int) ($r->user()?->id ?? 0),
            'coach_id'   => (int) ($service->coach_id ?? 0),
            'service_id' => (int) $service->id,
            'meta'       => [
                'package_id' => (int) $package->id,
                'days_count' => count($data['days'] ?? []),
                'client_tz'  => $data['client_tz'] ?? null,
            ],
        ]);

        // Build slots (UTC instants are already sent from availability/day as ISO Z)
        $slots = collect($data['days'])->map(function ($d) {
            $start = CarbonImmutable::parse($d['start'])->utc();
            $end   = CarbonImmutable::parse($d['end'])->utc();

            $minutes = $end->diffInRealMinutes($start, true);
            $hours   = $minutes / 60;

            return [
                'date'  => $d['date'],
                'start' => $start,
                'end'   => $end,
                'hours' => $hours,
            ];
        });

        $totalHours = round($slots->sum('hours'), 2);

        $base = 0.0;
        if (! is_null($package->total_price) && $package->total_price > 0) {
            $base = (float) $package->total_price;
        } elseif (! is_null($package->hourly_rate) && $package->hourly_rate > 0) {
            $base = (float) $package->hourly_rate * $totalHours;
        }

        $fees = ServiceFee::query()
            ->where('is_active', true)
            ->where('party', 'client')
            ->get();

        $feeLines = [];
        $percentSum = 0.0;
        $fixedSum   = 0.0;

        foreach ($fees as $fee) {
            if ($fee->type === 'percent') {
                $val = round($base * ((float) $fee->amount / 100), 2);
                $percentSum += $val;
                $feeLines[] = ['label' => $fee->label, 'value' => $val];
            } else {
                $val = round((float) $fee->amount, 2);
                $fixedSum += $val;
                $feeLines[] = ['label' => $fee->label, 'value' => $val];
            }
        }

        $subtotal = round($base, 2);

        $coachFeeRow = ServiceFee::where('is_active', true)
            ->where(function ($q) {
                $q->where('slug', 'coach_commission')
                  ->orWhere('party', 'coach');
            })
            ->first();

        $coachFeeType   = $coachFeeRow?->type ?: 'percent';
        $coachFeeAmount = (float) ($coachFeeRow?->amount ?? 0);

        $coachFeeValue = 0.0;
        if ($coachFeeRow) {
            $coachFeeValue = $coachFeeType === 'percent'
                ? round($subtotal * ($coachFeeAmount / 100), 2)
                : round($coachFeeAmount, 2);
        }

        $coachNet = max(0, round($subtotal - $coachFeeValue, 2));

        $clientPlatformFee = round($percentSum + $fixedSum, 2);
        $total = round($subtotal + $clientPlatformFee, 2);

        $pricingSnapshot = [
            'service_title'         => $service->title,
            'package_name'          => $package->name,
            'package_hourly_rate'   => $package->hourly_rate,
            'package_total_price'   => $package->total_price,
            'package_hours_per_day' => $package->hours_per_day,
            'package_total_days'    => $package->total_days,
            'package_total_hours'   => $package->total_hours,
            'subtotal'              => $subtotal,
            'fees'                  => $clientPlatformFee,
            'total'                 => $total,
            'currency'              => 'USD',
            'priced_at'             => now()->toIso8601String(),
        ];

        $environments = is_array($service->environments) ? $service->environments : [];

        return view('reserve.create', [
            'service'            => $service,
            'package'            => $package,
            'clientTz'           => $data['client_tz'] ?? 'UTC',
            'slots'              => $slots,
            'subtotal'           => $subtotal,
            'feeLines'           => $feeLines,
            'clientPlatformFee'  => $clientPlatformFee,
            'total'              => $total,
            'totalHours'         => $totalHours,
            'environments'       => $environments,
            'rawDays'            => $data['days'],
            'pricingSnapshot'    => $pricingSnapshot,
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'service_id'        => ['required', 'integer', 'exists:services,id'],
            'package_id'        => ['required', 'integer', 'exists:service_packages,id'],
            'client_tz'         => ['nullable', 'string'],
            'environment'       => ['nullable', 'string', 'max:120'],
            'note'              => ['nullable', 'string', 'max:2000'],
            'days'              => ['required', 'array', 'min:1'],
            'days.*.date'       => ['required', 'date_format:Y-m-d'],
            'days.*.start'      => ['required', 'date'],
            'days.*.end'        => ['required', 'date'],

            'use_platform_credit'         => ['required', 'boolean'],
            'payable_minor'               => ['nullable', 'integer', 'min:0'],
            'platform_credit_apply_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        if (! $r->boolean('use_platform_credit')) {
            AnalyticsLogger::log($r, 'wallet_checkout_blocked', [
                'group'      => 'booking',
                'client_id'  => (int) ($r->user()?->id ?? 0),
                'service_id' => (int) ($data['service_id'] ?? 0),
                'meta'       => [
                    'reason' => 'wallet_not_enabled',
                ],
            ]);

            return redirect()->back()->with('error', 'Please enable platform credit to pay with wallet.');
        }

        $service = Service::with('coach')->findOrFail($data['service_id']);
        $package = ServicePackage::findOrFail($data['package_id']);

        if ($package->service_id !== $service->id) {
            AnalyticsLogger::log($r, 'wallet_checkout_blocked', [
                'group'      => 'booking',
                'client_id'  => (int) ($r->user()?->id ?? 0),
                'coach_id'   => (int) ($service->coach_id ?? 0),
                'service_id' => (int) $service->id,
                'meta'       => [
                    'reason'     => 'invalid_package_for_service',
                    'package_id' => (int) $package->id,
                ],
            ]);

            return redirect()->back()->with('error', 'Selected package is not valid for this service.');
        }

        AnalyticsLogger::log($r, 'wallet_checkout_started', [
            'group'      => 'booking',
            'client_id'  => (int) ($r->user()?->id ?? 0),
            'coach_id'   => (int) ($service->coach_id ?? 0),
            'service_id' => (int) $service->id,
            'meta'       => [
                'package_id'          => (int) $package->id,
                'days_count'          => count($data['days'] ?? []),
                'use_platform_credit' => true,
            ],
        ]);

        $this->assertSlotsBookableOrFail($service, $data['days']);

        $totalHours = 0.0;
        foreach ($data['days'] as $d) {
            $start = CarbonImmutable::parse($d['start'])->utc();
            $end   = CarbonImmutable::parse($d['end'])->utc();
            $totalHours += $end->diffInRealMinutes($start, true) / 60;
        }
        $totalHours = round($totalHours, 2);

        $base = 0.0;
        if (! is_null($package->total_price) && $package->total_price > 0) {
            $base = (float) $package->total_price;
        } elseif (! is_null($package->hourly_rate) && $package->hourly_rate > 0) {
            $base = (float) $package->hourly_rate * $totalHours;
        }
        $base = round($base, 2);

        $feesRows = ServiceFee::query()
            ->where('is_active', true)
            ->where('party', 'client')
            ->get();

        $feeTotal = 0.0;
        foreach ($feesRows as $fee) {
            $feeTotal += $fee->type === 'percent'
                ? round($base * ((float) $fee->amount / 100), 2)
                : round((float) $fee->amount, 2);
        }
        $feeTotal = round($feeTotal, 2);

        $total = round($base + $feeTotal, 2);

        $subtotalMinor = (int) round($base * 100);
        $feesMinor     = (int) round($feeTotal * 100);
        $totalMinor    = (int) round($total * 100);

        $coachFeeRow = ServiceFee::where('is_active', true)
            ->where(function ($q) {
                $q->where('slug', 'coach_commission')
                  ->orWhere('party', 'coach');
            })
            ->first();

        $coachFeeType   = $coachFeeRow?->type ?: 'percent';
        $coachFeeAmount = (float) ($coachFeeRow?->amount ?? 0);

        $coachFeeMinor = 0;
        if ($coachFeeRow) {
            $coachFeeMinor = $coachFeeType === 'percent'
                ? (int) round($subtotalMinor * ($coachFeeAmount / 100))
                : (int) round($coachFeeAmount * 100);
        }

        $coachNetMinor = max(0, $subtotalMinor - $coachFeeMinor);

        $availMinor     = (int) (auth()->user()->platform_credit_minor ?? 0);
        $requestedApply = (int) ($data['platform_credit_apply_minor'] ?? 0);
        $walletUseMinor = min($totalMinor, $availMinor, $requestedApply);

        if ($walletUseMinor <= 0) {
            AnalyticsLogger::log($r, 'wallet_checkout_blocked', [
                'group'      => 'booking',
                'client_id'  => (int) ($r->user()?->id ?? 0),
                'coach_id'   => (int) ($service->coach_id ?? 0),
                'service_id' => (int) $service->id,
                'meta'       => [
                    'reason'               => 'insufficient_platform_credit',
                    'available_minor'      => (int) $availMinor,
                    'requested_apply_minor'=> (int) $requestedApply,
                    'total_minor'          => (int) $totalMinor,
                ],
            ]);

            return redirect()->back()->with('error', 'Insufficient platform credit.');
        }

        if ($walletUseMinor !== $totalMinor) {
            AnalyticsLogger::log($r, 'wallet_checkout_blocked', [
                'group'      => 'booking',
                'client_id'  => (int) ($r->user()?->id ?? 0),
                'coach_id'   => (int) ($service->coach_id ?? 0),
                'service_id' => (int) $service->id,
                'meta'       => [
                    'reason'               => 'wallet_not_full_coverage',
                    'wallet_use_minor'     => (int) $walletUseMinor,
                    'total_minor'          => (int) $totalMinor,
                    'available_minor'      => (int) $availMinor,
                    'requested_apply_minor'=> (int) $requestedApply,
                ],
            ]);

            return redirect()->back()->with('error', 'Your platform credit is not enough. Please use Card/PayPal for the remaining amount.');
        }

        $clientTz = $data['client_tz'] ?: config('app.timezone', 'UTC');
        $reservation = null;
        $payment = null;

        DB::transaction(function () use (
            $data,
            $service,
            $package,
            $clientTz,
            $subtotalMinor,
            $feesMinor,
            $totalMinor,
            $totalHours,
            $walletUseMinor,
            $coachFeeType,
            $coachFeeAmount,
            $coachFeeMinor,
            $coachNetMinor,
            &$reservation,
            &$payment
        ) {
            $this->assertSlotsBookableOrFail($service, $data['days'], true);

            $reservation = Reservation::create([
                'service_id'   => $service->id,
                'package_id'   => $package->id,
                'client_id'    => auth()->id(),
                'coach_id'     => $service->coach_id ?? null,
                'client_tz'    => $clientTz,
                'environment'  => $data['environment'] ?? null,
                'note'         => $data['note'] ?? null,

                'wallet_platform_credit_used_minor' => $walletUseMinor,
                'payable_minor'   => 0,
                'funding_status'  => 'wallet_only',

                'coach_fee_type'   => $coachFeeType,
                'coach_fee_amount' => $coachFeeAmount,
                'coach_fee_minor'  => $coachFeeMinor,
                'coach_net_minor'  => $coachNetMinor,

                'service_title_snapshot' => $service->title,
                'package_name_snapshot'  => $package->name,
                'package_hourly_rate'    => $package->hourly_rate,
                'package_total_price'    => $package->total_price,
                'package_hours_per_day'  => $package->hours_per_day,
                'package_total_days'     => $package->total_days,
                'package_total_hours'    => $package->total_hours,

                'currency'       => 'USD',
                'subtotal_minor' => $subtotalMinor,
                'fees_minor'     => $feesMinor,
                'total_minor'    => $totalMinor,
                'total_hours'    => $totalHours,
                'priced_at'      => now(),

                'status'         => 'booked',
                'payment_status' => 'paid',
                'booked_at'      => now(),
                'provider'       => 'wallet',
            ]);

            foreach ($data['days'] as $d) {
                ReservationSlot::create([
                    'reservation_id' => $reservation->id,
                    'slot_date'      => $d['date'],
                    'start_utc'      => CarbonImmutable::parse($d['start'])->utc(),
                    'end_utc'        => CarbonImmutable::parse($d['end'])->utc(),
                ]);
            }

            $payment = Payment::create([
                'reservation_id'         => $reservation->id,
                'provider'               => 'wallet',
                'provider_payment_id'    => 'wallet_' . $reservation->id,
                'method'                 => 'PLATFORM_CREDIT',
                'status'                 => 'succeeded',
                'currency'               => 'USD',
                'amount_total'           => $walletUseMinor,

                'service_subtotal_minor' => $subtotalMinor,
                'client_fee_minor'       => $feesMinor,
                'coach_fee_minor'        => $coachFeeMinor,
                'coach_earnings'         => 0,
                'platform_fee'           => $feesMinor,

                'succeeded_at'           => now(),
                'meta'                   => ['reason' => 'wallet_only_checkout'],
            ]);

            app(WalletService::class)->debit(
                auth()->id(),
                $walletUseMinor,
                'reservation_paid_wallet',
                $reservation->id,
                $payment->id,
                [],
                'USD',
                WalletService::BAL_PLATFORM,
                false
            );
        });

        $reservation->loadMissing(['slots', 'service', 'client', 'coach']);
        $this->sendBookingEmails($reservation);

        AnalyticsLogger::log($r, 'booking_paid_wallet_only', [
            'group'          => 'booking',
            'client_id'      => (int) ($r->user()?->id ?? 0),
            'coach_id'       => (int) ($reservation->coach_id ?? 0),
            'service_id'     => (int) ($reservation->service_id ?? 0),
            'reservation_id' => (int) $reservation->id,
            'payment_id'     => (int) ($payment->id ?? 0),
            'meta'           => [
                'provider'           => 'wallet',
                'funding_status'     => 'wallet_only',
                'subtotal_minor'     => (int) $subtotalMinor,
                'fees_minor'         => (int) $feesMinor,
                'total_minor'        => (int) $totalMinor,
                'wallet_used_minor'  => (int) $walletUseMinor,
                'coach_fee_minor'    => (int) $coachFeeMinor,
                'coach_net_minor'    => (int) $coachNetMinor,
                'total_hours'        => (float) $totalHours,
                'package_id'         => (int) $package->id,
            ],
        ]);

        return redirect()->route('client.home', ['tab' => 'bookings'])
            ->with('success', 'Booking confirmed using your platform credit.');
    }

    /**
     * Server-truth booking block rules:
     * - booked + paid => blocks
     * - cancelled => FREE
     *   EXCEPT: cancelled_by=coach AND (startUtc - cancelled_at) >= 48h => block
     *
     * If $lock=true, it will lock overlapping ReservationSlot rows FOR UPDATE.
     */
    private function assertSlotsBookableOrFail(Service $service, array $days, bool $lock = false): void
    {
        $coachId = (int) ($service->coach_id ?? 0);
        if ($coachId <= 0) {
            abort(409, 'Service coach is missing.');
        }

        $ranges = [];
        foreach ($days as $d) {
            $s = CarbonImmutable::parse($d['start'])->utc();
            $e = CarbonImmutable::parse($d['end'])->utc();
            if ($e->lte($s)) {
                abort(422, 'Invalid time range.');
            }
            $ranges[] = [$s, $e];
        }

        $q = ReservationSlot::query()
            ->whereHas('reservation', function ($qq) use ($coachId) {
                $qq->where('coach_id', $coachId);
            })
            ->where(function ($w) use ($ranges) {
                foreach ($ranges as [$s, $e]) {
                    $w->orWhere(function ($x) use ($s, $e) {
                        $x->where('start_utc', '<', $e)
                          ->where('end_utc',   '>', $s);
                    });
                }
            })
            ->with(['reservation:id,status,payment_status,cancelled_by,cancelled_at']);

        if ($lock) {
            $q->lockForUpdate();
        }

        $overlaps = $q->get();

        foreach ($overlaps as $slot) {
            $res = $slot->reservation;
            if (! $res) {
                continue;
            }

            $startUtc = CarbonImmutable::parse($slot->start_utc)->utc();

            if (($res->status === 'booked') && ($res->payment_status === 'paid')) {
                abort(409, 'One or more selected times are no longer available.');
            }

            if (
                in_array($res->status, ['cancelled', 'canceled'], true)
                && strtolower((string) $res->cancelled_by) === 'coach'
                && $res->cancelled_at
            ) {
                $cancelAtUtc = CarbonImmutable::parse($res->cancelled_at)->utc();
                $hoursLeft   = $cancelAtUtc->diffInRealHours($startUtc, false);

                if ($hoursLeft >= 48) {
                    abort(409, 'One or more selected times are not bookable because the coach cancelled earlier with 48+ hours remaining.');
                }
            }
        }
    }

    private function sendBookingEmails(Reservation $reservation): void
    {
        $reservation->loadMissing(['slots', 'service', 'client', 'coach']);

        if ($reservation->status !== 'booked' || $reservation->payment_status !== 'paid') {
            return;
        }

        $clientTz = $reservation->client_tz ?: config('app.timezone', 'UTC');
        $coachTz  = $reservation->coach?->timezone ?: config('app.timezone', 'UTC');

        $formatSlots = function (string $tz) use ($reservation) {
            return $reservation->slots
                ->sortBy('start_utc')
                ->map(function ($slot) use ($tz) {
                    $start = CarbonImmutable::parse($slot->start_utc)->setTimezone($tz);
                    $end   = CarbonImmutable::parse($slot->end_utc)->setTimezone($tz);

                    return [
                        'date'  => $start->format('D, d M Y'),
                        'start' => $start->format('h:i A'),
                        'end'   => $end->format('h:i A'),
                    ];
                })->values()->all();
        };

        if (is_null($reservation->client_booking_emailed_at) && $reservation->client?->email) {
            $slotsClient = $formatSlots($clientTz);
            Mail::to($reservation->client->email)->send(new BookingConfirmedClientMail($reservation, $slotsClient, $clientTz));
            $reservation->forceFill(['client_booking_emailed_at' => now()])->save();
        }

        if (is_null($reservation->coach_booking_emailed_at) && $reservation->coach?->email) {
            $slotsCoach = $formatSlots($coachTz);
            Mail::to($reservation->coach->email)->send(new BookingConfirmedCoachMail($reservation, $slotsCoach, $coachTz));
            $reservation->forceFill(['coach_booking_emailed_at' => now()])->save();
        }
    }

    public function reprice(Request $r)
    {
        $r->validate([
            'package_id'      => ['required', 'integer', 'exists:service_packages,id'],
            'days'            => ['required', 'array', 'min:1'],
            'days.*.start'    => ['required', 'date'],
            'days.*.end'      => ['required', 'date'],
        ]);

        $package = ServicePackage::findOrFail($r->integer('package_id'));
        $hours = collect($r->input('days'))->sum(function ($d) {
            return max(0, (float) CarbonImmutable::parse($d['end'])->floatDiffInRealHours(CarbonImmutable::parse($d['start'])));
        });

        $base = 0.0;
        if (! is_null($package->total_price) && $package->total_price > 0) {
            $base = (float) $package->total_price;
        } elseif (! is_null($package->hourly_rate) && $package->hourly_rate > 0) {
            $base = (float) $package->hourly_rate * $hours;
        }

        $fees = ServiceFee::where('is_active', true)
            ->where('party', 'client')
            ->get();

        $percentSum = 0;
        $fixedSum   = 0;
        $lines      = [];

        foreach ($fees as $f) {
            $val = $f->type === 'percent'
                ? round($base * ((float) $f->amount / 100), 2)
                : round((float) $f->amount, 2);

            $lines[] = ['label' => $f->label, 'value' => $val];
            $f->type === 'percent' ? $percentSum += $val : $fixedSum += $val;
        }

        $subtotal = round($base, 2);
        $total = round($subtotal + $percentSum + $fixedSum, 2);

        return response()->json(compact('subtotal', 'lines', 'total'));
    }
}