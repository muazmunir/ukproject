@extends('superadmin.layout')
@section('title','Leave Requests Review')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/support_leave_review.css') }}">
@endpush

@section('content')
<div class="slr-wrap container py-3">

  <div class="slr-card">
    <div class="slr-head">
      <div>
        <div class="slr-title text-center">Leave Requests Review</div>
        <div class="slr-sub text-center ">
          Review <strong>Manager</strong> And <strong>Agent</strong> Leave Requests •
          Approve (Authorized) Or Reject (Unauthorized)
        </div>
      </div>
    </div>

    @php
      $state = request('state', $state ?? 'pending');
      $kind  = request('kind',  $kind ?? 'all');     // absence|holiday|all
      $role  = request('role',  $role ?? 'all');     // admin|manager|all
      $q     = request('q',     $q ?? '');
      $per   = request('per',   $per ?? 20);

      $tzNow = now()->format('d M Y, H:i');
    @endphp

    {{-- Filters --}}
    <div class="slr-toolbar">
    

      <form class="slr-filters" method="GET">
        <input type="hidden" name="state" value="{{ $state }}">

        <select name="role" class="form-select form-select-sm slr-select">
          <option value="all" {{ $role==='all'?'selected':'' }}>All Roles</option>
          <option value="admin" {{ $role==='admin'?'selected':'' }}>Agents</option>
          <option value="manager" {{ $role==='manager'?'selected':'' }}>Managers</option>
        </select>

        <select name="kind" class="form-select form-select-sm slr-select">
          <option value="all" {{ $kind==='all'?'selected':'' }}>All Types</option>
          <option value="absence" {{ $kind==='absence'?'selected':'' }}>Absence</option>
          <option value="holiday" {{ $kind==='holiday'?'selected':'' }}>Holiday</option>
        </select>

        <select name="per" class="form-select form-select-sm slr-select" title="Rows per page">
          @foreach([10,20,30,50] as $n)
            <option value="{{ $n }}" {{ (int)$per===$n?'selected':'' }}>{{ $n }}/page</option>
          @endforeach
        </select>

        <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm slr-search"
               placeholder="Search #Id, Username, Reason...">
        <button class="btn btn-sm btn-dark bg-black">Search</button>
      </form>

        <div class="slr-tabs">
        <a class="btn btn-sm {{ $state==='pending'?'btn-dark':'btn-outline-dark' }}"
           href="{{ request()->fullUrlWithQuery(['state'=>'pending']) }}">
          Pending <span class="pill">{{ $counts['pending'] ?? 0 }}</span>
        </a>

        <a class="btn btn-sm {{ $state==='approved'?'btn-dark':'btn-outline-dark' }}"
           href="{{ request()->fullUrlWithQuery(['state'=>'approved']) }}">
          Approved <span class="pill">{{ $counts['approved'] ?? 0 }}</span>
        </a>

        <a class="btn btn-sm {{ $state==='cancelled'?'btn-dark':'btn-outline-dark' }}"
           href="{{ request()->fullUrlWithQuery(['state'=>'cancelled']) }}">
          Cancelled <span class="pill">{{ $counts['cancelled'] ?? 0 }}</span>
        </a>

        <a class="btn btn-sm {{ $state==='all'?'btn-dark':'btn-outline-dark' }}"
           href="{{ request()->fullUrlWithQuery(['state'=>'all']) }}">
          All <span class="pill">{{ $counts['all'] ?? 0 }}</span>
        </a>
      </div>
    </div>

    <div class="slr-body">
      @if(session('success')) <div class="alert alert-success mb-3">{{ session('success') }}</div> @endif
      @if(session('error'))   <div class="alert alert-danger mb-3">{{ session('error') }}</div> @endif

     
      

      <div class="table-responsive">
        <table class="table table-sm align-middle slr-table">
          <thead>
            <tr>
              <th style="width:70px;">ID</th>
              <th style="width:120px;">Type</th>
              <th style="width:240px;">Requested By</th>
              <th style="width:270px;">Window</th>
              <th>Reason</th>
              {{-- <th style="width:180px;">Attachments</th> --}}
              <th style="width:160px;" class="">Action</th>

            </tr>
          </thead>

          <tbody>
            @forelse($requests as $req)
              @php
                $reqKind = strtolower((string)($req->kind ?? 'absence'));
                $kindLabel = ucfirst($reqKind);

                $agent = $req->agent;
                $agentRole = strtolower((string)($agent->role ?? ''));
                $roleLabel = $agentRole ? ucfirst($agentRole) : 'User';

                $agentName = $agent->username ?? $agent->name ?? ('User#'.$req->agent_id);
                $agentTz = $agent->timezone ?? 'UTC';

                // show both UTC and agent local time (easy for superadmin)
                $startUtc = \Carbon\Carbon::parse($req->start_at)->utc();
                $endUtc   = \Carbon\Carbon::parse($req->end_at)->utc();
                $startLocal = $startUtc->copy()->timezone($agentTz);
                $endLocal   = $endUtc->copy()->timezone($agentTz);

                $files = $req->files ?? collect();
              @endphp

              <tr>
                <td class="fw-bold">#{{ $req->id }}</td>

                <td>
                  <span class="badge text-bg-light border">{{ $kindLabel }}</span>
                  <div class="small mt-1">
                    <span class="badge {{ $agentRole==='manager' ? 'bg-primary' : 'bg-secondary' }}">
                      {{ $roleLabel }}
                    </span>
                  </div>
                </td>

                <td>
                  <div class="slr-user">
                    <div class="slr-user-name">{{ $agentName }}</div>
                    <div class="slr-user-meta">
                      <span class="text-muted">TZ:</span> <span class="mono">{{ $agentTz }}</span>
                      <span class="dot">•</span>
                      <span class="text-muted">ID:</span> <span class="mono">{{ $req->agent_id }}</span>
                    </div>
                  </div>
                </td>

                <td>
                  <div class="slr-window">
                    <div class="slr-window-row">
                      <span class="label">Start</span>
                      <span class="val">
                        {{ $startLocal->format('d M Y, H:i') }}
                        <span class="muted">({{ $agentTz }})</span>
                      </span>
                    </div>
                    <div class="slr-window-row">
                      <span class="label">End</span>
                      <span class="val">
                        {{ $endLocal->format('d M Y, H:i') }}
                        <span class="muted">({{ $agentTz }})</span>
                      </span>
                    </div>
                    {{-- <div class="slr-window-row slr-window-utc">
                      <span class="label">UTC</span>
                      <span class="val mono">
                        {{ $startUtc->format('Y-m-d H:i') }} → {{ $endUtc->format('Y-m-d H:i') }}
                      </span>
                    </div> --}}
                  </div>
                </td>

                <td style="max-width: 380px;">
                  <div class="fw-semibold">{{ $req->reason }}</div>
                  {{-- @if($req->comments)
                    <div class="text-muted small mt-1">{{ $req->comments }}</div>
                  @endif --}}
                </td>

                {{-- <td>
                  @if($files->count())
                    <div class="d-flex flex-column gap-1">
                      @foreach($files as $f)
                        <a class="small slr-file"
                           href="{{ route('superadmin.support.leave.review_request_file.download', $f->id) }}">
                          <i class="bi bi-paperclip"></i>
                          <span class="text-truncate" style="max-width:160px; display:inline-block; vertical-align:bottom;">
                            {{ $f->original_name ?? 'Attachment' }}
                          </span>
                        </a>
                      @endforeach
                    </div>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td> --}}

              <td class="">
  <a class="btn btn-sm btn-dark bg-black"
     href="{{ route('superadmin.support.leave.show', $req->id) }}">
    View Details <i class="bi bi-arrow-right-short"></i>
  </a>

  @if($req->state !== 'pending')
    <div class="text-muted small mt-1 ">
      <span class="text-capitalize fw-semibold">{{ $req->type ?? '—' }}</span>
      @if($req->decision_note) • {{ $req->decision_note }} @endif
    </div>
  @endif
</td>

              </tr>

            @empty
              <tr>
                <td colspan="6" class="text-center text-muted py-4">No requests found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-end">
        {{ $requests->links() }}
      </div>
    </div>
  </div>

</div>
@endsection
