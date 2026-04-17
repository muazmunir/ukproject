@extends('layouts.app')
@section('title', __('Payout Settings'))

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/payout.css') }}">
@endpush

@section('content')
@php
    $isApproved = $coachProfile->application_status === 'approved';
    $applicationLabel = ucfirst(str_replace('_', ' ', $coachProfile->application_status));

    $account = $stripeAccount
        ?? $coachProfile->payoutAccounts->first(function ($item) {
            return strtolower((string) $item->provider) === 'stripe' && (bool) $item->is_default;
        });

    if (!$account) {
        $account = $coachProfile->defaultPayoutAccount
            && strtolower((string) $coachProfile->defaultPayoutAccount->provider) === 'stripe'
                ? $coachProfile->defaultPayoutAccount
                : null;
    }

    $requirementsDue = array_values((array) ($account?->requirements_currently_due ?? []));
    $pastDueRequirements = array_values((array) ($account?->requirements_past_due ?? []));
    $eventuallyDue = array_values((array) ($account?->requirements_eventually_due ?? []));

    $hasAccount = (bool) $account;
    $methods = $hasAccount && $account->payoutMethods ? $account->payoutMethods : collect();
    $hasMethods = $methods->count() > 0;

    $accountStatus = strtolower((string) ($account->status ?? 'not_connected'));
    $accountStatusLabel = ucfirst(str_replace('_', ' ', $accountStatus));

    $accountStatusClass = match($accountStatus) {
        'verified' => 'ps-badge--success',
        'restricted', 'disabled', 'failed' => 'ps-badge--danger',
        'pending_verification', 'onboarding_required', 'pending' => 'ps-badge--warning',
        default => 'ps-badge--muted',
    };

    $setupState = match (true) {
        !$isApproved => 'locked',
        !$hasAccount => 'not_started',
        $account?->payouts_enabled => 'complete',
        !empty($pastDueRequirements) => 'restricted',
        !empty($requirementsDue) => 'action_required',
        default => 'in_review',
    };

    $setupTitle = match ($setupState) {
        'locked' => __('Approval required'),
        'not_started' => __('Connect Stripe'),
        'complete' => __('Stripe account ready'),
        'restricted' => __('Restricted account'),
        'action_required' => __('Complete required details'),
        default => __('Stripe setup in review'),
    };

    $setupText = match ($setupState) {
        'locked' => __('Your coach application must be approved before you can enable Stripe payouts.'),
        'not_started' => __('Connect your Stripe account to receive withdrawals securely to your bank account.'),
        'complete' => __('Your Stripe Connect account is active and ready to receive payouts.'),
        'restricted' => __('Stripe needs additional information before payouts can continue.'),
        'action_required' => __('Finish the remaining Stripe requirements to activate payouts.'),
        default => __('Your Stripe account exists, but Stripe is still reviewing or waiting for remaining details.'),
    };

    $primaryButtonLabel = match (true) {
        !$isApproved => __('Application pending'),
        !$hasAccount => __('Connect Stripe'),
        default => __('Continue Stripe setup'),
    };

    $defaultMethod = $methods->firstWhere('is_default', true) ?? $methods->first();

    $checklist = collect([
        [
            'label' => __('Coach application approved'),
            'done' => $isApproved,
        ],
        [
            'label' => __('Stripe account created'),
            'done' => $hasAccount,
        ],
        [
            'label' => __('Payout method available'),
            'done' => $hasMethods,
        ],
        [
            'label' => __('Payouts enabled'),
            'done' => (bool) ($account?->payouts_enabled),
        ],
    ]);

    $completedCount = $checklist->where('done', true)->count();
@endphp

