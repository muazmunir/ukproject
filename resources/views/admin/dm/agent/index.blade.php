@extends('layouts.admin')
@section('title','Message Manager')

@php
  $role = strtolower(auth()->user()->role ?? '');
@endphp

@section('content')
@php
  $active = $active ?? null;
@endphp

@if($active)
  <script>window.location.href = @json(route('admin.dm.agent.show', $active));</script>
@else
  <div class="p-4">
    <div class="alert alert-warning mb-0 text-capitalize">
      You are not assigned to any manager yet. Please contact Superadmin.
    </div>
  </div>
@endif
@endsection
