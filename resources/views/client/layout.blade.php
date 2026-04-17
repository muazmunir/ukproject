@extends('layouts.role-dashboard')
@section('title', __('Client Dashboard'))

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/client.css') }}">
@endpush

@section('role-content')
  {{-- page content here --}}
@endsection
