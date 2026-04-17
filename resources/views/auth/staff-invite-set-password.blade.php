@extends('layouts.app')
@section('title','Set Password')

@section('content')
<div class="container my-4" style="max-width:480px;">
  <div class="card p-4">
    <h4 class="mb-1">Set your password</h4>
    <div class="text-muted mb-3">
      You’re joining as <strong class="text-capitalize">{{ $user->role }}</strong>.
      ({{ $user->email }})
    </div>

    @if($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('staff.invite.store', $invite->token) }}">
      @csrf

      <label class="form-label">New password</label>
      <input class="form-control mb-2" type="password" name="password" required>

      <label class="form-label">Confirm password</label>
      <input class="form-control mb-3" type="password" name="password_confirmation" required>

      <button class="btn btn-dark w-100">Set password & Continue</button>
    </form>
  </div>
</div>
@endsection
