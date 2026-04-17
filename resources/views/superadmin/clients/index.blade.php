@extends('superadmin.layout')
@section('title','Clients List')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-clients.css') }}">
@endpush

@section('content')
<section class="card">
  <div>
      <div class="card__title">Clients List</div>

      <div class="muted text-capitalize text-center">Manage all registered clients</div>
    </div>
  <div class="card__head">
    
    <form method="get" class="segmented" id="segmentedForm">
  <input type="hidden" name="q" value="{{ $q }}">
  <input type="hidden" name="per" value="{{ $per }}">

  <input type="radio" id="seg-all" name="status" value="all" @checked(($status ?? 'all')==='all')>
  <label for="seg-all">All <span class="badge">{{ $counts['all'] ?? 0 }}</span></label>

  <input type="radio" id="seg-deleted" name="status" value="deleted" @checked(($status ?? 'all')==='deleted')>
  <label for="seg-deleted">Deleted <span class="badge danger">{{ $counts['deleted'] ?? 0 }}</span></label>
</form>

  </div>

  {{-- tools --}}
  <div class="tools">
    <form method="get" class="per-form">
      <input type="hidden" name="status" value="{{ $status ?? 'all' }}">

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
      <input type="hidden" name="status" value="{{ $status ?? 'all' }}">
      <input type="hidden" name="per" value="{{ $per }}">
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
          <th style="width:120px">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($customers as $u)
          <tr>
            <td>
              <div class="namecol">
                <strong>{{ trim($u->first_name.' '.$u->last_name) ?: '—' }}</strong>
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
        class="avatar-img"
        alt="{{ $name }}"
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
              <div class="row-actions compact">
              <a href="{{ route('superadmin.clients.show', $u->id) }}"
   class="btn icon info" title="View">
  <i class="bi bi-eye"></i>
</a>

<a href="{{ route('superadmin.clients.stats', $u->id) }}"
   class="btn icon primary"
   title="View Stats"
   aria-label="View stats">
  <i class="bi bi-bar-chart-line"></i>
</a>

@if(($status ?? 'all') === 'deleted')
  <button class="btn icon success" data-open="#mRestore"
          data-id="{{ $u->id }}" data-name="{{ $u->first_name }} {{ $u->last_name }}"
          title="Restore" type="button">
    <i class="bi bi-arrow-counterclockwise"></i>
  </button>
@else
  <button class="btn icon danger" data-open="#mDelete"
          data-id="{{ $u->id }}" data-name="{{ $u->first_name }} {{ $u->last_name }}"
          title="Delete" type="button">
    <i class="bi bi-trash3"></i>
  </button>
@endif


              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="muted">No Clients found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pager">{{ $customers->appends(['q'=>$q,'per'=>$per,'status'=>$status ?? 'all'])->links() }}</div>

</section>

{{-- Delete modal --}}
<div id="mDelete" class="modal">
  <div class="modal__dialog">
    <form method="post" id="deleteForm" class="modal__card">
      @csrf @method('DELETE')
      <div class="modal__head">
        <div class="title">Delete Client</div>
      <button type="button" class="x" data-close aria-label="Close">
  <i class="bi bi-x-lg"></i>
</button>

      </div>

      <div class="modal__body">
        <p>Delete <strong id="deleteName"></strong>?</p>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button class="btn danger" type="submit">Delete</button>
      </div>
    </form>
  </div>
</div>
{{-- Restore modal --}}
<div id="mRestore" class="modal">
  <div class="modal__dialog">
    <form method="post" id="restoreForm" class="modal__card">
      @csrf
      <div class="modal__head">
        <div class="title">Restore Client</div>
        <button type="button" class="x" data-close>×</button>
      </div>

      <div class="modal__body">
        <p>Restore <strong id="restoreName"></strong>?</p>
      </div>

      <div class="modal__foot">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button class="btn success" type="submit">Restore</button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<script>
  // auto submit segmented
  document.getElementById('segmentedForm')?.addEventListener('change', e=>{
    if(e.target && e.target.name==='status') e.currentTarget.submit();
  });

  const base = "{{ url('superadmin/clients') }}";

  // delete
  document.querySelectorAll('[data-open="#mDelete"]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const id = btn.dataset.id;
      const name = btn.dataset.name || '';
      const modal = document.querySelector('#mDelete');
      modal.classList.add('open');
      document.querySelector('#deleteName').textContent = name;
      document.querySelector('#deleteForm').action = `${base}/${id}`;
    });
  });

  // restore
  document.querySelectorAll('[data-open="#mRestore"]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const id = btn.dataset.id;
      const name = btn.dataset.name || '';
      const modal = document.querySelector('#mRestore');
      modal.classList.add('open');
      document.querySelector('#restoreName').textContent = name;
      document.querySelector('#restoreForm').action = `${base}/${id}/restore`;
    });
  });

  // close
  document.querySelectorAll('[data-close]').forEach(x=>{
    x.addEventListener('click',()=> x.closest('.modal')?.classList.remove('open'));
  });
  document.querySelectorAll('.modal').forEach(m=>{
    m.addEventListener('click',(e)=>{ if(e.target===m) m.classList.remove('open'); });
  });
</script>

@endpush
