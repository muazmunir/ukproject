@extends('layouts.role-dashboard')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/client-profile.css') }}">
  <link rel="stylesheet" href="https://unpkg.com/cropperjs@1.5.13/dist/cropper.min.css">
@endpush

@section('role-content')

@if(session('ok'))
  <div class="alert alert-success zv-alert">{{ session('ok') }}</div>
@endif

<div class="zv-profile-wrapper">
  <div class="row g-4 flex-lg-nowrap">

    {{-- LEFT --}}
    <div class="col-12 {{ $activeRole === 'coach' ? 'col-xl-8' : 'col-lg-8' }}">
      @include('profile._form', ['user' => $user, 'activeRole' => $activeRole])
    </div>

    {{-- RIGHT --}}
    <div class="col-12 {{ $activeRole === 'coach' ? 'col-xl-4' : 'col-lg-4' }}">
      @include('profile._security', ['activeRole' => $activeRole])
    </div>

  </div>
</div>

@include('profile._avatar_crop_modal')


@push('scripts')

<script src="https://unpkg.com/cropperjs@1.5.13/dist/cropper.min.js"></script>
@include('profile._scripts', ['activeRole' => $activeRole])
@endpush

@endsection
