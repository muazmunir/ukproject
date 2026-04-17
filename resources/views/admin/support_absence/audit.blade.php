@extends('layouts.admin')
@section('title','Absence Audit')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/support_absence.css') }}">
@endpush

@section('content')
<div class="abs-wrap">

  <div class="abs-head">
    <div>
      <h1 class="abs-title">Absence Audit</h1>
      <div class="abs-sub">Full trail of absence actions</div>
    </div>

    <a class="btn btn-outline-dark" href="{{ route('admin.support.absence.review') }}">
      <i class="bi bi-arrow-left"></i> Back
    </a>
  </div>

  <form class="d-flex gap-2 mb-3" method="GET">
    <select class="form-select" name="agent_id" style="max-width:320px;">
      <option value="">All agents</option>
      @foreach($agents as $a)
        <option value="{{ $a->id }}" {{ request('agent_id')==$a->id?'selected':'' }}>
          {{ $a->username }}
        </option>
      @endforeach
    </select>
    <button class="btn btn-dark">Filter</button>
  </form>

  <div class="abs-card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Time</th>
            <th>Agent</th>
            <th>Action</th>
            <th>By</th>
            <th>Meta</th>
          </tr>
        </thead>
        <tbody>
          @forelse($audits as $a)
            <tr>
              <td class="small text-muted">{{ optional($a->created_at)->format('d M Y, H:i') }}</td>
              <td class="fw-semibold">{{ $a->agent->username ?? 'Agent' }}</td>
              <td><span class="badge bg-secondary">{{ $a->action }}</span></td>
              <td>{{ $a->actor->username ?? 'Staff' }}</td>
              <td class="small text-muted" style="max-width:420px;">
                {{ $a->meta ? json_encode($a->meta) : '-' }}
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-muted">No logs.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="p-3">{{ $audits->links() }}</div>
  </div>
</div>
@endsection
