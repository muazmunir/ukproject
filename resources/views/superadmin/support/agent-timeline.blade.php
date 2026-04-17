@extends('superadmin.layout')

@section('title','Agent Timeline')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/superadmin/agent-timeline.css') }}">
@endpush

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex align-items-start justify-content-between mb-3">
    <div>
      <h4 class="mb-1 fw-bold">Agent Timeline</h4>
      <div class="text-muted" style="font-size:13px;">Day schedule view by status (like call-center timeline).</div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-5">
          <label class="form-label fw-semibold mb-1">Staff</label>
          <select id="user_id" class="form-select">
            <option value="" disabled selected>Select staff...</option>
            @foreach($staff as $u)
              <option value="{{ $u->id }}">
                {{ ucfirst($u->role) }} — {{ $u->full_name ?: $u->email }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="col-6 col-lg-2">
          <label class="form-label fw-semibold mb-1">Date</label>
          <input id="date" type="date" class="form-control" value="{{ now()->toDateString() }}">
        </div>

        <div class="col-3 col-lg-2">
          <label class="form-label fw-semibold mb-1">From</label>
          <select id="start_hour" class="form-select">
            @for($h=0;$h<=23;$h++)
              <option value="{{ $h }}" {{ $h===9 ? 'selected' : '' }}>{{ str_pad($h,2,'0',STR_PAD_LEFT) }}:00</option>
            @endfor
          </select>
        </div>

        <div class="col-3 col-lg-2">
          <label class="form-label fw-semibold mb-1">To</label>
          <select id="end_hour" class="form-select">
            @for($h=1;$h<=24;$h++)
              <option value="{{ $h }}" {{ $h===18 ? 'selected' : '' }}>{{ str_pad($h,2,'0',STR_PAD_LEFT) }}:00</option>
            @endfor
          </select>
        </div>

        <div class="col-12 col-lg-1 d-flex justify-content-end">
          <button id="apply" class="btn btn-dark w-100 fw-semibold" type="button">Apply</button>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
      <div class="fw-bold">Schedule</div>
      <div id="meta" class="text-muted" style="font-size:12px;">—</div>
    </div>
    <div class="card-body">
      <div class="zv-timeline" id="timeline">
        <div class="zv-timecol" id="timecol"></div>
        <div class="zv-track" id="track">
          <div class="zv-track__grid" id="grid"></div>
          <div class="zv-track__blocks" id="blocks"></div>
        </div>
      </div>
      <div class="text-muted mt-2" style="font-size:12px;">
        Tip: this view is clamped to the chosen hours. Open sessions (no ended_at) are shown until “To” time.
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  window.SA_TIMELINE = {
    dataUrl: @json(route('superadmin.support.agent_timeline.data'))
  };
</script>
<script src="{{ asset('assets/js/agent-timeline.js') }}"></script>
@endpush
