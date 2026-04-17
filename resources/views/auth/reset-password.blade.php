@extends('layouts.app')

@section('title','Set Password')

@section('content')
<div class="container" style="max-width:420px">
  <h3 class="mb-3">Set new password</h3>

  <form method="POST" action="{{ route('password.update') }}">
    @csrf

    <input type="hidden" name="token" value="{{ $token }}">

    <label>Email</label>
    <input class="form-control mb-2" type="email" name="email" required>

    <label>Password</label>
    <input class="form-control mb-2" type="password" name="password" required>

    <label>Confirm Password</label>
    <input class="form-control mb-3" type="password" name="password_confirmation" required>

    <button class="btn btn-dark w-100">Set Password</button>
  </form>
</div>
@endsection
