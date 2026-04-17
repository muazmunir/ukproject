@extends('layouts.role-dashboard')

@section('role-content')

@if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
@if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

@php
  $withdrawable = ((int)($withdrawableMinor ?? 0)) / 100;
  $processing   = ((int)($processingMinor ?? 0)) / 100;

  $defaultMethod = ($methods ?? collect())->firstWhere('is_default', true) ?? ($methods ?? collect())->first();
@endphp

<div class="container-narrow">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h3 class="mb-0">{{ __('Earnings') }}</h3>
      <div class="text-muted small mt-1">
        {{ __('Withdrawals are processed automatically and released within 7 days.') }}
      </div>
    </div>

    <span class="badge {{ $kycOk ? 'bg-success' : 'bg-warning text-dark' }}">
      {{ $kycOk ? __('KYC Approved') : __('KYC Required') }}
    </span>
  </div>

  {{-- Balance cards --}}
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="text-muted small">{{ __('Withdrawable') }}</div>
          <div class="fs-3 fw-semibold">${{ number_format($withdrawable, 2) }}</div>
          <div class="text-muted small">{{ __('Available now') }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="text-muted small">{{ __('Processing') }}</div>
          <div class="fs-3 fw-semibold">${{ number_format($processing, 2) }}</div>
          <div class="text-muted small">{{ __('Scheduled release (up to 7 days)') }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Payout Methods --}}
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">

      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h5 class="mb-0">{{ __('Payout methods') }}</h5>
          <div class="text-muted small mt-1">
            {{ __('Add PayPal email or connect Stripe for automatic payouts.') }}
          </div>
        </div>

        {{-- Stripe Connect CTA (Fiverr-like) --}}
        <div class="d-flex gap-2">
          @if($kycOk)
            <a href="{{ route('coach.stripe.connect') }}" class="btn btn-dark">
              <i class="bi bi-link-45deg me-1"></i>{{ __('Connect Stripe') }}
            </a>
          @else
            <a href="{{ route('coach.kyc.show') }}" class="btn btn-outline-dark">
              <i class="bi bi-shield-check me-1"></i>{{ __('Complete KYC') }}
            </a>
          @endif
        </div>
      </div>

      {{-- Existing methods --}}
      @if(($methods ?? collect())->count())
        <div class="list-group mb-3">
          @foreach($methods as $m)
            @php
              $type  = strtolower((string)($m->type ?? ''));
              $label = $m->label ?: ucfirst($type);

              $status = strtolower((string)($m->status ?? 'pending')); // pending|active|disabled
              $statusBadge = $status === 'active'
                  ? 'bg-success'
                  : ($status === 'disabled' ? 'bg-danger' : 'bg-warning text-dark');

              // Email snapshot (paypal) OR stripe acct id
              $email = $m->details['email'] ?? '';
              $acct  = $m->details['stripe_account_id'] ?? null;

              $subtitle = $type === 'stripe'
                  ? ($acct ? ('acct: '.$acct) : __('Not connected yet'))
                  : ($email ?: __('No email'));
            @endphp

            <div class="list-group-item d-flex align-items-center justify-content-between">
              <div>
                <div class="fw-semibold d-flex align-items-center gap-2 flex-wrap">
                  <span>{{ $label }}</span>
                  <span class="text-muted small">• {{ ucfirst($type) }}</span>

                  <span class="badge {{ $statusBadge }}">{{ ucfirst($status) }}</span>

                  @if($m->is_default)
                    <span class="badge bg-dark">{{ __('Default') }}</span>
                  @endif
                </div>

                <div class="text-muted small mt-1">
                  {{ $subtitle }}
                </div>

                @if($type === 'stripe' && $acct && $status !== 'active')
                  <div class="text-muted small mt-1">
                    {{ __('Stripe may still be verifying your account. Try reconnecting if needed.') }}
                    <a class="ms-1" href="{{ route('coach.stripe.connect') }}">{{ __('Continue') }}</a>
                  </div>
                @endif
              </div>

              <div class="d-flex gap-2">
                @if(!$m->is_default)
                  <form method="POST" action="{{ route('coach.payout_methods.default', $m) }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-dark">{{ __('Make default') }}</button>
                  </form>
                @endif

                <form method="POST" action="{{ route('coach.payout_methods.destroy', $m) }}"
                      onsubmit="return confirm('{{ __('Remove this payout method?') }}')">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger">{{ __('Remove') }}</button>
                </form>
              </div>
            </div>
          @endforeach
        </div>
      @else
        <div class="alert alert-light border mb-3">
          {{ __('No payout methods yet. Add PayPal or connect Stripe to withdraw.') }}
        </div>
      @endif

      {{-- Add PayPal method only (Stripe should be connected via button) --}}
      <div class="border-top pt-3">
        <h6 class="mb-2">{{ __('Add PayPal') }}</h6>

        <form method="POST" action="{{ route('coach.payout_methods.store') }}" class="row g-3">
          @csrf
          <input type="hidden" name="type" value="paypal">

          <div class="col-md-4">
            <label class="form-label">{{ __('Label (optional)') }}</label>
            <input name="label" class="form-control" placeholder="{{ __('e.g. My PayPal') }}">
          </div>

          <div class="col-md-6">
            <label class="form-label">{{ __('PayPal Email') }}</label>
            <input name="email" type="email" class="form-control" required placeholder="name@example.com">
          </div>

          <div class="col-md-2 d-flex align-items-end">
            <input type="hidden" name="make_default" value="1">
            <button class="btn btn-dark w-100">{{ __('Add') }}</button>
          </div>
        </form>

        <div class="text-muted small mt-2">
          {{ __('Stripe payouts require Stripe Connect. PayPal payouts are sent to your PayPal email.') }}
        </div>
      </div>

    </div>
  </div>

  {{-- Withdraw --}}
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <h5 class="mb-3">{{ __('Withdraw') }}</h5>

      @if(!$kycOk)
        <div class="alert alert-warning mb-0">
          {{ __('You must complete and get approval for KYC before requesting a withdrawal.') }}
        </div>

      @elseif(($methods ?? collect())->isEmpty())
        <div class="alert alert-warning mb-0">
          {{ __('Add a payout method first (PayPal) or connect Stripe.') }}
        </div>

      @elseif($withdrawable <= 0)
        <div class="alert alert-light border mb-0">
          {{ __('You have no withdrawable balance yet.') }}
        </div>

      @else
        <form method="POST" action="{{ route('coach.withdraw.store') }}" class="row g-3">
          @csrf

          <div class="col-md-6">
            <label class="form-label">{{ __('Amount (USD)') }}</label>
            <div class="input-group">
              <input
                id="wd-amount"
                type="number"
                step="0.01"
                min="1"
                max="{{ $withdrawable }}"
                name="amount"
                class="form-control"
                required
                placeholder="0.00">
              <button class="btn btn-outline-dark" type="button"
                      onclick="document.getElementById('wd-amount').value='{{ number_format($withdrawable,2,'.','') }}'">
                {{ __('All') }}
              </button>
            </div>
            <div class="text-muted small mt-1">
              {{ __('Max:') }} ${{ number_format($withdrawable,2) }}
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label">{{ __('Payout method') }}</label>
            <select name="payout_method_id" class="form-select" required>
              @foreach($methods as $m)
                @php
                  $type = strtolower((string)$m->type);
                  $status = strtolower((string)($m->status ?? 'pending'));

                  $email = $m->details['email'] ?? '';
                  $acct  = $m->details['stripe_account_id'] ?? null;

                  $label = ($m->label ?: ucfirst($type));

                  // disable Stripe if not active / not connected
                  $disabled = ($type === 'stripe' && ($status !== 'active' || !$acct));
                  $desc = $type === 'stripe'
                      ? ($acct ? 'Stripe (connected)' : 'Stripe (not connected)')
                      : ('PayPal: '.$email);
                @endphp

                <option value="{{ $m->id }}"
                        {{ $defaultMethod && $m->id===$defaultMethod->id ? 'selected' : '' }}
                        {{ $disabled ? 'disabled' : '' }}>
                  {{ $label }} — {{ $desc }} {{ $m->is_default ? '(Default)' : '' }}
                </option>
              @endforeach
            </select>
            <div class="text-muted small mt-1">
              {{ __('Stripe must be connected and active to withdraw.') }}
            </div>
          </div>

          <div class="col-12 d-flex justify-content-end">
            <button class="btn btn-dark">
              <i class="bi bi-arrow-up-right-circle me-1"></i>{{ __('Request withdrawal') }}
            </button>
          </div>
        </form>
      @endif
    </div>
  </div>

  {{-- Recent withdrawals --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <h5 class="mb-3">{{ __('Withdrawal history') }}</h5>

      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>{{ __('Requested') }}</th>
              <th>{{ __('Method') }}</th>
              <th>{{ __('Amount') }}</th>
              <th>{{ __('Status') }}</th>
              <th>{{ __('Release date') }}</th>
            </tr>
          </thead>
          <tbody>
          @forelse($withdrawals as $w)
            <tr>
              <td class="small">{{ $w->requested_at?->format('d M Y, h:i A') }}</td>
              <td class="text-capitalize">{{ $w->method }}</td>
              <td>${{ number_format($w->amount_minor/100, 2) }}</td>
              <td>
                <span class="badge {{ $w->status==='released' ? 'bg-success' : ($w->status==='failed' ? 'bg-danger' : 'bg-warning text-dark') }}">
                  {{ ucfirst($w->status) }}
                </span>
              </td>
              <td class="small">{{ $w->release_at?->format('d M Y') }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No withdrawals yet.') }}</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection
