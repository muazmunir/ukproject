@extends('superadmin.layout')
@section('title','DM Audit')

@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/staff_dm_superadmin.css') }}">
@endpush

@section('content')
@php $active = $active ?? null; @endphp

@if($active)
 <script>
  const baseUrl = @json(route('superadmin.dm.show', $active));
  const params  = new URLSearchParams(@json(request()->query())).toString();
  window.location.href = params ? `${baseUrl}?${params}` : baseUrl;
</script>

@else
  <div class="p-4">
    <div class="alert alert-warning mb-0 text-capitalize">
      No conversations found for the selected filters.
    </div>
  </div>
@endif
@endsection
