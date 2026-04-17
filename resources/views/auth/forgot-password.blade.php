@extends('layouts.app')

@section('title','Forgot Password')

@section('content')
<div class="container" style="max-width:420px">
  <h3 class="mb-3">Reset your password</h3>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif

  <form method="POST" action="{{ route('password.email') }}">
    @csrf

    <label>Email</label>
    <input class="form-control mb-3" type="email" name="email" required>

    <button class="btn btn-dark w-100">Send Reset Link</button>
  </form>
</div>
@endsection
