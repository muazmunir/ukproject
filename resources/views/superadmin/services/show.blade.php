@extends('superadmin.layout')
@section('title','Service Details')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-service-details.css') }}">

@endpush

@section('content')
<section class="card">
 <div class="card__head">
  <div>
    <div class="card__title">Service Details</div>
    <div class="muted">#{{ $service->id }} · {{ $service->title }}</div>
  </div>

  @php
    $isPending = is_null($service->is_approved) || (int)$service->is_approved === 0;
    $isRejected = (int)$service->is_approved === -1;
    $isApproved = (int)$service->is_approved === 1;
    $isDisabled = (bool)($service->admin_disabled ?? false);
  @endphp

  <div class="sd-actions">
    <a href="{{ route('superadmin.services.index') }}" class="sd-btn sd-btn--ghost" title="Back to Services">
  <i class="bi bi-arrow-left"></i>
  Back
</a>


    @if($isDisabled)
      <form method="post" action="{{ route('superadmin.services.enable', $service) }}">
        @csrf
        <button type="submit" class="sd-btn sd-btn--success">
          <i class="bi bi-unlock"></i>
          Enable
        </button>
      </form>
    @else
      <button type="button" class="sd-btn sd-btn--danger"
              data-open="#mDisable"
              data-id="{{ $service->id }}"
              data-title="{{ $service->title }}">
        <i class="bi bi-lock"></i>
        Disable
      </button>
    @endif

    @if($isPending)
      <form method="post" action="{{ route('superadmin.services.approve', $service) }}">
        @csrf
        <button type="submit" class="sd-btn sd-btn--primary">
          <i class="bi bi-check2"></i>
          Approve
        </button>
      </form>

      <button class="sd-btn sd-btn--outline"
              data-open="#mReject"
              data-id="{{ $service->id }}"
              data-title="{{ $service->title }}">
        <i class="bi bi-x-lg"></i>
        Reject
      </button>
    @elseif($isRejected)
      <span class="sd-pill sd-pill--danger">
        <i class="bi bi-x-circle"></i> Rejected
      </span>
    @elseif($isApproved)
      <span class="sd-pill sd-pill--ok">
        <i class="bi bi-check-circle"></i> Approved
      </span>
    @endif
  </div>
</div>


  {{-- Basic info --}}
  <div class="info-block">
    <h3>Basic Information</h3>
    <div class="info-grid">
      <div class="info-text">
        <p><strong>Title:</strong> {{ $service->title }}</p>
        <p><strong>Total Packages:</strong> {{ $service->packages->count() }}</p>
        <p><strong>About:</strong> {{ $service->description }}</p>
      </div>
      <div class="info-thumb">
        @if($service->thumbnail_path)
          <img src="{{ asset('storage/'.$service->thumbnail_path) }}" alt="" class="hero-img">
        @endif
      </div>
    </div>
  </div>

  {{-- Trainer --}}
  <div class="info-block">
    <h3>Trainer Information</h3>
    @php $coach = $service->coach; @endphp
    <div class="trainer-row">
      <div class="avatar-lg">
      <img  class="avatar-lg" src="{{ $coach->avatar_path ? asset('storage/'.$coach->avatar_path) : asset('images/avatar-placeholder.png') }}"
      alt="">
      </div>
      <div class="trainer-text">
        <p><strong>Name:</strong> {{ $coach ? trim($coach->first_name.' '.$coach->last_name) : '—' }}</p>
        <p><strong>Email:</strong> {{ $coach->email ?? '—' }}</p>
        <p><strong>Phone:</strong> {{ $coach->phone ?? '—' }}</p>
      </div>
    </div>
  </div>

  {{-- Gallery --}}
  @php $images = $service->images ?? []; @endphp
  @if(!empty($images))
    <div class="info-block">
      <h3>Gallery Images</h3>
      <div class="gallery-row">
        @foreach($images as $img)
          <img src="{{ asset('storage/'.$img) }}" class="gallery-img" alt="">
        @endforeach
      </div>
    </div>
  @endif

  {{-- Packages --}}
  <div class="info-block">
    <h3>Service Packages</h3>
    <div class="table-wrap">
      <table class="zv-table small">
        <thead>
          <tr>
            <th>SL</th>
            <th>Package Name</th>
            <th>Unit Price</th>
            <th>Sessions/Hours</th>
            <th>Total Price</th>
            <th>Equipments</th>
            <th>Description</th>
          </tr>
        </thead>
        <tbody>
          @forelse($service->packages as $i => $p)
            <tr>
              <td>{{ $i+1 }}</td>
              <td>{{ $p->name }}</td>
              <td>${{ number_format($p->hourly_rate,2) }}/Session</td>
              <td>{{ $p->total_hours }} Sessions</td>
              <td>${{ number_format($p->total_price,2) }}</td>
              <td>{{ $p->equipments }}</td>
              <td class="truncate">{{ $p->description }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="muted">No packages.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- FAQ --}}
  <div class="info-block">
    <h3>Service FAQ</h3>
    @forelse($service->faqs as $i => $f)
      <div class="faq-row">
        <div class="faq-index">{{ str_pad($i+1, 2, '0', STR_PAD_LEFT) }}.</div>
        <div class="faq-text">
          <div class="faq-q">{{ $f->question }}</div>
          <div class="faq-a">{{ $f->answer }}</div>
        </div>
      </div>
    @empty
      <p class="muted">No FAQs Added.</p>
    @endforelse
  </div>
