@extends('layouts.admin')
@section('title','My Absence')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/support_absence_my.css') }}">
@endpush

@section('content')
<div class="abs-my container py-3">

  {{-- HEADER --}}
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
      <h3 class="mb-0">My Absence</h3>
      <div class="text-muted small">Submit absence request for manager review</div>
    </div>

    <a href="{{ route('admin.support.absence.my_log') }}" class="btn btn-outline-dark btn-sm">
      <i class="bi bi-clock-history me-1"></i> View My Audit Log
    </a>
  </div>

  {{-- FLASH --}}
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  {{-- ACTIVE ABSENCE --}}
  @if($activeAbsence)
    <div class="alert alert-warning">
      <strong>Absence Active:</strong>
      Your status is locked until
      <strong>{{ \Carbon\Carbon::parse($agent->absence_end_at)->format('d M Y, H:i') }}</strong>.
      You cannot change your support status during this window.
    </div>
  @endif

  {{-- SUBMIT CARD (keep your existing form) --}}
  <div class="card mb-4">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
      <strong>Submit Request</strong>
      <span class="badge text-bg-light border">Reason + at least 1 proof required</span>
    </div>

    <div class="card-body">
      <form method="POST"
            action="{{ route('admin.support.absence.request') }}"
            enctype="multipart/form-data"
            class="row g-3">
        @csrf

        <div class="col-md-6">
          <label class="form-label">Start <span class="text-danger">*</span></label>
          <input type="datetime-local" name="start_at"
                 class="form-control @error('start_at') is-invalid @enderror"
                 required value="{{ old('start_at') }}">
          @error('start_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
          <label class="form-label">End <span class="text-danger">*</span></label>
          <input type="datetime-local" name="end_at"
                 class="form-control @error('end_at') is-invalid @enderror"
                 required value="{{ old('end_at') }}">
          @error('end_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
          <label class="form-label">Reason <span class="text-danger">*</span></label>
          <input type="text" name="reason"
                 class="form-control @error('reason') is-invalid @enderror"
                 maxlength="190" required
                 placeholder="e.g. Medical appointment, emergency, etc."
                 value="{{ old('reason') }}">
          @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
          <label class="form-label">Proof Documents <span class="text-danger">*</span></label>
          <input id="proofsInput"
                 type="file"
                 name="proofs[]"
                 class="form-control @error('proofs') is-invalid @enderror @error('proofs.*') is-invalid @enderror"
                 multiple required
                 accept="image/*,.pdf,.doc,.docx">
          <div class="form-text">
            Upload at least 1 file. Allowed: images, PDF, DOC/DOCX. Max 10MB each.
          </div>
          @error('proofs')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
          @error('proofs.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

          <div id="proofsList" class="abs-files mt-2 d-none"></div>
        </div>

        <div class="col-12">
          <label class="form-label">Comments (optional)</label>
          <textarea name="comments"
                    rows="3"
                    class="form-control @error('comments') is-invalid @enderror"
                    maxlength="2000"
                    placeholder="Extra details for the manager (optional)">{{ old('comments') }}</textarea>
          @error('comments')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12 d-flex align-items-center gap-2">
          <button class="btn btn-dark bg-black">
            <i class="bi bi-send me-1"></i> Submit Request
          </button>
          <div class="text-muted small">
            Manager will approve as Authorized or reject as Unauthorized.
          </div>
        </div>
      </form>
    </div>
  </div>

  {{-- REQUESTS TABLE (minimal) --}}
  <div class="card">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
      <strong>My Requests</strong>
      <div class="muted">Click info to view full details & transparency</div>
    </div>

    <div class="table-wrap">
      <table class="zv-table">
        <thead>
          <tr>
            <th style="width:90px;">ID</th>
            <th>Window</th>
            <th style="width:140px;">State</th>
            <th style="width:160px;">Decision</th>
            <th class="ta-center" style="width:120px;">Details</th>
            <th style="width:150px;">Action</th>
          </tr>
        </thead>

        <tbody>
        @forelse($requests as $req)
          @php
            $state = strtolower((string)$req->state);
            $type  = strtolower((string)($req->type ?? ''));

            $statePill =
              $state === 'pending' ? 'pending' :
              ($state === 'approved' ? 'ok' : ''); // cancelled uses default

            $decisionPill =
              $type === 'authorized' ? 'ok' :
              ($type === 'unauthorized' ? 'danger' : '');

            $files = $req->files ?? collect();

            // manager transparency fields on request:
            // decided_by, decided_at, decision_note
            $decidedAt = $req->decided_at ? \Carbon\Carbon::parse($req->decided_at) : null;
            $startAt = \Carbon\Carbon::parse($req->start_at);
            $endAt = \Carbon\Carbon::parse($req->end_at);
          @endphp

          <tr>
            <td class="fw-bold">#{{ $req->id }}</td>

            <td>
              <div class="namecol">
                <div><span class="muted">Start</span> — <strong>{{ $startAt->format('d M Y, H:i') }}</strong></div>
                <div><span class="muted">End</span> — <strong>{{ $endAt->format('d M Y, H:i') }}</strong></div>
              </div>
            </td>

            <td>
              <span class="pill {{ $statePill }}">
                <i class="bi {{ $state === 'pending' ? 'bi-hourglass-split' : ($state === 'approved' ? 'bi-check2-circle' : 'bi-slash-circle') }}"></i>
                {{ ucfirst($req->state) }}
              </span>
            </td>

            <td class="text-capitalize">
              @if($req->type)
                <span class="pill {{ $decisionPill }}">
                  <i class="bi {{ $type === 'authorized' ? 'bi-shield-check' : 'bi-shield-x' }}"></i>
                  {{ $req->type }}
                </span>
              @else
                <span class="muted">—</span>
              @endif
            </td>

            <td class="ta-center">
              <div class="row-actions compact">
                <button type="button"
                        class="btn icon info"
                        data-open="absModal-{{ $req->id }}"
                        title="View details">
                  <i class="bi bi-info-circle"></i>
                </button>
              </div>
            </td>

            <td>
              @if($req->state === 'pending')
                <form method="POST"
                      action="{{ route('admin.support.absence.cancel', $req) }}"
                      onsubmit="return confirm('Cancel this request?')">
                  @csrf
                  <button class="btn icon danger" title="Cancel request">
                    <i class="bi bi-x-circle"></i>
                  </button>
                </form>
              @else
                <span class="muted">—</span>
              @endif
            </td>
          </tr>

          {{-- DETAILS MODAL --}}
          <div class="modal" id="absModal-{{ $req->id }}" aria-hidden="true">
            <div class="modal__dialog">
              <div class="modal__card">

                <div class="modal__head">
                  <div>
                    <div class="title">Absence Request #{{ $req->id }}</div>
                    <div class="muted">
                      Status:
                      <span class="pill {{ $statePill }}" style="margin-left:6px;">
                        {{ ucfirst($req->state) }}
                      </span>

                      @if($req->type)
                        <span class="pill {{ $decisionPill }}" style="margin-left:6px;">
                          {{ ucfirst($req->type) }}
                        </span>
                      @endif
                    </div>
                  </div>

                  <button class="x" type="button" data-close="absModal-{{ $req->id }}">
                    <i class="bi bi-x"></i>
                  </button>
                </div>

                <div class="modal__body">

                  <div class="abs-kv">
                    <div class="abs-kv__row">
                      <div class="abs-kv__k">Window</div>
                      <div class="abs-kv__v">
                        <div><strong>Start:</strong> {{ $startAt->format('d M Y, H:i') }}</div>
                        <div><strong>End:</strong> {{ $endAt->format('d M Y, H:i') }}</div>
                      </div>
                    </div>

                    <div class="abs-kv__row">
                      <div class="abs-kv__k">Reason</div>
                      <div class="abs-kv__v">
                        <div class="abs-box">{{ $req->reason }}</div>
                      </div>
                    </div>

                    <div class="abs-kv__row">
                      <div class="abs-kv__k">Comments</div>
                      <div class="abs-kv__v">
                        @if($req->comments)
                          <div class="abs-box">{{ $req->comments }}</div>
                        @else
                          <div class="muted">—</div>
                        @endif
                      </div>
                    </div>

                    <div class="abs-kv__row">
                      <div class="abs-kv__k">Proof Documents</div>
                      <div class="abs-kv__v">
                        @if(isset($req->files) && $files->count())
                          <div class="abs-proof-list">
                            @foreach($files as $f)
                              <a class="abs-proof-link"
                                 href="{{ route('admin.support.absence.my_request_file.download', $f) }}">
                                <i class="bi bi-paperclip me-1"></i>
                                <span class="abs-proof-name">{{ $f->original_name ?? 'Attachment' }}</span>
                                <span class="abs-proof-meta">
                                  @if($f->size) • {{ number_format($f->size/1024,0) }} KB @endif
                                </span>
                              </a>
                            @endforeach
                          </div>
                        @else
                          <div class="muted">No files found.</div>
                        @endif
                      </div>
                    </div>

                    <div class="abs-kv__row">
                      <div class="abs-kv__k">Decision Transparency</div>
                      <div class="abs-kv__v">
                        @if($req->decided_by || $req->decided_at)
                          <div class="abs-trans">
                            <div class="abs-trans__row">
                              <div class="abs-trans__k">Decided By</div>
                              <div class="abs-trans__v">
                                {{ optional($req->decider)->username ?? ('User#'.$req->decided_by) }}
                              </div>
                            </div>
                            <div class="abs-trans__row">
                              <div class="abs-trans__k">Decided At</div>
                              <div class="abs-trans__v">
                                {{ $decidedAt ? $decidedAt->format('d M Y, H:i') : '—' }}
                              </div>
                            </div>
                            <div class="abs-trans__row">
                              <div class="abs-trans__k">Note</div>
                              <div class="abs-trans__v">
                                @if($req->decision_note)
                                  <div class="abs-box">{{ $req->decision_note }}</div>
                                @else
                                  <div class="muted">—</div>
                                @endif
                              </div>
                            </div>
                          </div>
                        @else
                          <div class="muted">No decision yet (pending manager review).</div>
                        @endif
                      </div>
                    </div>

                  </div>
                </div>

                <div class="modal__foot">
                  <div class="muted">
                    Submitted: {{ \Carbon\Carbon::parse($req->created_at)->format('d M Y, H:i') }}
                  </div>

                  <div class="d-flex gap-2">
                    @if($req->state === 'pending')
                      <form method="POST"
                            action="{{ route('admin.support.absence.cancel', $req) }}"
                            onsubmit="return confirm('Cancel this request?')">
                        @csrf
                        <button class="btn danger" type="submit">Cancel Request</button>
                      </form>
                    @endif
                    <button class="btn" type="button" data-close="absModal-{{ $req->id }}">Close</button>
                  </div>
                </div>

              </div>
            </div>
          </div>

        @empty
          <tr>
            <td colspan="6" class="ta-center muted" style="padding:18px;">
              No requests yet.
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="pager">
      {{ $requests->links() }}
    </div>
  </div>

</div>

@push('scripts')
<script>
(function(){
  // -----------------------------
  // file picker preview (same as yours)
  // -----------------------------
  const input = document.getElementById('proofsInput');
  const list  = document.getElementById('proofsList');
  if(input && list){
    function humanSize(bytes){
      const units = ['B','KB','MB','GB'];
      let i=0, n=bytes;
      while(n>=1024 && i<units.length-1){ n/=1024; i++; }
      return (i===0 ? n : n.toFixed(1)) + ' ' + units[i];
    }
    let picked = [];
    function syncInputFiles(){
      const dt = new DataTransfer();
      picked.forEach(f => dt.items.add(f));
      input.files = dt.files;
    }
    function escapeHtml(str){
      return String(str)
        .replaceAll('&','&amp;')
        .replaceAll('<','&lt;')
        .replaceAll('>','&gt;')
        .replaceAll('"','&quot;')
        .replaceAll("'","&#039;");
    }
    function render(){
      if(!picked.length){
        list.classList.add('d-none');
        list.innerHTML = '';
        return;
      }
      list.classList.remove('d-none');
      list.innerHTML = picked.map((f, idx) => {
        const icon = (f.type || '').startsWith('image/') ? 'bi-image' :
                     (f.type === 'application/pdf') ? 'bi-file-earmark-pdf' :
                     'bi-file-earmark';
        return `
          <div class="abs-file">
            <div class="abs-file-left">
              <i class="bi ${icon}"></i>
              <div class="abs-file-meta">
                <div class="abs-file-name">${escapeHtml(f.name)}</div>
                <div class="abs-file-sub">${escapeHtml(f.type || 'file')} • ${humanSize(f.size)}</div>
              </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger abs-file-remove" data-idx="${idx}">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        `;
      }).join('');
      list.querySelectorAll('.abs-file-remove').forEach(btn => {
        btn.addEventListener('click', () => {
          const idx = parseInt(btn.getAttribute('data-idx'), 10);
          picked.splice(idx, 1);
          syncInputFiles();
          render();
        });
      });
    }
    input.addEventListener('change', () => {
      const fresh = Array.from(input.files || []);
      picked = picked.concat(fresh);
      const seen = new Set();
      picked = picked.filter(f => {
        const k = `${f.name}|${f.size}|${f.lastModified}`;
        if(seen.has(k)) return false;
        seen.add(k);
        return true;
      });
      syncInputFiles();
      render();
    });
  }

  // -----------------------------
  // modal open/close (your CSS style)
  // -----------------------------
  function openModal(id){
    const el = document.getElementById(id);
    if(!el) return;
    el.classList.add('open');
    el.setAttribute('aria-hidden','false');
    document.body.style.overflow = 'hidden';
  }
  function closeModal(id){
    const el = document.getElementById(id);
    if(!el) return;
    el.classList.remove('open');
    el.setAttribute('aria-hidden','true');
    document.body.style.overflow = '';
  }

  document.addEventListener('click', function(e){
    const openBtn = e.target.closest('[data-open]');
    if(openBtn){
      e.preventDefault();
      openModal(openBtn.getAttribute('data-open'));
      return;
    }
    const closeBtn = e.target.closest('[data-close]');
    if(closeBtn){
      e.preventDefault();
      closeModal(closeBtn.getAttribute('data-close'));
      return;
    }
    const modal = e.target.classList && e.target.classList.contains('modal') ? e.target : null;
    if(modal && modal.id){
      closeModal(modal.id);
    }
  });

  document.addEventListener('keydown', function(e){
    if(e.key !== 'Escape') return;
    const open = document.querySelector('.modal.open');
    if(open && open.id) closeModal(open.id);
  });
})();
</script>
@endpush

@endsection
