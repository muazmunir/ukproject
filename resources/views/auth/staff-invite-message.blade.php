@extends('layouts.app')
@section('title', $title ?? 'Invite')

@section('content')
<div class="container my-4" style="max-width:520px;">
  <div class="card p-4">
    <h4 class="mb-2">{{ $title ?? 'Invite' }}</h4>
    <p class="text-muted mb-3">{{ $message ?? '' }}</p>
    <a class="btn btn-dark" href="{{ route('login') }}">Go to login</a>
  </div>
</div>
@endsection
