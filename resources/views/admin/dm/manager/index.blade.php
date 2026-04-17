@extends('layouts.admin')
@section('title','My Agents')

@section('content')
@php $active = $active ?? null; @endphp

@if($active)
  <script>window.location.href = @json(route('admin.dm.manager.show', $active));</script>
@else
  <div class="p-4">
    <div class="alert alert-info mb-0 text-capitalize">
      No agents assigned to you yet.
    </div>
  </div>
@endif
@endsection
