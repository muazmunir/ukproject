@extends('superadmin.layout')
@section('title','Leave Request Details')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/support_leave_show.css') }}">
@endpush

@section('content')
@php
  $agentTz = $agent->timezone ?? 'UTC';
  $kind = strtolower((string)($req->kind ?? 'absence'));

  $startUtc = \Carbon\Carbon::parse($req->start_at)->utc();
  $endUtc   = \Carbon\Carbon::parse($req->end_at)->utc();
  $startLocal = $startUtc->copy()->timezone($agentTz);
  $endLocal   = $endUtc->copy()->timezone($agentTz);

  $roleLabel = ucfirst(strtolower((string)($agent->role_label ?? 'user')));
@endphp

<div class="sls-wrap container py-3">

   <div class="sls-actions ms-auto text-end my-2">
      <a href="{{ route('superadmin.support.leave.review') }}" class="btn btn-sm btn-outline-dark">
        <i class="bi bi-arrow-left"></i> Back To Review
      </a>
    </div>
  <div class="sls-top align-items-center justify-center">
    <div>
      <div class="sls-title text-center">Leave Request #{{ $req->id }}</div>
      <div class="sls-sub text-capitalize text-center">Full request details • Review attachments • Make decision</div>
    </div>

   
  </div>

  @if(session('success')) <div class="alert alert-success mt-3">{{ session('success') }}</div> @endif
  @if(session('error'))   <div class="alert alert-danger mt-3">{{ session('error') }}</div> @endif

  <div class="sls-grid mt-3">
    {{-- LEFT: Request info --}}
    <div class="sls-card">
      <div class="sls-card-head">
        <div class="sls-card-title">Request Information</div>
        <div class="sls-badges">
          <span class="badge text-bg-light border">{{ ucfirst($kind) }}</span>
          <span class="badge {{ strtolower($agent->role)==='manager' ? 'bg-primary' : 'bg-secondary' }}">{{ $roleLabel }}</span>

          <span class="badge {{ $req->state==='pending' ? 'bg-warning text-dark' : 'bg-success' }}">
            {{ ucfirst($req->state) }}
          </span>
        </div>
      </div>

      <div class="sls-row">
        <div class="k">Requested By</div>
        <div class="v">
          <div class="fw-semibold">{{ $agent->username ?? $agent->name ?? ('User#'.$agent->id) }}</div>
          <div class="text-muted small">User ID: {{ $agent->id }} • TZ: <span class="mono">{{ $agentTz }}</span></div>
        </div>
      </div>

      <div class="sls-row">
        <div class="k">Window (Local)</div>
        <div class="v">
          <div><span class="mono">{{ $startLocal->format('d M Y, H:i') }}</span> → <span class="mono">{{ $endLocal->format('d M Y, H:i') }}</span></div>
          <div class="text-muted small">{{ $agentTz }}</div>
        </div>
      </div>

      <div class="sls-row">
        <div class="k">Window (UTC)</div>
        <div class="v mono">{{ $startUtc->format('Y-m-d H:i') }} → {{ $endUtc->format('Y-m-d H:i') }}</div>
      </div>

      <div class="sls-row">
        <div class="k">Reason</div>
        <div class="v fw-semibold">{{ $req->reason }}</div>
      </div>

      @if($req->comments)
      <div class="sls-row">
        <div class="k">Comments</div>
        <div class="v text-muted">{{ $req->comments }}</div>
      </div>
      @endif

      <div class="sls-row">
        <div class="k">Submitted</div>
        <div class="v text-muted">
          {{ optional($req->created_at)->format('d M Y, H:i') ?? '—' }}
        </div>
      </div>
    </div>

    {{-- RIGHT: Attachments + decision --}}
    <div class="sls-card">
      <div class="sls-card-head">
        <div class="sls-card-title">Attachments</div>
        <div class="text-muted small text-capitalize">All files submitted with this request.</div>
      </div>

      @if(($req->files ?? collect())->count())
        <div class="sls-files">
          @foreach($req->files as $f)
            <a class="sls-file"
               href="{{ route('superadmin.support.leave.review_request_file.download', $f->id) }}">
              <i class="bi bi-paperclip"></i>
              <span class="name">{{ $f->original_name ?? 'Attachment' }}</span>
              <span class="meta">{{ number_format(($f->size ?? 0)/1024, 0) }} KB</span>
            </a>
          @endforeach
        </div>
      @else
        <div class="text-muted">No Attachments.</div>
      @endif

      <hr class="my-3">

      {{-- <div class="sls-card-head">
        <div class="sls-card-title">Decision</div>
        <div class="text-muted small text-capitalize">
          Approve → Authorized window • Reject → Unauthorized (Absence) / No schedule (Holiday)
        </div>
      </div> --}}

      @if($req->state === 'pending')
        <form method="POST" action="{{ route('superadmin.support.leave.decide', $req->id) }}" class="sls-decision">
          @csrf

          <label class="small text-muted mb-1">Decision Note (Optional)</label>
          <input type="text" name="note" maxlength="255" class="form-control form-control-sm"
                 placeholder="Explain Briefly Why You Approved/Rejected">

          <div class="d-flex gap-2 mt-2">
            <button class="btn btn-sm btn-success bg-success" name="decision" value="approve">
              <i class="bi bi-check2"></i> Approve
            </button>

            <button class="btn btn-sm btn-danger bg-danger" name="decision" value="reject">
              <i class="bi bi-x-lg"></i> Reject
            </button>
          </div>
        </form>
      @else
        <div class="sls-result">
          <div class="fw-semibold text-capitalize">{{ $req->type ?? '—' }}</div>
          @if($req->decision_note)
            <div class="text-muted small mt-1">{{ $req->decision_note }}</div>
          @endif
          <div class="text-muted small mt-1">
            Decided at: {{ optional($req->decided_at)->format('d M Y, H:i') ?? '—' }}
          </div>
          <div class="text-muted small mt-1">
            Decided by: {{ $req->decider->username ?? $req->decider->name ?? ('User#'.$req->decided_by) }}
          </div>
        </div>
      @endif
    </div>
  </div>

  {{-- Optional audit trail --}}
  @if(isset($audits) && $audits->count())
    <div class="sls-card mt-3">
      <div class="sls-card-head">
        <div class="sls-card-title">Audit Trail</div>
        <div class="text-muted small text-capitalize">Last 20 actions related to this request.</div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>When</th>
              <th>Action</th>
              <th>Actor</th>
              <th>Meta</th>
            </tr>
          </thead>
          <tbody>
            @foreach($audits as $a)
              <tr>
                <td class="text-muted small">{{ optional($a->created_at)->format('d M Y, H:i') }}</td>
                <td class="fw-semibold">{{ $a->action }}</td>
                <td>{{ $a->actor->username ?? $a->actor->name ?? ('User#'.$a->actor_id) }}</td>
                <td class="text-muted small" style="max-width:520px;">
                  {{ is_array($a->meta) ? json_encode($a->meta) : (string)$a->meta }}
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

</div>
@endsection
