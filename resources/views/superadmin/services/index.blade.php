@extends('superadmin.layout')
@section('title', $pageMode === 'requests' ? 'Service Request List' : 'Service List')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-services.css') }}">
@endpush

@section('content')
<section class="card">
  <div>
      <div class="card__title">
        {{ $pageMode === 'requests' ? 'Service Request List' : 'Service List' }}
      </div>
      <div class="muted text-capitalize text-center">
        @if($pageMode === 'requests')
          Approve or reject newly submitted services from coaches.
        @else
          Manage all services, visibility and approval status.
        @endif
      </div>
    </div>
  <div class="card__head">
    

    <div class="actions">
      {{-- Per page --}}
      <form method="get" class="per-form">
        <input type="hidden" name="q" value="{{ $q }}">
        <input type="hidden" name="status" value="{{ $status }}">
        <label class="per-label">Show
          <select name="per" onchange="this.form.submit()">
            @foreach([10,20,50,100] as $n)
              <option value="{{ $n }}" @selected($per==$n)>{{ $n }}</option>
            @endforeach
          </select>
          Entries
        </label>
      </form>

      {{-- Status filter --}}
      <form method="get" class="status-form">
        <input type="hidden" name="q" value="{{ $q }}">
        <input type="hidden" name="per" value="{{ $per }}">
        <select name="status" onchange="this.form.submit()">
          <option value="all"     @selected($status==='all')>All</option>
          <option value="live"    @selected($status==='live')>Live</option>
          <option value="pending" @selected($status==='pending')>Pending</option>
          <option value="rejected"@selected($status==='rejected')>Rejected</option>
          <option value="inactive"@selected($status==='inactive')>Inactive</option>
          <option value="deleted" @selected($status==='deleted')>Deleted</option>
          <option value="disabled" @selected($status==='disabled')>Disabled</option>


        </select>
      </form>

      {{-- Search --}}
      <form method="get" class="search-form">
        <input type="hidden" name="status" value="{{ $status }}">
        <input type="hidden" name="per" value="{{ $per }}">
        <span class="search-icon" aria-hidden="true"><i class="bi bi-search"></i></span>

        <input type="search" name="q" value="{{ $q }}" placeholder="Search Title, Coach, Email…">
      </form>
    </div>
  </div>

  @php
    $from = $services->firstItem() ?? 0;
    $to   = $services->lastItem() ?? 0;
    $tot  = $services->total();
  @endphp
  <div class="results-meta">
    <span class="muted">Showing <strong>{{ $from }}</strong>–<strong>{{ $to }}</strong> of <strong>{{ $tot }}</strong> entries</span>
  </div>

  <div class="table-wrap">
    <table class="zv-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Thumbnail</th>
          <th>Trainer</th>
          <th>Price (starting from)</th>
          <th>Status</th>
          <th>Approval</th>
          <th style="width:190px">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($services as $s)
          @php
            $coachName = $s->coach
              ? trim($s->coach->first_name.' '.$s->coach->last_name)
              : '—';
            $fromPrice = optional($s->packages)->min('total_price') ?? null;

            if ($s->admin_disabled) {
  $statusPill  = 'pending';
  $statusLabel = 'Disabled by Admin';
} elseif ($s->status === 'archived') {
  $statusPill  = 'muted';
  $statusLabel = 'Archived';
} elseif ($s->status === 'under_review' || (int)$s->is_approved === 0) {
  $statusPill  = 'pending';
  $statusLabel = 'Under Review';
} elseif ($s->is_active) {
  $statusPill  = 'ok';
  $statusLabel = 'Active';
} else {
  $statusPill  = 'muted';
  $statusLabel = 'Inactive';
}

            

            if ($s->is_approved === 1) {
              $appClass = 'ok';
              $appLabel = 'Approved';
            } elseif ($s->is_approved === -1) {
              $appClass = 'danger';
              $appLabel = 'Rejected';
            } else {
              $appClass = 'pending';
              $appLabel = 'Approval Pending';
            }
          @endphp
          <tr>
            <td>
              <div class="cell-title">
                <strong>{{ $s->title }}</strong>
                <div class="muted tiny">#{{ $s->id }}</div>
              </div>
            </td>

            <td>
              @if($s->thumbnail_path)
                <img class="thumb" src="{{ asset('storage/'.$s->thumbnail_path) }}" alt="">
              @else
                <span class="thumb placeholder"></span>
              @endif
            </td>

            <td>{{ $coachName }}</td>

            <td>
              @if($fromPrice !== null)
                ${{ number_format($fromPrice, 0) }}
              @else
                <span class="muted">—</span>
              @endif
            </td>

            <td><span class="pill {{ $statusPill }}">{{ $statusLabel }}</span></td>

            <td><span class="pill {{ $appClass }}">{{ $appLabel }}</span></td>

            <td>
              <div class="row-actions compact">
                {{-- Info --}}
              <a href="{{ route('superadmin.services.show', $s->id) }}" class="btn icon info" title="Details" aria-label="Details">
  <i class="bi bi-info-circle"></i>
