@extends('superadmin.layout')

@section('title', 'Booking Details')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/superadmin-bookings-show.css') }}">
@endpush

@section('content')
    @php
        $money = function ($minor, $currency = 'USD') {
            if ($minor === null || $minor === '') {
                return '—';
            }

            $amount = ((int) $minor) / 100;
            return '$' . number_format($amount, 2) . ' ' . strtoupper((string) $currency);
        };

        $labelize = function ($value) {
            $value = trim((string) $value);
            if ($value === '') {
                return '—';
            }
            return ucwords(str_replace('_', ' ', $value));
        };

        $fullName = function ($user, $fallback = '—') {
            if (!$user) {
                return $fallback;
            }

            $name = trim((string) (($user->first_name ?? '') . ' ' . ($user->last_name ?? '')));
            return $name !== '' ? $name : ($user->username ?? $user->email ?? $fallback);
        };

        $providerLabel = function ($value) use ($labelize) {
            return match (strtolower((string) $value)) {
                'stripe' => 'Stripe',
                'paypal' => 'PayPal',
                'wallet' => 'Wallet',
                default => $labelize($value),
            };
        };

        $methodLabel = function ($value) use ($labelize) {
            return match ((string) $value) {
                'VISA' => 'Visa',
                'MASTERCARD' => 'Mastercard',
                'AMEX' => 'American Express',
                'DISCOVER' => 'Discover',
                'CARD' => 'Card',
                'KLARNA' => 'Klarna',
                'PAYPAL' => 'PayPal',
                'paypal' => 'PayPal',
                'PLATFORM_CREDIT' => 'Platform Credit',
                default => $labelize($value),
            };
        };

        $pillClass = function ($type, $value) {
            $v = strtolower((string) $value);

            return match ($type) {
                'booking' => match (true) {
                    in_array($v, ['completed', 'booked', 'live']) => 'ok',
                    in_array($v, ['cancelled', 'canceled']) => 'danger',
                    in_array($v, ['no_show', 'no_show_client', 'no_show_coach', 'no_show_both', 'waiting_for_client', 'waiting_for_coach']) => 'warn',
                    default => 'neutral',
                },
                'payment' => match (true) {
                    in_array($v, ['paid', 'succeeded']) => 'ok',
                    in_array($v, ['processing', 'pending', 'requires_payment', 'requires_action']) => 'warn',
                    in_array($v, ['failed', 'cancelled', 'canceled']) => 'danger',
                    default => 'neutral',
                },
                'settlement' => match (true) {
                    in_array($v, ['paid']) => 'ok',
                    in_array($v, ['refunded']) => 'danger',
                    in_array($v, ['refunded_partial', 'refund_pending', 'pending']) => 'warn',
                    in_array($v, ['in_dispute', 'cancelled', 'canceled']) => 'danger',
                    default => 'neutral',
                },
                'refund' => match (true) {
                    in_array($v, ['succeeded']) => 'ok',
                    in_array($v, ['partial', 'processing', 'pending_choice', 'pending']) => 'warn',
                    in_array($v, ['failed', 'cancelled', 'canceled']) => 'danger',
                    default => 'neutral',
                },
                'funding' => match (true) {
                    $v === 'external_only' => 'ok',
                    $v === 'mixed' => 'warn',
                    $v === 'wallet_only' => 'neutral',
                    default => 'neutral',
                },
                'dispute' => match (true) {
                    in_array($v, ['resolved', 'closed']) => 'ok',
                    in_array($v, ['rejected']) => 'danger',
                    in_array($v, ['open', 'opened', 'in_review']) => 'warn',
                    default => 'neutral',
                },
                default => 'neutral',
            };
        };

        $formatDt = function ($value, $fallback = '—') {
            if (empty($value)) {
                return $fallback;
            }

            try {
                return \Illuminate\Support\Carbon::parse($value)->format('d M Y, h:i A');
            } catch (\Throwable $e) {
                return $fallback;
            }
        };

        $formatDate = function ($value, $fallback = '—') {
            if (empty($value)) {
                return $fallback;
            }

            try {
                return \Illuminate\Support\Carbon::parse($value)->format('d M Y');
            } catch (\Throwable $e) {
                return $fallback;
            }
        };

        $formatTime = function ($value, $fallback = '—') {
            if (empty($value)) {
                return $fallback;
            }

            try {
                return \Illuminate\Support\Carbon::parse($value)->format('h:i A');
            } catch (\Throwable $e) {
                return $fallback;
            }
        };

        $mapLink = function ($lat, $lng) {
            if ($lat === null || $lng === null || $lat === '' || $lng === '') {
                return null;
            }

            return 'https://www.google.com/maps?q=' . urlencode((string) $lat . ',' . (string) $lng);
        };

        $currency = strtoupper((string) ($finance['currency'] ?? $reservation->currency ?? 'USD'));
        $bookingNo = 'BK-' . str_pad((string) $reservation->id, 8, '0', STR_PAD_LEFT);

        $service = $reservation->service;
        $client = $reservation->client;
        $coach = $reservation->coach ?? $service?->coach;
        $package = $reservation->package;

        $clientName = $fullName($client, 'Client #' . $reservation->client_id);
        $coachName = $fullName($coach, 'Coach #' . ($reservation->coach_id ?? $service?->coach_id));
        $serviceTitle = $service?->title ?? ($reservation->service_title_snapshot ?? '—');
        $packageTitle = $package?->name ?? ($reservation->package_name_snapshot ?? '—');

        $refundRows = collect($refundRows ?? []);
        $disputeRows = collect($disputeRows ?? []);
        $timeline = collect($timeline ?? []);
        $slotDetails = collect($slotDetails ?? []);
        $paymentRows = collect($paymentRows ?? []);

        $sessionSummary = $sessionSummary ?? [];
        $refundSummary = $refundSummary ?? [];
        $disputeSummary = $disputeSummary ?? [];
        $cancellationDetails = $cancellationDetails ?? ['is_cancelled' => false];

        $latestRefundStatus = $finance['latest_refund_status'] ?? 'none';
        $latestRefundMethod = $finance['latest_refund_method'] ?? null;
        $latestRefundRequestedAt = $finance['latest_refund_requested_at'] ?? null;
        $latestRefundProcessedAt = $finance['latest_refund_processed_at'] ?? null;
        $latestRefundError = $finance['latest_refund_error'] ?? null;

        $latestRefundProvider = $finance['latest_refund_provider'] ?? null;
