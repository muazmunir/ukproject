@extends('layouts.admin') {{-- change to your admin layout --}}

@section('title', 'Newsletter Subscribers')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/newsletter-subscribers.css') }}">
@endpush


@section('content')
<div class="container-fluid py-4">

  {{-- Header --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h3 class="mb-1 fw-bold">Newsletter Subscribers</h3>
      <div class="text-muted small text-capitalize">Manage email subscriptions, status, and exports.</div>
    </div>

    <div class="d-flex gap-2">
      <a class="btn btn-outline-dark btn-sm" href="{{ route('admin.newsletter.subscribers.index') }}">
        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
      </a>

      {{-- Optional export button (add later) --}}
      {{-- <a class="btn btn-dark btn-sm" href="{{ route('admin.newsletter.subscribers.export') }}">
        <i class="bi bi-download me-1"></i> Export CSV
      </a> --}}
    </div>
  </div>

  {{-- Flash --}}
  @if (session('success'))
    <div class="alert alert-success d-flex align-items-center gap-2">
      <i class="bi bi-check-circle"></i>
      <div>{{ session('success') }}</div>
    </div>
  @endif

  {{-- Stats cards --}}
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
      <div class="card zv-card shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small">Total Subscribers</div>
              <div class="fs-3 fw-bold">{{ number_format($total) }}</div>
            </div>
            <div class="zv-icon-pill">
              <i class="bi bi-people"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card zv-card shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small">Active</div>
              <div class="fs-3 fw-bold">{{ number_format($active) }}</div>
            </div>
            <div class="zv-icon-pill zv-icon-pill--success">
              <i class="bi bi-check2-circle"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card zv-card shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small">Inactive</div>
              <div class="fs-3 fw-bold">{{ number_format($inactive) }}</div>
            </div>
            <div class="zv-icon-pill zv-icon-pill--muted">
              <i class="bi bi-slash-circle"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Filters --}}
  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
      <form class="row g-2 align-items-end" method="GET" action="{{ route('admin.newsletter.subscribers.index') }}">
        <div class="col-12 col-md-6">
          <label class="form-label small text-muted mb-1">Search Email</label>
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="e.g. user@gmail.com">
          </div>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label small text-muted mb-1">Status</label>
          <select name="status" class="form-select">
            <option value="">All</option>
            <option value="active" @selected($status === 'active')>Active</option>
            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
          </select>
        </div>

        <div class="col-12 col-md-3 d-flex gap-2">
          <button class="btn btn-dark w-100">
            <i class="bi bi-funnel me-1"></i> Apply
          </button>
          <a class="btn btn-outline-secondary w-100" href="{{ route('admin.newsletter.subscribers.index') }}">
            Reset
          </a>
        </div>
      </form>
    </div>
  </div>

  {{-- Table --}}
  <div class="card shadow-sm border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="zv-thead">
            <tr>
              <th class="ps-4">Email</th>
              <th>Status</th>
              <th>Subscribed At</th>
              <th>Created</th>
              <th class="text-end pe-4">Actions</th>
            </tr>
          </thead>
          <tbody>
          @forelse($subscribers as $s)
            <tr>
              <td class="ps-4">
                <div class="fw-semibold">{{ $s->email }}</div>
                <div class="text-muted small">#{{ $s->id }}</div>
              </td>

              <td>
                @if($s->is_active)
                  <span class="badge text-bg-success-subtle zv-badge">
                    <i class="bi bi-check2-circle me-1"></i> Active
                  </span>
                @else
                  <span class="badge text-bg-secondary-subtle zv-badge">
                    <i class="bi bi-slash-circle me-1"></i> Inactive
                  </span>
                @endif
              </td>

              <td class="text-muted">
                {{ optional($s->subscribed_at)->format('M d, Y h:i A') ?? '—' }}
              </td>

              <td class="text-muted">
                {{ optional($s->created_at)->format('M d, Y') ?? '—' }}
              </td>

              <td class="text-end pe-4">
                <div class="d-inline-flex gap-2">
                  <form method="POST" action="{{ route('admin.newsletter.subscribers.toggle', $s) }}">
                    @csrf
                    @method('PATCH')
                    <label class="zv-switch">
  <input type="checkbox"
         onchange="this.form.submit()"
         {{ $s->is_active ? 'checked' : '' }}>
  <span class="zv-slider"></span>
</label>

                  </form>

                  <button type="button"
        class="btn icon danger"
        data-bs-toggle="modal"
        data-bs-target="#deleteSubscriberModal"
        data-action="{{ route('admin.newsletter.subscribers.destroy', $s) }}"
        data-email="{{ $s->email }}">
  <i class="bi bi-trash3"></i>
</button>

                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center py-5">
                <div class="text-muted">No subscribers found.</div>
              </td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if($subscribers->hasPages())
      <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center">
        <div class="small text-muted">
          Showing {{ $subscribers->firstItem() }}–{{ $subscribers->lastItem() }} of {{ $subscribers->total() }}
        </div>
        <div>
          {{ $subscribers->links() }}
        </div>
      </div>
    @endif
  </div>

</div>


{{-- Delete Modal --}}
<div class="modal fade" id="deleteSubscriberModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content zv-modal">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">Delete subscriber?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body pt-2">
        <p class="mb-2 text-muted text-capitalize">
          You are about to permanently remove:
        </p>
        <div class="zv-modal-email" id="deleteSubscriberEmail">—</div>

        <div class="alert alert-warning mt-3 mb-0 text-capitalize">
          This action cannot be undone.
        </div>
      </div>

      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          Cancel
        </button>

        <form id="deleteSubscriberForm" method="POST" action="#">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-trash3 me-1"></i> Delete
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

@endsection


@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('deleteSubscriberModal');
    const formEl  = document.getElementById('deleteSubscriberForm');
    const emailEl = document.getElementById('deleteSubscriberEmail');

    modalEl.addEventListener('show.bs.modal', function (event) {
      const btn = event.relatedTarget;
      const action = btn.getAttribute('data-action');
      const email  = btn.getAttribute('data-email');

      formEl.setAttribute('action', action);
      emailEl.textContent = email || '—';
    });
  });
</script>
@endpush