</a>


                {{-- Toggle Active --}}
              @if($status !== 'deleted')
  @if($s->admin_disabled)
    <form method="post" action="{{ route('superadmin.services.enable', $s) }}" class="inline">
      @csrf
     <button type="submit" class="btn icon success" title="Enable service (remove admin lock)" aria-label="Enable">
  <i class="bi bi-unlock"></i>
</button>

    </form>
  @else
   <button class="btn icon warning" data-open="#mDisable" data-id="{{ $s->id }}" data-title="{{ $s->title }}" title="Disable" aria-label="Disable">
  <i class="bi bi-lock"></i>
</button>

  @endif
@endif



                {{-- Approve / Reject --}}
               {{-- Approve / Reject (only if decision not taken yet) --}}
@php
  // treat null or 0 as "pending"
  $isPending = is_null($s->is_approved) || (int)$s->is_approved === 0;
@endphp

@if($status !== 'deleted' && $isPending)
 <button class="btn icon success" data-open="#mApprove" data-id="{{ $s->id }}" data-title="{{ $s->title }}" title="Approve" aria-label="Approve">
  <i class="bi bi-check2"></i>
</button>


 <button class="btn icon danger" data-open="#mReject" data-id="{{ $s->id }}" data-title="{{ $s->title }}" title="Reject" aria-label="Reject">
  <i class="bi bi-x-lg"></i>
</button>

@endif
@if($status === 'deleted')
  {{-- Restore --}}
  <button class="btn icon success" data-open="#mRestore" data-id="{{ $s->id }}" data-title="{{ $s->title }}" title="Restore" aria-label="Restore">
  <i class="bi bi-arrow-counterclockwise"></i>
</button>

@else
  {{-- Soft delete --}}
 <button class="btn icon danger" data-open="#mDelete" data-id="{{ $s->id }}" data-title="{{ $s->title }}" title="Delete" aria-label="Delete">
  <i class="bi bi-trash3"></i>
</button>

@endif


              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="muted ta-center text-capitalize">No services found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pager">
    {{ $services->appends(['q'=>$q,'status'=>$status,'per'=>$per])->links() }}
  </div>
</section>

{{-- Approve Modal --}}
<div id="mApprove" class="modal">
  <div class="modal__dialog">
    <form method="post" id="approveForm" class="modal__card">
      @csrf
      <div class="modal__head">
        <div class="title">Approve Service</div>
        <button type="button" class="x" data-close>×</button>
      </div>
      <div class="modal__body text-capitalize">
        <p>Approve <strong id="approveTitle"></strong> and make it available on the marketplace?</p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button type="submit" class="btn success">Approve</button>
      </div>
    </form>
  </div>
</div>

