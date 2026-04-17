@extends('layouts.role-dashboard')

@section('title', __('Your Services'))

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/coach-services.css') }}">
@endpush

@section('role-content')
<div class="zv-services-page p-3">

  {{-- Header --}}
  <div class="zv-services-header">
    <div>
      <h5 class="zv-services-title mb-1">{{ __('Your Services') }}</h5>
      <p class="zv-services-subtitle mb-0 text-capitalize">
        {{ __('Manage the offerings clients can book from your profile.') }}
      </p>
    </div>
    <a href="{{ route('coach.services.create') }}" class="btn-3d btn-dark-elev bg-black text-white zv-services-add">
      <i class="bi bi-plus-lg me-1"></i>{{ __('Add Service') }}
    </a>
  </div>

 

  @if($services->isEmpty())
    {{-- Empty state --}}
    <div class="zv-services-empty">
      <div class="zv-services-empty-inner">
        <div class="zv-empty-icon-wrap">
          <i class="bi bi-bag-plus"></i>
        </div>
        <h6 class="mb-1">{{ __('No services yet') }}</h6>
        <p class="text-muted mb-3 text-capitalize">
          {{ __('Create your first service to start receiving bookings from clients.') }}
        </p>
        <a href="{{ route('coach.services.create') }}" class="btn-3d btn-dark-elev">
          {{ __('Create Service') }}
        </a>
      </div>
    </div>
  @else
    {{-- Cards grid --}}
    <div class="zv-services-grid">
      @foreach($services as $s)
        <article class="zv-service-card">
          <div class="zv-service-media"
               style="background-image:url('{{ $s->thumbnail_path ? asset('storage/'.$s->thumbnail_path) : asset('assets/placeholder-16x9.png') }}');">
       @php
       $isAdminDisabled = !is_null($s->admin_disabled_at);
  $isArchived      = ($s->status === 'archived');
  $isRejected      = ($s->status === 'rejected') || ((int)$s->is_approved === -1);
  $isUnderReview   = (! $isRejected) && ((int)$s->is_approved !== 1 || $s->status === 'under_review');
  $isActive        = (! $isAdminDisabled) && $s->is_active && ((int)$s->is_approved === 1) && $s->status === 'active';
  $isArchived    = ($s->status === 'archived');
  $isRejected    = ($s->status === 'rejected') || ($s->is_approved === -1);
  $isUnderReview = (! $isRejected) && (! $s->is_approved || $s->status === 'under_review');
  $isActive      = $s->is_active && $s->is_approved && $s->status === 'active';
@endphp

<span class="zv-service-status-pill
  {{
    $isAdminDisabled ? 'is-admin-disabled' :
    ($isArchived ? 'is-archived' :
    ($isRejected ? 'is-reject' :
    ($isUnderReview ? 'is-review' :
    ($isActive ? 'is-active' : 'is-hidden'))))
  }}">
  {{
    $isAdminDisabled ? __('Disabled by Admin') :
    ($isArchived ? __('Archived') :
    ($isRejected ? __('Rejected') :
    ($isUnderReview ? __('Under Review') :
    ($isActive ? __('Active') : __('Hidden')))))
  }}
</span>



          </div>

          <div class="zv-service-body">
            <div class="zv-service-header-row">
              <h6 class="zv-service-title mb-0" title="{{ $s->title }}">
                {{ $s->title }}
              </h6>
              <span class="zv-service-category">
                {{ $s->category->name ?? __('Uncategorized') }}
              </span>
            </div>

            <p class="zv-service-desc line-clamp-2 mb-2">
              {{ $s->description }}
              @if(!is_null($s->admin_disabled_at) && filled($s->admin_disabled_reason))
  <div class="text-danger small mt-1">
    {{ __('Reason:') }} {{ $s->admin_disabled_reason }}
  </div>
@endif
            </p>

            <div class="zv-service-meta">
              <div class="zv-service-level">
                {{ ucfirst($s->service_level) }}
                @if($s->packages->count())
                  · {{ $s->packages->count() }} {{ __('packages') }}
                @endif
              </div>
              @if($s->updated_at)
                <div class="zv-service-updated">
                  {{ __('Updated') }} {{ $s->updated_at->diffForHumans() }}
                </div>
              @endif
            </div>

            <div class="zv-service-actions">
              <a href="{{ route('coach.services.edit', $s) }}" class="btn btn-sm btn-light zv-btn-icon">
                <i class="bi bi-pencil-square me-1"></i>{{ __('Edit') }}
              </a>

              @if(($s->status ?? null) !== 'archived')
  <form method="POST" action="{{ route('coach.services.toggle', $s) }}" class="m-0">
    @csrf
    <button class="btn btn-sm btn-outline-secondary zv-btn-icon" type="submit">
      <i class="bi {{ $s->is_active ? 'bi-eye-slash' : 'bi-eye' }} me-1"></i>
      {{ $s->is_active ? __('Hide') : __('Activate') }}
    </button>
  </form>
@else
  <button class="btn btn-sm btn-outline-secondary zv-btn-icon" type="button" disabled>
    <i class="bi bi-lock me-1"></i>{{ __('Archived') }}
  </button>
@endif


              <button
  type="button"
  class="btn btn-sm btn-outline-danger zv-btn-icon ms-auto"
  data-bs-toggle="modal"
  data-bs-target="#deleteServiceModal"
  data-service-id="{{ $s->id }}"
  data-service-title="{{ $s->title }}"
>
  <i class="bi bi-trash me-1"></i>{{ __('Delete') }}
</button>

            </div>
          </div>
        </article>
      @endforeach
    </div>

    <div class="zv-services-pagination">
      {{ $services->links() }}
    </div>
  @endif
</div>

{{-- Delete / Archive Modal --}}
<div class="modal fade" id="deleteServiceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">{{ __('Remove Service') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>

      <div class="modal-body">
        <p class="mb-2 text-capitalize">
          {{ __('Are you sure you want to remove this service?') }}
        </p>
        <div class="alert alert-warning mb-0 text-capitalize">
          <strong  id="deleteServiceTitle">—</strong><br>
          {{ __('If this service has bookings, it will be archived (hidden) instead of fully deleted.') }}
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
          {{ __('Cancel') }}
        </button>

        <form method="POST" id="deleteServiceForm" action="#">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-danger">
            {{ __('Yes, Remove') }}
          </button>
        </form>
      </div>

    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const modalEl = document.getElementById('deleteServiceModal');
  if (!modalEl) return;

  modalEl.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    const id = btn.getAttribute('data-service-id');
    const title = btn.getAttribute('data-service-title') || '—';

    // Update title in modal
    const titleEl = document.getElementById('deleteServiceTitle');
    if (titleEl) titleEl.textContent = title;

    // Set form action to destroy route
    const form = document.getElementById('deleteServiceForm');
    if (form) {
      form.action = "{{ url('/coach/services') }}/" + id; // <-- adjust if your route prefix differs
    }
  });
});
</script>
@endpush


@endsection


