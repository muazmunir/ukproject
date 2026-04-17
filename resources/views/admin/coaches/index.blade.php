
@extends('layouts.admin')
@section('title','Coach List')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-coaches.css') }}">
@endpush

@section('content')
<section class="card">
   <div>
      <div class="card__title">Coaches</div>
      <div class="muted text-capitalize text-center">Review, approve, reject, or remove coaches. Use the quick toggles below.</div>
    </div>
  <div class="card__head">
   

    {{-- Segmented filter --}}
    <form method="get" class="segmented" id="segmentedForm">
      <input type="hidden" name="q" value="{{ $q }}">
      <input type="radio" id="seg-all"      name="status" value="all"     @checked($status==='all')>
      <label for="seg-all">All <span class="badge">{{ $counts['all'] ?? 0 }}</span></label>

      <input type="radio" id="seg-active"   name="status" value="active"  @checked($status==='active')>
      <label for="seg-active">Active <span class="badge ok">{{ $counts['active'] ?? 0 }}</span></label>

      <input type="radio" id="seg-pending"  name="status" value="pending" @checked($status==='pending')>
      <label for="seg-pending">Pending <span class="badge warn">{{ $counts['pending'] ?? 0 }}</span></label>

      <input type="radio" id="seg-rejected" name="status" value="rejected" @checked($status==='rejected')>
      <label for="seg-rejected">Rejected <span class="badge danger">{{ $counts['rejected'] ?? 0 }}</span></label>

      <input type="radio" id="seg-deleted" name="status" value="deleted" @checked($status==='deleted')>
<label for="seg-deleted">Deleted <span class="badge danger">{{ $counts['deleted'] ?? 0 }}</span></label>

      {{-- Deactivation requests jump (opens a separate route) --}}
      {{-- <a class="chip link" href="{{ route('admin.coaches.deactivations') }}" title="Pending deactivation requests">
        Deactivation Requests <span class="badge {{ ($deactCount ?? 0) ? 'warn' : '' }}">{{ $deactCount ?? 0 }}</span>
      </a> --}}
    </form>
  </div>

  {{-- Top tools: search + per-page --}}
  <div class="tools">
    <form method="get" class="per-form">
      <input type="hidden" name="status" value="{{ $status }}">
      <input type="hidden" name="q" value="{{ $q }}">
      <label class="per-label">Show
        <select name="per" onchange="this.form.submit()">
          @foreach([10,20,50,100] as $n)
            <option value="{{ $n }}" @selected($per==$n)>{{ $n }}</option>
          @endforeach
        </select>
        Entries
      </label>
    </form>

    <form method="get" class="search-form" role="search">
      <input type="hidden" name="status" value="{{ $status }}">
      <input type="hidden" name="per" value="{{ $per }}">
      <span class="search-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M21 21l-4.2-4.2M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </span>
      <input type="search" name="q" value="{{ $q }}" placeholder="Search Name, Email, Phone…">
    </form>
  </div>

  <div class="table-wrap">
    <table class="zv-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Image</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Status</th>
          <th style="width:180px">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($coaches as $u)
          <tr>
            <td>
              <div class="namecol">
                <strong>{{ trim(($u->first_name.' '.$u->last_name)) ?: '—' }}</strong>
                <div class="muted uname">{{ $u->username ?: '—' }}</div>
              </div>
            </td>
           <td>
  @php
    $name = trim(($u->first_name.' '.$u->last_name)) ?: ($u->username ?: 'U');
    $letter = strtoupper(mb_substr($name, 0, 1));
    $avatar = $u->avatar_path ? asset('storage/'.$u->avatar_path) : null;
  @endphp

  <div class="avatar-wrap">
    @if($avatar)
      <img
        src="{{ $avatar }}"
        alt="{{ $name }}"
        class="avatar-img"
        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
      >
    @endif

    <div class="avatar-fallback" style="{{ $avatar ? 'display:none;' : '' }}">
      {{ $letter }}
    </div>
  </div>
</td>

            <td><a href="mailto:{{ $u->email }}">{{ $u->email }}</a></td>
            <td><a href="tel:{{ $u->phone }}">{{ $u->phone ?: '—' }}</a></td>
            <td>
  @php
  $kyc = (string) optional($u->coachProfile)->application_status;

  $pill = $kyc === 'approved'
      ? 'ok'
      : ($kyc === 'submitted'
          ? 'pending'
          : ($kyc === 'rejected' ? 'danger' : 'pending'));

  $lbl = $kyc === 'approved'
      ? 'Approved'
      : ($kyc === 'submitted'
          ? 'Pending'
          : ($kyc === 'rejected' ? 'Rejected' : 'Not Submitted'));
@endphp
<span class="pill {{ $pill }}">{{ $lbl }}</span>
</td>

      <td>
  <div class="row-actions compact">
    {{-- Info / details --}}
    <a href="{{ route('admin.coaches.show', $u->id) }}"
   class="btn icon info"
   title="View Details"
   aria-label="View details">
  <i class="bi bi-person-vcard"></i>
</a>

<a href="{{ route('admin.coaches.stats', $u->id) }}"
   class="btn icon primary"
   title="View Stats"
   aria-label="View stats">
  <i class="bi bi-bar-chart-line"></i>