{{-- Reject Modal --}}
<div id="mReject" class="modal">
  <div class="modal__dialog">
    <form method="post" id="rejectForm" class="modal__card">
      @csrf
      <div class="modal__head">
        <div class="title">Reject Service</div>
        <button type="button" class="x" data-close>×</button>
      </div>
      <div class="modal__body text-capitalize">
        <p>Mark <strong id="rejectTitle"></strong> as rejected? It will not appear to clients.</p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button type="submit" class="btn danger">Reject</button>
      </div>
    </form>
  </div>
</div>

{{-- Delete Modal (Soft Delete) --}}
<div id="mDelete" class="modal">
  <div class="modal__dialog">
    <form method="post" id="deleteForm" class="modal__card">
      @csrf @method('DELETE')
      <div class="modal__head">
        <div class="title">Delete Service</div>
        <button type="button" class="x" data-close>×</button>
      </div>
      <div class="modal__body text-capitalize">
        <p>This will move <strong id="deleteTitle"></strong> to Deleted. You can restore it later.</p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button type="submit" class="btn danger">Delete</button>
      </div>
    </form>
  </div>
</div>

{{-- Restore Modal --}}
<div id="mRestore" class="modal">
  <div class="modal__dialog">
    <form method="post" id="restoreForm" class="modal__card">
      @csrf
      <div class="modal__head">
        <div class="title">Restore Service</div>
        <button type="button" class="x" data-close>×</button>
      </div>
      <div class="modal__body">
        <p>Restore <strong id="restoreTitle"></strong>?</p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button type="submit" class="btn success">Restore</button>
      </div>
    </form>
  </div>
</div>


<div id="mDisable" class="modal">
  <div class="modal__dialog">
    <form method="post" id="disableForm" class="modal__card">
      @csrf
      <div class="modal__head">
        <div class="title">Disable Service</div>
        <button type="button" class="x" data-close>×</button>
      </div>

      <div class="modal__body text-capitalize">
        <p>Disable <strong id="disableTitle"></strong>? Coach will not be able to reactivate it.</p>

        <label class="muted tiny d-block mb-1">Reason (optional)</label>
        <textarea name="reason" class="zv-input" rows="3" placeholder="Reason shown to coach..."></textarea>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button type="submit" class="btn danger">Disable</button>
      </div>
    </form>
  </div>
</div>


@endsection

@push('scripts')
<script>
  const baseServiceUrl = "{{ url('superadmin/services') }}";

  function wireServiceModal(openSelector, modalId, formId, titleId, routeSuffix){
  document.querySelectorAll(openSelector).forEach(btn => {
    btn.addEventListener('click', () => {
      const id    = btn.dataset.id;
      const title = btn.dataset.title || '';
      const modal = document.querySelector(modalId);
      modal.classList.add('open');

      const form  = document.querySelector(formId);

      // build action
      form.action = routeSuffix
        ? `${baseServiceUrl}/${id}/${routeSuffix}`
        : `${baseServiceUrl}/${id}`;

      document.querySelector(titleId).textContent = title;
    });
  });
}


  wireServiceModal('[data-open="#mApprove"]', '#mApprove', '#approveForm', '#approveTitle', 'approve');
  wireServiceModal('[data-open="#mReject"]',  '#mReject',  '#rejectForm',  '#rejectTitle',  'reject');
  wireServiceModal('[data-open="#mDelete"]',   '#mDelete',   '#deleteForm',   '#deleteTitle',   '');
wireServiceModal('[data-open="#mRestore"]',  '#mRestore',  '#restoreForm',  '#restoreTitle',  'restore');
wireServiceModal('[data-open="#mDisable"]', '#mDisable', '#disableForm', '#disableTitle', 'disable');



  document.querySelectorAll('[data-close]').forEach(x=>{
    x.addEventListener('click',()=> x.closest('.modal')?.classList.remove('open'));
  });
  document.querySelectorAll('.modal').forEach(m=>{
    m.addEventListener('click',(e)=>{ if(e.target===m) m.classList.remove('open'); });
  });
</script>
@endpush
