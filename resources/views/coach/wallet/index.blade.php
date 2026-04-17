@extends('coach.layout')

@section('coach-content')
<div class="zv-panel">
  <h6 class="mb-2">{{ __('Wallet') }}</h6>
  <p class="zv-muted mb-3">
    {{ __('Your available balance:') }}
    <strong>{{ number_format((float) (auth()->user()->wallet_balance ?? 0), 2) }}</strong>
  </p>
  <a class="btn-3d btn-plain" href="{{ route('coach.wallet.withdraw') }}">
    <i class="bi bi-cash-coin me-1"></i>{{ __('Withdraw funds') }}
  </a>
</div>
@endsection
