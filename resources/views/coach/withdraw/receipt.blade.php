<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Payout Receipt') }} #{{ $payout->id }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/payout-receipt.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
@php
    $receiptNo = 'PR-' . str_pad((string) $payout->id, 6, '0', STR_PAD_LEFT);
    $amount = number_format(($payout->amount_minor ?? 0) / 100, 2);
    $currency = strtoupper($payout->currency ?? 'USD');
    $status = strtolower((string) ($payout->status ?? 'pending'));

    $statusClass = match($status) {
        'paid' => 'is-success',
        'processing', 'payout_pending', 'transfer_created', 'pending' => 'is-warning',
        'failed', 'reversed' => 'is-danger',
        default => 'is-muted',
    };

    $statusLabel = ucfirst(str_replace('_', ' ', $status));
    $providerReference = $payout->provider_payout_id ?: ($payout->provider_transfer_id ?: '—');
    $provider = ucfirst($payout->provider ?? '—');
    $generatedAt = now()->format('M d, Y h:i A');

    $coachUser = $payout->coachProfile?->user;
    $coachName = trim(($coachUser?->first_name ?? '') . ' ' . ($coachUser?->last_name ?? '')) ?: __('Coach');
    $coachEmail = $coachUser?->email ?? '—';
@endphp

<div class="pr-page">
    <div class="pr-sheet">

        <header class="pr-top-grid">
            <section class="pr-card pr-card--intro">
                <div class="pr-intro-logo">
                    <img src="{{ asset('assets/logo.png') }}" alt="Logo" class="pr-logo">
                </div>

                <div class="pr-intro-copy">
                    <div class="pr-eyebrow">{{ __('Official Payout Receipt') }}</div>
                    <h1 class="pr-title">{{ __('Payout Receipt') }}</h1>
                    <p class="pr-subtitle text-capitalize">
                        {{ __('Payout processed via your selected payout provider.') }}
                    </p>
                </div>
            </section>

            <section class="pr-card pr-card--docbox">
                <div class="pr-doc-item">
                    <span>{{ __('Receipt ID:') }}</span>
                    <strong>{{ $receiptNo }}</strong>
                </div>

                <div class="pr-doc-item">
                    <span>{{ __('Generated:') }}</span>
                    <strong>{{ $generatedAt }}</strong>
                </div>

                <div class="pr-doc-item">
                    <span>{{ __('Status:') }}</span>
                    <strong>
                        <span class="pr-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                    </strong>
                </div>
            </section>
        </header>

        <section class="pr-stats-grid">
            <div class="pr-card pr-card--amount">
                <div class="pr-stat-inner">
                    <div class="pr-label">{{ __('Total Payout') }}</div>
                    <div class="pr-amount">${{ $amount }}</div>
                    <div class="pr-currency">{{ $currency }}</div>
                </div>
            </div>

            <div class="pr-card pr-card--provider-status">
                <div class="pr-stat-inner">
                    <div class="pr-label">{{ __('Provider') }}</div>
                    <div class="pr-value text-capitalize">{{ $provider }}</div>

                    <div class="pr-provider-status-meta">
                        <div class="pr-provider-status-caption">{{ __('Status') }}</div>
                        <div class="pr-provider-status-badge">
                            <span class="pr-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="pr-info-grid">
            <div class="pr-card pr-card--recipient">
                <div class="pr-card__head">
                    <h2>{{ __('Recipient') }}</h2>
                </div>

                <div class="pr-party">
                    <div class="pr-party__name">{{ $coachName }}</div>
                    <div class="pr-party__sub">{{ $coachEmail }}</div>
                    <div class="pr-party__meta">{{ __('Coach Payout Recipient') }}</div>
                </div>
            </div>

            <div class="pr-card pr-card--summary">
                <div class="pr-card__head">
                    <h2>{{ __('Transaction Summary') }}</h2>
                </div>

                <div class="pr-summary-list">
                    <div class="pr-summary-row">
                        <span>{{ __('Payout Amount') }}</span>
                        <strong>${{ $amount }} {{ $currency }}</strong>
                    </div>

                    <div class="pr-summary-row">
                        <span>{{ __('Provider') }}</span>
                        <strong>{{ $provider }}</strong>
                    </div>

                    <div class="pr-summary-row">
                        <span>{{ __('Reference ID') }}</span>
                        <strong>{{ $providerReference }}</strong>
                    </div>

                    <div class="pr-summary-row">
                        <span>{{ __('Failure Reason') }}</span>
                        <strong>{{ $payout->failure_reason ?: '—' }}</strong>
                    </div>
                </div>
            </div>
        </section>

        <section class="pr-card pr-card--table">
            <div class="pr-card__head pr-card__head--table">
                <h2>{{ __('Receipt Details') }}</h2>
            </div>

            <div class="pr-table">
                <div class="pr-row pr-row--head">
                    <div>{{ __('Description') }}</div>
                    <div>{{ __('Provider') }}</div>
                    <div>{{ __('Date') }}</div>
                    <div>{{ __('Amount') }}</div>
                </div>

                <div class="pr-row pr-row--body">
                    <div class="pr-row__title">{{ __('Coach Payout') }}</div>
                    <div>{{ $provider }}</div>
                    <div>{{ $payout->created_at?->format('M d, Y') }}</div>
                    <div class="pr-row__amount">${{ $amount }} {{ $currency }}</div>
                </div>
            </div>
        </section>

        <section class="pr-card pr-card--note">
            <div class="pr-card__head">
                <h2>{{ __('Important Note') }}</h2>
            </div>
            <p class="pr-note-text text-capitalize">
                {{ __('The platform acts as an intermediary and is not responsible for any applicable taxes, including VAT. Recipients are responsible for reporting this income in accordance with applicable tax laws.') }}
            </p>
        </section>

        <footer class="pr-footer">
            <div class="pr-footer__right no-print">
                <a href="{{ route('coach.withdraw.index') }}" class="pr-btn pr-btn--light">
                    {{ __('Back') }}
                </a>
                <button type="button" class="pr-btn pr-btn--dark" onclick="window.print()">
                    {{ __('Print / Save PDF') }}
                </button>
            </div>
        </footer>

    </div>
</div>
</body>
</html>