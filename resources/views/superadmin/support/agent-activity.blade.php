@extends('superadmin.layout')

@section('title','Agent Activity')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/superadmin/agent-activity.css') }}">
@endpush

@section('content')
<div class="container-fluid py-3 sa-act">
  <div class="d-flex align-items-start justify-content-between mb-3">
    <div>
      <h4 class="mb-1 fw-bold">Agent Activity</h4>
      <div class="text-muted" style="font-size:13px;">Timeline for daily view, analytics for weekly/monthly/yearly.</div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3 sa-card">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-4">
          <label class="form-label fw-semibold mb-1">Staff</label>
          <select id="user_id" class="form-select sa-input">
            <option value="">All Admins/Managers</option>
            @foreach($staff as $u)
              <option value="{{ $u->id }}">{{ ucfirst($u->role) }} — {{ $u->full_name ?: $u->email }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-6 col-lg-2">
          <label class="form-label fw-semibold mb-1">View</label>
          <select id="view" class="form-select sa-input">
            <option value="daily" selected>Daily (Timeline)</option>
            <option value="weekly">Weekly</option>
            <option value="monthly">Monthly</option>
            <option value="yearly">Yearly</option>
          </select>
        </div>

        <div class="col-6 col-lg-2">
          <label class="form-label fw-semibold mb-1">Preset</label>
          <select id="preset" class="form-select sa-input">
            <option value="today">Today</option>
            <option value="7d" selected>Last 7 days</option>
            <option value="30d">Last 30 days</option>
            <option value="this_month">This month</option>
            <option value="last_month">Last month</option>
            <option value="this_year">This year</option>
            <option value="custom">Custom</option>
          </select>
        </div>

        <div class="col-6 col-lg-2">
          <label class="form-label fw-semibold mb-1">Start</label>
          <input id="start" type="date" class="form-control sa-input">
        </div>

        <div class="col-6 col-lg-2">
          <label class="form-label fw-semibold mb-1">End</label>
          <input id="end" type="date" class="form-control sa-input">
        </div>

        <div class="col-12 d-flex justify-content-end gap-2 mt-2">
          <button id="apply" class="btn btn-dark px-4 fw-semibold" type="button">Apply</button>
          <button id="reset" class="btn btn-outline-dark px-4 fw-semibold" type="button">Reset</button>
        </div>
      </div>

      <div class="sa-hint mt-2">
        <span class="pill">Daily</span> shows a schedule timeline. <span class="pill">Weekly/Monthly/Yearly</span> shows totals by bucket.
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm sa-card">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
      <div class="fw-bold" id="panelTitle">Timeline</div>
      <div class="text-muted" style="font-size:12px;" id="meta">—</div>
    </div>
    <div class="card-body">
      <div id="panelTimeline" class="sa-panel"></div>
      <div id="panelChart" class="sa-panel d-none">
        <canvas id="hoursChart" height="110"></canvas>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  window.SA_AGENT_ACTIVITY = {
    dataUrl: @json(route('superadmin.support.agent_activity.data')),
  };
</script>
<script src="{{ asset('assets/js/agent-activity.js') }}"></script>
@endpush
