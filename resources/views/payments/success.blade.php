{{-- resources/views/payments/success.blade.php --}}
@extends('layouts.app')
@section('title','Payment processing')
@section('content')
<div class="container py-5">
  <h3 class="mb-2">Thanks! We’re confirming your payment…</h3>
  <p class="text-muted">You’ll see the booking in your reservations shortly. If this page doesn’t update, please check your email for a receipt.</p>
  <a href="{{ url('/dashboard') }}" class="btn btn-dark mt-3">Go to dashboard</a>
</div>
@endsection