</a>

    @php
      $statusVal = (int) $u->is_approved;
    @endphp

   @if($status === 'deleted')
  {{-- Restore --}}
  <button class="btn icon success"
          data-open="#mRestore"
          data-id="{{ $u->id }}"
          data-name="{{ $u->first_name }} {{ $u->last_name }}"
          title="Restore"
          aria-label="Restore">
    <i class="bi bi-arrow-counterclockwise"></i>
  </button>
@else

  @if($kyc === 'pending' || $kyc === '')
    {{-- Approve --}}
    <button class="btn icon success"
            data-open="#mApprove"
            data-id="{{ $u->id }}"
            data-name="{{ $u->first_name }} {{ $u->last_name }}"
            title="Approve"
            aria-label="Approve">
      <i class="bi bi-check2"></i>
    </button>

    {{-- Reject --}}
    <button class="btn icon warning"
            data-open="#mReject"
            data-id="{{ $u->id }}"
            data-name="{{ $u->first_name }} {{ $u->last_name }}"
            title="Reject"
            aria-label="Reject">
      <i class="bi bi-x-lg"></i>
    </button>
  @endif

  {{-- Delete --}}
  <button class="btn icon danger"
          data-open="#mDelete"
          data-id="{{ $u->id }}"
          data-name="{{ $u->first_name }} {{ $u->last_name }}"
          title="Delete"
          aria-label="Delete">
    <i class="bi bi-trash3"></i>
  </button>
@endif

  </div>
</td>



          </tr>
        @empty
          <tr><td colspan="6" class="muted">No coaches found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pager">
    {{ $coaches->appends(['q'=>$q,'status'=>$status,'per'=>$per])->links() }}
  </div>
</section>

{{-- Approve --}}
<div id="mApprove" class="modal">
  <div class="modal__dialog">
    <form method="post" id="approveForm" class="modal__card" action="">
      @csrf
      <div class="modal__head">
        <div class="title">Approve Coach</div>
       <button type="button" class="x" data-close aria-label="Close">
  <i class="bi bi-x-lg"></i>
</button>

      </div>
      <div class="modal__body text-capitalize">
        <p>Approve <strong id="approveName"></strong> as a coach?</p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button class="btn success" type="submit">Approve</button>
      </div>
    </form>
  </div>
</div>

{{-- Reject --}}
<div id="mReject" class="modal">
  <div class="modal__dialog">
    <form method="post" id="rejectForm" class="modal__card" action="">
      @csrf
      <div class="modal__head">
        <div class="title">Reject Coach</div>
        <button type="button" class="x" data-close aria-label="Close">
  <i class="bi bi-x-lg"></i>
</button>

      </div>
      <div class="modal__body text-capitalize">
        <p>Mark <strong id="rejectName"></strong> as rejected?</p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button class="btn warning" type="submit">Reject</button>
      </div>
    </form>
  </div>
</div>

{{-- Restore --}}
<div id="mRestore" class="modal">
  <div class="modal__dialog">
    <form method="post" id="restoreForm" class="modal__card" action="">
      @csrf
      <div class="modal__head">
        <div class="title">Restore Coach</div>
        <button type="button" class="x" data-close aria-label="Close">
  <i class="bi bi-x-lg"></i>
</button>

      </div>
      <div class="modal__body text-capitalize">
        <p>Restore <strong id="restoreName"></strong> so they can be managed again?</p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button class="btn success" type="submit">Restore</button>
      </div>
    </form>
  </div>
</div>

{{-- Delete --}}
<div id="mDelete" class="modal">
  <div class="modal__dialog">
    <form method="post" id="deleteForm" class="modal__card" action="">
      @csrf @method('DELETE')
      <div class="modal__head">
        <div class="title">Delete Coach</div>
        <button type="button" class="x" data-close aria-label="Close">
  <i class="bi bi-x-lg"></i>
</button>

      </div>
      <div class="modal__body text-capitalize">
        <p>This will permanently remove <strong id="deleteName"></strong>. Continue?</p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button class="btn danger" type="submit">Delete</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
  // Submit segmented toggle automatically
  document.getElementById('segmentedForm')?.addEventListener('change', e=>{
    if(e.target && e.target.name==='status') e.currentTarget.submit();
  });

  const base = "{{ url('admin/coaches') }}";

  function wire(openerSel, modalId, formSel, nameSel, endpoint){
    document.querySelectorAll(openerSel).forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const id   = btn.dataset.id, name = btn.dataset.name || '';
        const modal= document.querySelector(modalId);
        modal.classList.add('open');
        document.querySelector(nameSel).textContent = name;
        const form = document.querySelector(formSel);
        form.action = `${base}/${id}${endpoint?('/'+endpoint):''}`;
      });
    });
  }
  wire('[data-open="#mApprove"]', '#mApprove', '#approveForm', '#approveName', 'approve');
  wire('[data-open="#mReject"]',  '#mReject',  '#rejectForm',  '#rejectName',  'reject');
  wire('[data-open="#mDelete"]',  '#mDelete',  '#deleteForm',  '#deleteName',  '');
  wire('[data-open="#mRestore"]', '#mRestore', '#restoreForm', '#restoreName', 'restore');

  document.querySelectorAll('[data-close]').forEach(x=> x.addEventListener('click',()=> x.closest('.modal')?.classList.remove('open')));
  document.querySelectorAll('.modal').forEach(m=> m.addEventListener('click',(e)=>{ if(e.target===m) m.classList.remove('open'); }));
</script>
@endpush