$latestProviderRefundId = $finance['latest_provider_refund_id'] ?? null;
$latestProviderOrderId = $finance['latest_provider_order_id'] ?? null;
$latestProviderCaptureId = $finance['latest_provider_capture_id'] ?? null;
    @endphp

    <div class="ab-page ab-center ab-caps">
        <div class="ab-shell ab-center">
           <div class="ab-head__actions ab-center">
                    <a href="{{ route('superadmin.bookings.index') }}" class="ab-btn ab-btn--ghost">
                        <i class="bi bi-arrow-left"></i>
                        <span>Back To Bookings</span>
                    </a>
                </div>

            <div class="ab-head ab-center">
                <div class="ab-head__content ab-center">
                    <h1 class="ab-title">Booking Details</h1>
                    <p class="ab-sub">
                        Full operational view for {{ $bookingNo }} including overview, cancellation, sessions, payments,
                        refunds, disputes, settlement, and timeline.
                    </p>

                    <div class="ab-head__period ab-center">
                        <span class="ab-pill {{ $pillClass('booking', $reservation->status) }}">
                            {{ $labelize($reservation->status ?? 'pending') }}
                        </span>
                        <span class="ab-pill {{ $pillClass('payment', $reservation->payment_status) }}">
                            {{ $labelize($reservation->payment_status ?? 'requires_payment') }}
                        </span>
                        <span class="ab-pill {{ $pillClass('settlement', $reservation->settlement_status) }}">
                            {{ $labelize($reservation->settlement_status ?? 'pending') }}
                        </span>
                        @if (!empty($latestRefundStatus) && $latestRefundStatus !== 'none')
                            <span class="ab-pill {{ $pillClass('refund', $latestRefundStatus) }}">
                                {{ $labelize($latestRefundStatus) }}
                            </span>
                        @endif
                        <span class="ab-pill {{ $pillClass('funding', $finance['funding_status'] ?? $reservation->funding_status) }}">
                            {{ $labelize($finance['funding_status'] ?? $reservation->funding_status ?? 'external_only') }}
                        </span>
                    </div>
                </div>

               
            </div>

            <div class="ab-kpis ab-center">
                <div class="ab-kpiCard is-highlight ab-center">
                    <div class="ab-kpiLabel">Total</div>
                    <div class="ab-kpiValue">{{ $money($finance['total_minor'] ?? 0, $currency) }}</div>
                    <div class="ab-kpiMeta">Full booking amount charged to client</div>
                </div>

                <div class="ab-kpiCard ab-center">
                    <div class="ab-kpiLabel">Subtotal</div>
                    <div class="ab-kpiValue">{{ $money($finance['subtotal_minor'] ?? 0, $currency) }}</div>
                    <div class="ab-kpiMeta">Service amount before fees</div>
                </div>

                <div class="ab-kpiCard ab-center">
                    <div class="ab-kpiLabel">Fees</div>
                    <div class="ab-kpiValue">{{ $money($finance['fees_minor'] ?? 0, $currency) }}</div>
                    <div class="ab-kpiMeta">Client/platform fee portion</div>
                </div>

                <div class="ab-kpiCard ab-center">
                    <div class="ab-kpiLabel">Wallet Used</div>
                    <div class="ab-kpiValue">{{ $money($finance['wallet_used_minor'] ?? 0, $currency) }}</div>
                    <div class="ab-kpiMeta">Applied from platform credit</div>
                </div>

                <div class="ab-kpiCard ab-center">
                    <div class="ab-kpiLabel">Gateway Paid</div>
                    <div class="ab-kpiValue">{{ $money($finance['gateway_paid_minor'] ?? 0, $currency) }}</div>
                    <div class="ab-kpiMeta">External paid amount</div>
                </div>

                <div class="ab-kpiCard ab-center">
                    <div class="ab-kpiLabel">Refunded</div>
                    <div class="ab-kpiValue">{{ $money($finance['refund_total_minor'] ?? 0, $currency) }}</div>
                    <div class="ab-kpiMeta">Actual refunded amount so far</div>
                </div>

                <div class="ab-kpiCard ab-center">
                    <div class="ab-kpiLabel">Coach Total Earnings</div>
                    <div class="ab-kpiValue">{{ $money($finance['coach_total_earning_minor'] ?? 0, $currency) }}</div>
                    <div class="ab-kpiMeta">Earned + comp - penalty</div>
                </div>

                <div class="ab-kpiCard is-highlight ab-center">
                    <div class="ab-kpiLabel">Platform Earned</div>
                    <div class="ab-kpiValue">{{ $money($finance['platform_earned_minor'] ?? 0, $currency) }}</div>
                    <div class="ab-kpiMeta">Net platform result on booking</div>
                </div>
            </div>

            <div class="ab-chartGrid ab-center">
                <div class="ab-card ab-center">
                    <div class="ab-cardHead ab-center">
                        <h2 class="ab-cardTitle">Booking Overview</h2>
                        <p class="ab-cardSub">Core booking identity, dates, and lifecycle state.</p>
                    </div>

                    <div class="ab-detailGrid ab-center">
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Booking No</span>
                            <strong class="ab-mono">{{ $bookingNo }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Reservation Id</span>
                            <strong>#{{ $reservation->id }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Created</span>
                            <strong>{{ $formatDt($reservation->created_at) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Booked At</span>
                            <strong>{{ $formatDt($reservation->booked_at) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Completed At</span>
                            <strong>{{ $formatDt($reservation->completed_at) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Cancelled At</span>
                            <strong>{{ $formatDt($reservation->cancelled_at) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Cancelled By</span>
                            <strong>{{ $labelize($reservation->cancelled_by ?: '—') }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Funding</span>
                            <strong>{{ $labelize($finance['funding_status'] ?? $reservation->funding_status ?? 'external_only') }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Currency</span>
                            <strong>{{ $currency }}</strong>
                        </div>
                    </div>
                </div>

                <div class="ab-card ab-center">
                    <div class="ab-cardHead ab-center">
                        <h2 class="ab-cardTitle">Service & People</h2>
                        <p class="ab-cardSub">Service, package, client, and coach details.</p>
                    </div>

                    <div class="ab-detailGrid ab-center">
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Service</span>
                            <strong>{{ $serviceTitle }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Package</span>
                            <strong>{{ $packageTitle }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Client</span>
                            <strong>{{ $clientName }}</strong>
                            <span>{{ $client?->email ?? '—' }}</span>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Coach</span>
                            <strong>{{ $coachName }}</strong>
                            <span>{{ $coach?->email ?? '—' }}</span>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Client Timezone</span>
                            <strong>{{ $reservation->client_tz ?? '—' }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Environment</span>
                            <strong>{{ $reservation->environment ?? '—' }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Total Hours</span>
                            <strong>{{ number_format((float) ($reservation->total_hours ?? 0), 2) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Note</span>
                            <strong>{{ $reservation->note ?: '—' }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            @if(!empty($cancellationDetails['is_cancelled']))
                <div class="ab-card ab-center">
                    <div class="ab-cardHead ab-center">
                        <h2 class="ab-cardTitle">Cancellation Details</h2>
                        <p class="ab-cardSub">Rule bucket, penalties, refunds, and final cancellation outcome.</p>
                    </div>

                    <div class="ab-banner ab-banner--danger ab-center">
                        <strong>{{ $cancellationDetails['human_summary'] ?? 'Cancelled Booking' }}</strong>
                    </div>

                    <div class="ab-detailGrid ab-center">
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Cancelled By</span>
                            <strong>{{ $labelize($cancellationDetails['cancelled_by'] ?? '—') }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Cancelled At</span>
                            <strong>{{ $formatDt($cancellationDetails['cancelled_at'] ?? null) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Cancel Reason</span>
                            <strong>{{ $cancellationDetails['cancel_reason'] ?? '—' }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Rule Bucket</span>
                            <strong>{{ $labelize($cancellationDetails['rule_bucket'] ?? '—') }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Hours Until First Slot</span>
                            <strong>
                                {{ isset($cancellationDetails['hours_until_first_slot']) && $cancellationDetails['hours_until_first_slot'] !== null
                                    ? number_format((float) $cancellationDetails['hours_until_first_slot'], 2)
                                    : '—' }}
                            </strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Refund Method</span>
                            <strong>{{ $labelize($cancellationDetails['refund_method'] ?? '—') }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Refund Status</span>
                            <strong>{{ $labelize($cancellationDetails['refund_status'] ?? '—') }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Refund Requested At</span>
                            <strong>{{ $formatDt($cancellationDetails['refund_requested_at'] ?? null) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Refund Processed At</span>
                            <strong>{{ $formatDt($cancellationDetails['refund_processed_at'] ?? null) }}</strong>
                        </div>

                        <div class="ab-detailItem ab-center">
    <span class="ab-detailLabel">Refund Provider</span>
    <strong>{{ $providerLabel($cancellationDetails['provider'] ?? '—') }}</strong>
</div>
<div class="ab-detailItem ab-center">
    <span class="ab-detailLabel">Provider Refund Id</span>
    <strong class="ab-mono">{{ $cancellationDetails['provider_refund_id'] ?? '—' }}</strong>
</div>
<div class="ab-detailItem ab-center">
    <span class="ab-detailLabel">Provider Order Id</span>
    <strong class="ab-mono">{{ $cancellationDetails['provider_order_id'] ?? '—' }}</strong>
</div>
<div class="ab-detailItem ab-center">
    <span class="ab-detailLabel">Provider Capture Id</span>
    <strong class="ab-mono">{{ $cancellationDetails['provider_capture_id'] ?? '—' }}</strong>
</div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Refund Error</span>
                            <strong>{{ $cancellationDetails['refund_error'] ?? '—' }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Refund Total</span>
                            <strong>{{ $money($cancellationDetails['refund_total_minor'] ?? 0, $currency) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Refund To Wallet</span>
                            <strong>{{ $money($cancellationDetails['refund_wallet_minor'] ?? 0, $currency) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Refund To Original</span>
                            <strong>{{ $money($cancellationDetails['refund_external_minor'] ?? 0, $currency) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Client Penalty</span>
                            <strong>{{ $money($cancellationDetails['client_penalty_minor'] ?? 0, $currency) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Coach Penalty</span>
                            <strong>{{ $money($cancellationDetails['coach_penalty_minor'] ?? 0, $currency) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Coach Compensation</span>
                            <strong>{{ $money($cancellationDetails['coach_comp_minor'] ?? 0, $currency) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Platform Earned</span>
                            <strong>{{ $money($cancellationDetails['platform_earned_minor'] ?? 0, $currency) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Platform Fee Refund Requested</span>
                            <strong>{{ $cancellationDetails['platform_fee_refund_requested_minor'] !== null ? $money($cancellationDetails['platform_fee_refund_requested_minor'], $currency) : '—' }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Platform Fee Refunded</span>
                            <strong>{{ $cancellationDetails['platform_fee_refunded_minor'] !== null ? $money($cancellationDetails['platform_fee_refunded_minor'], $currency) : '—' }}</strong>
                        </div>
                    </div>
                </div>
            @endif

            <div class="ab-card ab-center">
                <div class="ab-cardHead ab-center">
                    <h2 class="ab-cardTitle">Session Summary</h2>
                    <p class="ab-cardSub">Aggregated status view across all reservation slots.</p>
                </div>

                <div class="ab-financeGrid ab-center">
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Total Slots</span>
                        <strong class="ab-financeValue">{{ (int) ($sessionSummary['slots_count'] ?? 0) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Live</span>
                        <strong class="ab-financeValue">{{ (int) ($sessionSummary['live_count'] ?? 0) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Waiting For Client</span>
                        <strong class="ab-financeValue">{{ (int) ($sessionSummary['waiting_for_client'] ?? 0) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Waiting For Coach</span>
                        <strong class="ab-financeValue">{{ (int) ($sessionSummary['waiting_for_coach'] ?? 0) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Client No Show</span>
                        <strong class="ab-financeValue">{{ (int) ($sessionSummary['client_no_show_count'] ?? 0) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Coach No Show</span>
                        <strong class="ab-financeValue">{{ (int) ($sessionSummary['coach_no_show_count'] ?? 0) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Both No Show</span>
                        <strong class="ab-financeValue">{{ (int) ($sessionSummary['both_no_show_count'] ?? 0) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Cancelled Slots</span>
                        <strong class="ab-financeValue">{{ (int) ($sessionSummary['cancelled_count'] ?? 0) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Finalized Slots</span>
                        <strong class="ab-financeValue">{{ (int) ($sessionSummary['finalized_count'] ?? 0) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Extended Slots</span>
                        <strong class="ab-financeValue">{{ (int) ($sessionSummary['extended_count'] ?? 0) }}</strong>
                    </div>
                </div>
            </div>

            <div class="ab-card ab-center">
                <div class="ab-cardHead ab-center">
                    <h2 class="ab-cardTitle">Slot Details</h2>
                    <p class="ab-cardSub">Per-slot check-in, waiting, extension, finalization, and audit data.</p>
                </div>

                <div class="ab-tableWrap">
                    <table class="ab-table ab-table--caps">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Slot Date</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Status</th>
                                <th>Client Check In</th>
                                <th>Coach Check In</th>
                                <th>Deadline</th>
                                <th>Extended Until</th>
                                <th>Finalized</th>
                                <th>Auto Cancelled</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($slotDetails as $i => $slot)
                                @php
                                    $clientMap = $mapLink($slot['client_lat'] ?? null, $slot['client_lng'] ?? null);
                                    $coachMap = $mapLink($slot['coach_lat'] ?? null, $slot['coach_lng'] ?? null);
                                @endphp
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $formatDate($slot['start_utc'] ?? $slot['slot_date'] ?? null, $slot['slot_date'] ?? '—') }}</td>
                                    <td>{{ $formatTime($slot['start_utc'] ?? null) }}</td>
                                    <td>{{ $formatTime($slot['end_utc'] ?? null) }}</td>
                                    <td>
                                        <span class="ab-pill {{ $pillClass('booking', $slot['session_status'] ?? 'pending') }}">
                                            {{ $labelize($slot['session_status'] ?? 'pending') }}
                                        </span>
                                    </td>
                                    <td>{{ !empty($slot['client_checked_in_at']) ? $formatDt($slot['client_checked_in_at']) : 'No' }}</td>
                                    <td>{{ !empty($slot['coach_checked_in_at']) ? $formatDt($slot['coach_checked_in_at']) : 'No' }}</td>
                                    <td>{{ $formatDt($slot['effective_deadline_utc'] ?? null) }}</td>
                                    <td>{{ $formatDt($slot['extended_until_utc'] ?? null) }}</td>
                                    <td>{{ $formatDt($slot['finalized_at'] ?? null) }}</td>
                                    <td>{{ $formatDt($slot['auto_cancelled_at'] ?? null) }}</td>
                                </tr>
                                <tr class="ab-table__subrow">
                                    <td colspan="11">
                                        <div class="ab-subgrid ab-center">
                                            <div class="ab-subgridItem ab-center">
                                                <span class="ab-detailLabel">Derived Outcome</span>
                                                <strong>{{ $slot['derived_outcome'] ?? '—' }}</strong>
                                            </div>
                                            <div class="ab-subgridItem ab-center">
                                                <span class="ab-detailLabel">Extended</span>
                                                <strong>{{ !empty($slot['extended']) ? 'Yes' : 'No' }}</strong>
                                            </div>
                                            <div class="ab-subgridItem ab-center">
                                                <span class="ab-detailLabel">Extended By</span>
                                                <strong>{{ $labelize($slot['extended_by'] ?? '—') }}</strong>
                                            </div>
                                            <div class="ab-subgridItem ab-center">
                                                <span class="ab-detailLabel">Extended At</span>
                                                <strong>{{ $formatDt($slot['extended_at_utc'] ?? null) }}</strong>
                                            </div>
                                            <div class="ab-subgridItem ab-center">
                                                <span class="ab-detailLabel">Nudge 1</span>
                                                <strong>{{ $formatDt($slot['nudge1_sent_at'] ?? null) }}</strong>
                                            </div>
                                            <div class="ab-subgridItem ab-center">
                                                <span class="ab-detailLabel">Nudge 2</span>
                                                <strong>{{ $formatDt($slot['nudge2_sent_at'] ?? null) }}</strong>
                                            </div>
                                            <div class="ab-subgridItem ab-center">
                                                <span class="ab-detailLabel">Client Location</span>
                                                <strong>
                                                    @if($clientMap)
                                                        <a href="{{ $clientMap }}" target="_blank" rel="noopener noreferrer">
                                                            {{ $slot['client_lat'] }}, {{ $slot['client_lng'] }}
                                                        </a>
                                                    @else
                                                        —
                                                    @endif
                                                </strong>
                                            </div>
                                            <div class="ab-subgridItem ab-center">
                                                <span class="ab-detailLabel">Coach Location</span>
                                                <strong>
                                                    @if($coachMap)
                                                        <a href="{{ $coachMap }}" target="_blank" rel="noopener noreferrer">
                                                            {{ $slot['coach_lat'] }}, {{ $slot['coach_lng'] }}
                                                        </a>
                                                    @else
                                                        —
                                                    @endif
                                                </strong>
                                            </div>
                                            <div class="ab-subgridItem ab-center ab-subgridItem--full">
                                                <span class="ab-detailLabel">Audit Info</span>
                                                <strong>
                                                    @if(!empty($slot['info']))
                                                        {{ json_encode($slot['info'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}
                                                    @else
                                                        —
                                                    @endif
                                                </strong>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="ab-empty">No slot details found for this booking.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ab-card ab-center">
                <div class="ab-cardHead ab-center">
                    <h2 class="ab-cardTitle">Finance Breakdown</h2>
                    <p class="ab-cardSub">Client payment, refund split, coach earnings, and platform accounting.</p>
                </div>

                <div class="ab-financeGrid ab-center">
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Client Paid</span>
                        <strong class="ab-financeValue">{{ $money($finance['client_paid_minor'] ?? 0, $currency) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Client Net Paid</span>
                        <strong class="ab-financeValue">{{ $money($finance['client_net_paid_minor'] ?? 0, $currency) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Wallet Refunded</span>
                        <strong class="ab-financeValue">{{ $money($finance['wallet_refunded_minor'] ?? 0, $currency) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Original Source Refunded</span>
                        <strong class="ab-financeValue">{{ $money($finance['external_refunded_minor'] ?? 0, $currency) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Coach Earned</span>
                        <strong class="ab-financeValue">{{ $money($finance['coach_earned_minor'] ?? 0, $currency) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Coach Compensation</span>
                        <strong class="ab-financeValue">{{ $money($finance['coach_comp_minor'] ?? 0, $currency) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Coach Penalty</span>
                        <strong class="ab-financeValue">{{ $money($finance['coach_penalty_minor'] ?? 0, $currency) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Client Penalty</span>
                        <strong class="ab-financeValue">{{ $money($finance['client_penalty_minor'] ?? 0, $currency) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Coach Net</span>
                        <strong class="ab-financeValue">{{ $money($finance['coach_net_minor'] ?? 0, $currency) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Coach Gross</span>
                        <strong class="ab-financeValue">{{ $money($finance['coach_gross_minor'] ?? 0, $currency) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Coach Commission</span>
                        <strong class="ab-financeValue">{{ $money($finance['coach_commission_minor'] ?? 0, $currency) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Refund To Wallet</span>
                        <strong class="ab-financeValue">{{ $money($finance['wallet_refunded_minor'] ?? 0, $currency) }}</strong>
                    </div>
                    <div class="ab-financeCard ab-center">
                        <span class="ab-financeLabel">Refund To Original</span>
                        <strong class="ab-financeValue">{{ $money($finance['external_refunded_minor'] ?? 0, $currency) }}</strong>
                    </div>
                </div>
            </div>

            <div class="ab-card ab-center">
                <div class="ab-cardHead ab-center">
                    <h2 class="ab-cardTitle">Settlement Details</h2>
                    <p class="ab-cardSub">Settlement, escrow, release, payout, and latest refund state.</p>
                </div>

                <div class="ab-detailGrid ab-center">
                    <div class="ab-detailItem ab-center">
                        <span class="ab-detailLabel">Settlement Status</span>
                        <strong>{{ $labelize($finance['settlement_status'] ?? 'pending') }}</strong>
                    </div>
                    <div class="ab-detailItem ab-center">
                        <span class="ab-detailLabel">Latest Refund Status</span>
                        <strong>{{ $labelize($latestRefundStatus ?? 'none') }}</strong>
                    </div>
                    <div class="ab-detailItem ab-center">
                        <span class="ab-detailLabel">Latest Refund Method</span>
                        <strong>{{ $labelize($latestRefundMethod ?? '—') }}</strong>
                    </div>
                    <div class="ab-detailItem ab-center">
                        <span class="ab-detailLabel">Latest Refund Requested At</span>
                        <strong>{{ $formatDt($latestRefundRequestedAt) }}</strong>
                    </div>
                    <div class="ab-detailItem ab-center">
                        <span class="ab-detailLabel">Latest Refund Processed At</span>
                        <strong>{{ $formatDt($latestRefundProcessedAt) }}</strong>
                    </div>
                    <div class="ab-detailItem ab-center">
                        <span class="ab-detailLabel">Latest Refund Error</span>
                        <strong>{{ $latestRefundError ?: '—' }}</strong>
                    </div>

                    <div class="ab-detailItem ab-center">
    <span class="ab-detailLabel">Latest Refund Provider</span>
    <strong>{{ $providerLabel($latestRefundProvider ?? '—') }}</strong>
</div>
<div class="ab-detailItem ab-center">
    <span class="ab-detailLabel">Provider Refund Id</span>
    <strong class="ab-mono">{{ $latestProviderRefundId ?? '—' }}</strong>
</div>
<div class="ab-detailItem ab-center">
    <span class="ab-detailLabel">Provider Order Id</span>
    <strong class="ab-mono">{{ $latestProviderOrderId ?? '—' }}</strong>
</div>
<div class="ab-detailItem ab-center">
    <span class="ab-detailLabel">Provider Capture Id</span>
    <strong class="ab-mono">{{ $latestProviderCaptureId ?? '—' }}</strong>
</div>
                    <div class="ab-detailItem ab-center">
                        <span class="ab-detailLabel">Escrow Release At</span>
                        <strong>{{ $formatDt($finance['escrow_release_at'] ?? null) }}</strong>
                    </div>
                    <div class="ab-detailItem ab-center">
                        <span class="ab-detailLabel">Last Slot End Utc</span>
                        <strong>{{ $formatDt($finance['last_slot_end_utc'] ?? null) }}</strong>
                    </div>
                   
                </div>
            </div>

            <div class="ab-card ab-center">
                <div class="ab-cardHead ab-center">
                    <h2 class="ab-cardTitle">Payments</h2>
                    <p class="ab-cardSub">All payment records linked to this booking.</p>
                </div>

                <div class="ab-tableWrap">
                    <table class="ab-table ab-table--caps">
                        <thead>
                            <tr>
                              <th>#</th>
<th>Provider</th>
<th>Method</th>
<th>Status</th>
<th>Amount</th>
<th>Currency</th>
<th>Refund Attempts</th>
<th>Provider Payment Id</th>
<th>Provider Refund Id</th>
<th>Order Id</th>
<th>Capture Id</th>
<th>Charge Id</th>
<th>Succeeded At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($paymentRows as $i => $payment)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $providerLabel($payment['provider'] ?? '—') }}</td>
                                    <td>{{ $methodLabel($payment['method'] ?? '—') }}</td>
                                    <td>
                                        <span class="ab-pill {{ $pillClass('payment', $payment['status'] ?? 'pending') }}">
                                            {{ $labelize($payment['status'] ?? 'pending') }}
                                        </span>
                                    </td>
                                    <td>{{ $money((int) ($payment['amount_total'] ?? 0), $payment['currency'] ?? $currency) }}</td>
                                    <td>{{ strtoupper((string) ($payment['currency'] ?? $currency)) }}</td>
                                    <td>{{ (int) ($payment['refund_attempts_count'] ?? 0) }}</td>
                                    <td class="ab-mono">{{ $payment['provider_payment_id'] ?? '—' }}</td>
                                    <td class="ab-mono">{{ $payment['provider_refund_id'] ?? '—' }}</td>
                                    <td class="ab-mono">{{ $payment['provider_order_id'] ?? '—' }}</td>
                                    <td class="ab-mono">{{ $payment['provider_capture_id'] ?? '—' }}</td>
                                    <td class="ab-mono">{{ $payment['provider_charge_id'] ?? '—' }}</td>
                                    <td>{{ $formatDt($payment['succeeded_at'] ?? null) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="ab-empty">No payments found for this booking.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ab-chartGrid ab-center">
                <div class="ab-card ab-center">
                    <div class="ab-cardHead ab-center">
                        <h2 class="ab-cardTitle">Refund Summary</h2>
                        <p class="ab-cardSub">Aggregate refund view for this booking.</p>
                    </div>

                    <div class="ab-detailGrid ab-center">
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Total Refund Records</span>
                            <strong>{{ (int) ($refundSummary['count'] ?? 0) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Succeeded</span>
                            <strong>{{ (int) ($refundSummary['succeeded_count'] ?? 0) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Partial</span>
                            <strong>{{ (int) ($refundSummary['partial_count'] ?? 0) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Failed</span>
                            <strong>{{ (int) ($refundSummary['failed_count'] ?? 0) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Processing</span>
                            <strong>{{ (int) ($refundSummary['processing_count'] ?? 0) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Total Refunded</span>
                            <strong>{{ $money((int) ($refundSummary['total_refunded_minor'] ?? 0), $currency) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Wallet Refunded</span>
                            <strong>{{ $money((int) ($refundSummary['wallet_refunded_minor'] ?? 0), $currency) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Original Refunded</span>
                            <strong>{{ $money((int) ($refundSummary['external_refunded_minor'] ?? 0), $currency) }}</strong>
                        </div>
                    </div>
                </div>

                <div class="ab-card ab-center">
                    <div class="ab-cardHead ab-center">
                        <h2 class="ab-cardTitle">Dispute Summary</h2>
                        <p class="ab-cardSub">Opened, resolved, and outcome counts.</p>
                    </div>

                    <div class="ab-detailGrid ab-center">
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">All Disputes</span>
                            <strong>{{ (int) ($disputeSummary['all_count'] ?? 0) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Client Opened</span>
                            <strong>{{ (int) ($disputeSummary['client_count'] ?? 0) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Coach Opened</span>
                            <strong>{{ (int) ($disputeSummary['coach_count'] ?? 0) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Open / In Review</span>
                            <strong>{{ (int) ($disputeSummary['open_count'] ?? 0) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Resolved</span>
                            <strong>{{ (int) ($disputeSummary['resolved_count'] ?? 0) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Client Wins</span>
                            <strong>{{ (int) ($disputeSummary['client_wins_count'] ?? 0) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Client Losses</span>
                            <strong>{{ (int) ($disputeSummary['client_losses_count'] ?? 0) }}</strong>
                        </div>
                        <div class="ab-detailItem ab-center">
                            <span class="ab-detailLabel">Coach Wins</span>
                            <strong>{{ (int) ($disputeSummary['coach_wins_count'] ?? 0) }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ab-card ab-center">
                <div class="ab-cardHead ab-center">
                    <h2 class="ab-cardTitle">Refund Attempts</h2>
                    <p class="ab-cardSub">Each refund record with split, method, and processing result.</p>
                </div>

                <div class="ab-tableWrap">
                    <table class="ab-table ab-table--caps">
                        <thead>
                            <tr>
                              <th>#</th>
<th>Refund Id</th>
<th>Status</th>
<th>Provider</th>
<th>Method</th>
<th>Requested</th>
<th>Actual</th>
<th>Wallet Portion</th>
<th>Original Portion</th>
<th>Refunded To Wallet</th>
<th>Refunded To Original</th>
<th>Wallet Status</th>
<th>External Status</th>
<th>Provider Refund Id</th>
<th>Order Id</th>
<th>Capture Id</th>
<th>Requested At</th>
<th>Processed At</th>
<th>Failure Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($refundRows as $i => $refund)
                               <tr>
    <td>{{ $i + 1 }}</td>
    <td class="ab-mono">RF-{{ str_pad((string) $refund->id, 8, '0', STR_PAD_LEFT) }}</td>
    <td>
        <span class="ab-pill {{ $pillClass('refund', $refund->status) }}">
            {{ $labelize($refund->status ?? 'pending') }}
        </span>
    </td>
    <td>{{ $providerLabel($refund->provider ?? '—') }}</td>
    <td>{{ $labelize($refund->method ?? '—') }}</td>
    <td>{{ $money((int) ($refund->requested_amount_minor ?? 0), $refund->currency ?? $currency) }}</td>
    <td>{{ $money((int) ($refund->actual_amount_minor ?? 0), $refund->currency ?? $currency) }}</td>
    <td>{{ $money((int) ($refund->wallet_amount_minor ?? 0), $refund->currency ?? $currency) }}</td>
    <td>{{ $money((int) ($refund->external_amount_minor ?? 0), $refund->currency ?? $currency) }}</td>
    <td>{{ $money((int) ($refund->refunded_to_wallet_minor ?? 0), $refund->currency ?? $currency) }}</td>
    <td>{{ $money((int) ($refund->refunded_to_original_minor ?? 0), $refund->currency ?? $currency) }}</td>
    <td>{{ $labelize($refund->wallet_status ?? '—') }}</td>
    <td>{{ $labelize($refund->external_status ?? '—') }}</td>
    <td class="ab-mono">{{ $refund->provider_refund_id ?? '—' }}</td>
    <td class="ab-mono">{{ $refund->provider_order_id ?? '—' }}</td>
    <td class="ab-mono">{{ $refund->provider_capture_id ?? '—' }}</td>
    <td>{{ $formatDt($refund->requested_at ?? $refund->created_at ?? null) }}</td>
    <td>{{ $formatDt($refund->processed_at ?? null) }}</td>
    <td>{{ $refund->failure_reason ?? '—' }}</td>
</tr>
                            @empty
                                <tr>
                                    <td colspan="19" class="ab-empty">No refunds found for this booking.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ab-card ab-center">
                <div class="ab-cardHead ab-center">
                    <h2 class="ab-cardTitle">Disputes</h2>
                    <p class="ab-cardSub">Dispute history, resolution status, and decisions.</p>
                </div>

                <div class="ab-tableWrap">
                    <table class="ab-table ab-table--caps">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Dispute Id</th>
                                <th>Opened By</th>
                                <th>Status</th>
                                <th>Subject</th>
                                <th>Decision</th>
                                <th>Opened At</th>
                                <th>Resolved At</th>
                                <th>Resolved By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($disputeRows as $i => $dispute)
                                @php
                                    $resolver = $dispute->resolvedBy ?? null;
                                    $resolverName = $fullName($resolver, '—');
                                @endphp
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td class="ab-mono">DP-{{ str_pad((string) $dispute->id, 8, '0', STR_PAD_LEFT) }}</td>
                                    <td>{{ $labelize($dispute->opened_by_role ?? '—') }}</td>
                                    <td>
                                        <span class="ab-pill {{ $pillClass('dispute', $dispute->status) }}">
                                            {{ $labelize($dispute->status ?? 'open') }}
                                        </span>
                                    </td>
                                    <td>{{ $dispute->subject ?? $dispute->reason ?? '—' }}</td>
                                    <td>{{ $dispute->decision_action ? $labelize($dispute->decision_action) : '—' }}</td>
                                    <td>{{ $formatDt($dispute->created_at ?? null) }}</td>
                                    <td>{{ $formatDt($dispute->resolved_at ?? $dispute->decided_at ?? null) }}</td>
                                    <td>{{ $resolverName }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="ab-empty">No disputes found for this booking.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ab-card ab-center">
                <div class="ab-cardHead ab-center">
                    <h2 class="ab-cardTitle">Activity Timeline</h2>
                    <p class="ab-cardSub">Chronological event log from booking creation through final outcomes.</p>
                </div>

                <div class="ab-tableWrap">
                    <table class="ab-table ab-table--caps">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Event</th>
                                <th>Type</th>
                                <th>Meta</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($timeline as $i => $item)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $item['label'] ?? '—' }}</td>
                                    <td>{{ $labelize($item['type'] ?? 'event') }}</td>
                                    <td>{{ !empty($item['meta']) ? $labelize($item['meta']) : '—' }}</td>
                                    <td>{{ $formatDt($item['at'] ?? null) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="ab-empty">No activity found for this booking.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
@endsection