<div class="ps-page py-4 py-lg-5">
    <div class="container">
        <div class="ps-wrap">

            @if(session('ok'))
                <div class="alert alert-success ps-alert">{{ session('ok') }}</div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger ps-alert">{{ session('error') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger ps-alert">
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="ps-hero">
                <div class="ps-hero__top">
                    <div class="ps-brand">
                        <div class="ps-brand__logo">
                            <img src="{{ asset('assets/stripe-lgo.png') }}" alt="Stripe logo">
                        </div>

                        <div class="ps-brand__copy">
                            <span class="ps-brand__eyebrow text-capitalize">{{ __('Stripe Connect') }}</span>
                            <h1 class="ps-page-title text-capitalize">{{ __('Payout Settings') }}</h1>
                            <p class="ps-page-subtitle text-capitalize">
                                {{ __('Manage your Stripe onboarding, payout readiness, and default payout destination in one place.') }}
                            </p>
                        </div>
                    </div>

                    <div class="ps-hero__actions">
                        @if($isApproved)
                            @if(!$hasAccount)
                                <form method="POST" action="{{ route('coach.payouts.start') }}">
                                    @csrf
                                    <button type="submit" class="ps-btn ps-btn--primary text-capitalize">
                                        {{ $primaryButtonLabel }}
                                    </button>
                                </form>
                            @endif

                            @if($hasAccount)
                                <form method="POST" action="{{ route('coach.payouts.refresh') }}">
                                    @csrf
                                    <button type="submit" class="ps-btn ps-btn--ghost text-capitalize">
                                        {{ __('Refresh Status') }}
                                    </button>
                                </form>
                            @endif
                        @else
                            <button type="button" class="ps-btn ps-btn--ghost text-capitalize" disabled>
                                {{ __('Waiting For Approval') }}
                            </button>
                        @endif
                    </div>
                </div>

                <div class="ps-hero__stats">
                    <div class="ps-stat-card">
                        <span class="ps-stat-card__label text-capitalize">{{ __('Coach Status') }}</span>
                        <span class="ps-stat-card__value text-capitalize">
                            {{ $isApproved ? __('Approved') : $applicationLabel }}
                        </span>
                    </div>

                    <div class="ps-stat-card">
                        <span class="ps-stat-card__label text-capitalize">{{ __('Stripe Status') }}</span>
                        <span class="ps-stat-card__value">
                            <span class="ps-badge {{ $hasAccount ? $accountStatusClass : 'ps-badge--muted' }} text-capitalize">
                                {{ $hasAccount ? $accountStatusLabel : __('Not Connected') }}
                            </span>
                        </span>
                    </div>

                    <div class="ps-stat-card">
                        <span class="ps-stat-card__label text-capitalize">{{ __('Payouts Enabled') }}</span>
                        <span class="ps-stat-card__value text-capitalize">
                            {{ $account?->payouts_enabled ? __('Yes') : __('No') }}
                        </span>
                    </div>

                    <div class="ps-stat-card ps-stat-card--action">
                        <span class="ps-stat-card__label text-capitalize">{{ __('Stripe Account') }}</span>

                        @if($isApproved)
                            <form method="POST" action="{{ route('coach.payouts.start') }}" class="ps-stat-card__form">
                                @csrf
                                <button type="submit" class="ps-btn ps-btn--primary ps-btn--full text-capitalize">
                                    {{ $hasAccount ? __('Manage Stripe Account') : __('Connect Stripe') }}
                                </button>
                            </form>
                        @else
                            <button type="button" class="ps-btn ps-btn--ghost ps-btn--full text-capitalize" disabled>
                                {{ __('Waiting For Approval') }}
                            </button>
                        @endif
                    </div>
                </div>
            </section>

            <div class="ps-layout">
                <section class="ps-card ps-card--feature ps-layout__feature">
                    <div class="ps-card__head ps-card__head--spread">
                        <div class="ps-card__head-copy">
                            <h2 class="ps-card__title text-capitalize">{{ $setupTitle }}</h2>
                            <p class="ps-card__subtitle text-capitalize">{{ $setupText }}</p>
                        </div>

                        <div class="ps-progress-pill text-capitalize">
                            {{ __(':done Of :total Completed', ['done' => $completedCount, 'total' => $checklist->count()]) }}
                        </div>
                    </div>

                    <div class="ps-checklist">
                        @foreach($checklist as $item)
                            <div class="ps-checklist__item {{ $item['done'] ? 'is-done' : '' }}">
                                <span class="ps-checklist__icon" aria-hidden="true">
                                    @if($item['done'])
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20 6 9 17l-5-5"></path>
                                        </svg>
                                    @else
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="8"></circle>
                                        </svg>
                                    @endif
                                </span>
                                <span class="ps-checklist__label text-capitalize">{{ $item['label'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if($hasAccount && (!empty($requirementsDue) || !empty($pastDueRequirements)))
                        <div class="ps-note ps-note--warning">
                            <div class="ps-note__title text-capitalize">{{ __('Stripe Still Needs Information') }}</div>
                            <div class="ps-note__text">
                                {{ __('Complete the missing Stripe details below, then continue onboarding or refresh your account.') }}
                            </div>

                            <div class="ps-requirement-grid">
                                @foreach(array_slice(array_merge($pastDueRequirements, $requirementsDue), 0, 8) as $item)
                                    <div class="ps-requirement-chip text-capitalize">
                                        {{ ucfirst(str_replace('_', ' ', $item)) }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>

                <section class="ps-card ps-layout__methods">
                    <div class="ps-card__head">
                        <div class="ps-card__head-copy">
                            <h2 class="ps-card__title text-capitalize">{{ __('Payout Method') }}</h2>
                            <p class="ps-card__subtitle text-capitalize">
                                {{ __('Your payout destination is synced from your Stripe account.') }}
                            </p>
                        </div>
                    </div>

                    @if(!$hasAccount)
                        <div class="ps-empty mt-3">
                            <div class="ps-empty__title text-capitalize">{{ __('No Stripe Account Connected Yet') }}</div>
                            <p class="ps-empty__text">
                                {{ __('Connect Stripe first. Once setup begins, your bank account or eligible payout destinations will appear here automatically.') }}
                            </p>

                            @if($isApproved)
                                <form method="POST" action="{{ route('coach.payouts.start') }}" class="mt-3">
                                    @csrf
                                    <button type="submit" class="ps-btn ps-btn--primary text-capitalize">
                                        {{ __('Connect Stripe') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    @elseif(!$hasMethods)
                        <div class="ps-empty mt-2">
                            <div class="ps-empty__title text-capitalize">{{ __('No Payout Method Available Yet') }}</div>
                            <p class="ps-empty__text">
                                {{ __('Complete Stripe onboarding and add a payout destination in Stripe. Then refresh this page.') }}
                            </p>

                            <div class="ps-empty__actions mt-2">
                                <form method="POST" action="{{ route('coach.payouts.start') }}">
                                    @csrf
                                    <button type="submit" class="ps-btn ps-btn--primary text-capitalize">
                                        {{ __('Open Stripe Setup') }}
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('coach.payouts.refresh') }}">
                                    @csrf
                                    <button type="submit" class="ps-btn ps-btn--ghost text-capitalize">
                                        {{ __('Refresh Methods') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <form method="POST" action="{{ route('coach.payouts.methods.default') }}" id="defaultMethodForm">
                            @csrf

                            <div class="ps-method-list mt-3">
                                @foreach($methods as $method)
                                    @php
                                        $rawType = strtolower((string) ($method->type ?? 'bank_account'));

                                        $methodLabel = match($rawType) {
                                            'bank_account', 'bank' => __('Bank Account'),
                                            'card' => __('Card'),
                                            default => ucfirst(str_replace('_', ' ', $rawType)),
                                        };

                                        $maskedNumber = !empty($method->last4)
                                            ? '•••• ' . $method->last4
                                            : __('Not Available');

                                        $methodStatus = strtolower((string) ($method->status ?? 'active'));
                                        $methodStatusClass = match($methodStatus) {
                                            'active' => 'ps-badge--success',
                                            'failed' => 'ps-badge--danger',
                                            'pending' => 'ps-badge--warning',
                                            default => 'ps-badge--muted',
                                        };

                                        $methodStatusLabel = ucfirst(str_replace('_', ' ', $methodStatus));
                                    @endphp

                                    <label class="ps-method-row {{ $method->is_default ? 'is-selected' : '' }}">
                                        <input
                                            type="radio"
                                            name="method_id"
                                            value="{{ $method->id }}"
                                            class="ps-method-row__input"
                                            {{ $method->is_default ? 'checked' : '' }}
                                        >

                                        <span class="ps-method-row__control" aria-hidden="true"></span>

                                        <span class="ps-method-row__body">
                                            <span class="ps-method-row__left">
                                                <span class="ps-method-row__icon" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                        <rect x="3.5" y="6.5" width="17" height="11" rx="2"></rect>
                                                        <path d="M3.5 10.5h17"></path>
                                                    </svg>
                                                </span>

                                                <span class="ps-method-row__copy">
                                                    <span class="ps-method-row__titleline">
                                                        <span class="ps-method-row__title text-capitalize">{{ $methodLabel }}</span>
                                                        <span class="ps-method-row__mask">{{ $maskedNumber }}</span>
                                                    </span>

                                                    <span class="ps-method-row__meta text-capitalize">
                                                        @if(!empty($method->bank_name))
                                                            <span>{{ $method->bank_name }}</span>
                                                        @endif

                                                        @if(!empty($method->country))
                                                            <span class="ps-dot">•</span>
                                                            <span>{{ strtoupper($method->country) }}</span>
                                                        @endif

                                                        @if(!empty($method->currency))
                                                            <span class="ps-dot">•</span>
                                                            <span>{{ strtoupper($method->currency) }}</span>
                                                        @endif
                                                    </span>
                                                </span>
                                            </span>

                                            <span class="ps-method-row__right">
                                                <span class="ps-badge {{ $methodStatusClass }} text-capitalize">{{ $methodStatusLabel }}</span>

                                                @if($method->is_default)
                                                    <span class="ps-badge ps-badge--dark text-capitalize">{{ __('Default') }}</span>
                                                @endif
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </form>
                    @endif
                </section>

                <aside class="ps-layout__side">
                    <section class="ps-card ps-card--sidefill">
                        <div class="ps-card__head">
                            <div class="ps-card__head-copy">
                                <h2 class="ps-card__title text-capitalize">{{ __('Stripe Account Overview') }}</h2>
                            </div>
                        </div>

                        <div class="ps-summary">
                            <div class="ps-summary__row">
                                <span class="ps-summary__label text-capitalize">{{ __('Provider') }}</span>
                                <span class="ps-summary__value text-capitalize">{{ __('Stripe Connect') }}</span>
                            </div>

                            <div class="ps-summary__row">
                                <span class="ps-summary__label text-capitalize">{{ __('Account Status') }}</span>
                                <span class="ps-summary__value">
                                    <span class="ps-badge {{ $hasAccount ? $accountStatusClass : 'ps-badge--muted' }} text-capitalize">
                                        {{ $hasAccount ? $accountStatusLabel : __('Not Connected') }}
                                    </span>
                                </span>
                            </div>

                            <div class="ps-summary__row">
                                <span class="ps-summary__label text-capitalize">{{ __('Country') }}</span>
                                <span class="ps-summary__value">
                                    {{ $account?->country ? strtoupper($account->country) : '—' }}
                                </span>
                            </div>

                            <div class="ps-summary__row">
                                <span class="ps-summary__label text-capitalize">{{ __('Default Currency') }}</span>
                                <span class="ps-summary__value">
                                    {{ $account?->default_currency ? strtoupper($account->default_currency) : '—' }}
                                </span>
                            </div>

                            <div class="ps-summary__row">
                                <span class="ps-summary__label text-capitalize">{{ __('Current Default') }}</span>
                                <span class="ps-summary__value">
                                    @if($defaultMethod)
                                        {{ !empty($defaultMethod->last4) ? '•••• '.$defaultMethod->last4 : __('Available') }}
                                    @else
                                        —
                                    @endif
                                </span>
                            </div>
                        </div>

                        @if($hasAccount && !empty($requirementsDue))
                            <div class="ps-note ps-note--warning">
                                <div class="ps-note__title text-capitalize">{{ __('Current Requirements') }}</div>
                                <ul class="ps-note-list">
                                    @foreach(array_slice($requirementsDue, 0, 6) as $item)
                                        <li class="text-capitalize">{{ ucfirst(str_replace('_', ' ', $item)) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if($hasAccount && empty($requirementsDue) && !$account?->payouts_enabled)
                            <div class="ps-note ps-note--soft">
                                <div class="ps-note__title text-capitalize">{{ __('Almost Done') }}</div>
                                <div class="ps-note__text text-capitalize">
                                    {{ __('Your Stripe account exists. Refresh the status after finishing setup in Stripe.') }}
                                </div>
                            </div>
                        @endif

                        @if($account?->payouts_enabled)
                            <div class="ps-note ps-note--success">
                                <div class="ps-note__title text-capitalize">{{ __('Ready For Payouts') }}</div>
                                <div class="ps-note__text text-capitalize">
                                    {{ __('Your Stripe Account Is Enabled And Can Receive Future Coach Payouts.') }}
                                </div>
                            </div>
                        @endif

                        @if(!$hasAccount)
                            <div class="ps-note ps-note--soft">
                                <div class="ps-note__title text-capitalize">{{ __('Next Step') }}</div>
                                <div class="ps-note__text text-capitalize">
                                    {{ __('Connect Stripe and complete onboarding to enable withdrawals to your payout destination.') }}
                                </div>
                            </div>
                        @endif
                    </section>
                </aside>
            </div>
        </div>
    </div>
</div>

@if($isApproved && $hasMethods)
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const defaultMethodForm = document.getElementById('defaultMethodForm');

        if (defaultMethodForm) {
            defaultMethodForm.querySelectorAll('input[name="method_id"]').forEach(function (input) {
                input.addEventListener('change', function () {
                    defaultMethodForm.submit();
                });
            });
        }
    });
</script>
@endif
@endsection