</section>

{{-- Reject Modal --}}


@endsection

<div id="mReject" class="modal">
  <div class="modal__dialog">
    <form method="post" class="modal__card">
      @csrf
      <div class="modal__head">
        <div class="title">Reject Service</div>
        <button type="button" class="x" data-close>×</button>
      </div>

      <div class="modal__body text-capitalize">
        <p>Reject <strong id="rejectTitle"></strong>? It will not appear to clients.</p>
        <label class="muted tiny d-block mb-1">Reason (optional)</label>
        <textarea name="reason" class="sd-input" rows="3" placeholder="Reason shown to coach..."></textarea>
      </div>

      <div class="modal__foot">
        <button type="button" class="sd-btn sd-btn--ghost" data-close>Cancel</button>
        <button type="submit" class="sd-btn sd-btn--danger">Reject</button>
      </div>
    </form>
  </div>
</div>
{{-- Disable Modal --}}
<div id="mDisable" class="modal">
  <div class="modal__dialog">
    <form method="post" class="modal__card">
      @csrf
      <div class="modal__head">
        <div class="title">Disable Service</div>
        <button type="button" class="x" data-close>×</button>
      </div>

      <div class="modal__body text-capitalize">
        <p>Disable <strong id="disableTitle"></strong>? It will be hidden from clients.</p>

        <label class="muted tiny d-block mb-1 text-capitalize">Reason (optional)</label>
        <textarea name="reason" class="sd-input" rows="3" placeholder="Reason shown to coach..."></textarea>
      </div>

      <div class="modal__foot">
        <button type="button" class="sd-btn sd-btn--ghost" data-close>Cancel</button>
        <button type="submit" class="sd-btn sd-btn--danger">Disable</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
(() => {
  const baseServiceUrl = "{{ url('superadmin/services') }}";

  // 1) Open modal (delegation) — works even if DOM changes
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-open]');
    if (!btn) return;

    e.preventDefault();

    const modalSel = btn.getAttribute('data-open'); // like "#mReject"
    const modal = document.querySelector(modalSel);
    if (!modal) return;

    const id    = btn.dataset.id || '';
    const title = btn.dataset.title || '';

    modal.classList.add('open');

    // Set title (works for reject/disable/delete/restore/approve)
    const titleEl =
      modal.querySelector('#rejectTitle') ||
      modal.querySelector('#disableTitle') ||
      modal.querySelector('#approveTitle') ||
      modal.querySelector('#deleteTitle') ||
      modal.querySelector('#restoreTitle');

    if (titleEl) titleEl.textContent = title;

    // Set form action based on modal id
    const form = modal.querySelector('form');
    if (form && id) {
      const suffixMap = {
        mApprove: 'approve',
        mReject:  'reject',
        mDisable: 'disable',
        mRestore: 'restore',
        mDelete:  '' // delete uses /{id}
      };

      const key = modal.id || '';
      const suffix = (key in suffixMap) ? suffixMap[key] : '';

      form.action = suffix
        ? `${baseServiceUrl}/${id}/${suffix}`
        : `${baseServiceUrl}/${id}`;
    }
  });

  // 2) Close modal (delegation)
  document.addEventListener('click', (e) => {
    if (e.target.matches('[data-close]')) {
      e.preventDefault();
      e.target.closest('.modal')?.classList.remove('open');
      return;
    }

    // click on backdrop
    const m = e.target.classList.contains('modal') ? e.target : null;
    if (m) m.classList.remove('open');
  });
})();
</script>
@endpush




