@extends('superadmin.layout')
@section('title', 'Security · Locked Staff')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/superadmin-security.css') }}">
@endpush

@section('content')
@php
    $viewerTz = auth()->user()->timezone ?? config('app.timezone');
    $q = request('q', '');
    $role = request('role', 'all');

    $reasonLabels = [
        'manual_lock' => 'Manually Locked By Super Admin',
        'mass_deletion_3_in_3min' => 'High Risk: 3 User Deletions In 3 Minutes (Auto Locked)',
        'mass_deletion_10_in_24h' => 'High Risk: 10 User Deletions In 24 Hours (Auto Locked)',
        'mass_deletion_lock' => 'High Risk: Mass Deletion Activity (Auto Locked)',
    ];
@endphp

<section class="card zv-sec-card">
  <div class="zv-sec-head__content">
      <div class="card__title text-center">Locked Staff</div>
      <div class="muted text-capitalize text-center">Hard-lock / unlock admin & manager accounts</div>
  </div>
    <div class="card__head zv-sec-head">

        <div class="zv-sec-actions">
            <a class="btn ghost small" href="{{ route('superadmin.security.events') }}">Security Events</a>
            <a class="btn ghost small" href="{{ route('superadmin.security.logs') }}">Action Logs</a>
        </div>
    </div>

    <div class="zv-sec-toolbar">
        <form method="get" class="zv-sec-filters">
            <div class="zv-field">
                <label for="filter_q">Search</label>
                <input
                    id="filter_q"
                    type="text"
                    name="q"
                    value="{{ $q }}"
                    placeholder="Search By Name Or Email"
                >
            </div>

            <div class="zv-field">
                <label for="filter_role">Role</label>
                <select id="filter_role" name="role">
                    <option value="all" {{ $role === 'all' ? 'selected' : '' }}>All</option>
                    <option value="admin" {{ $role === 'admin' ? 'selected' : '' }}>Agent</option>
                    <option value="manager" {{ $role === 'manager' ? 'selected' : '' }}>Manager</option>
                </select>
            </div>

            <div class="zv-sec-filters__actions">
                <button class="btn primary small" type="submit">Apply</button>
                <a class="btn ghost small" href="{{ route('superadmin.security.locked_staff') }}">Reset</a>
            </div>
        </form>
    </div>

    @if(session('ok'))
        <div class="zv-alert zv-alert--ok">
            {{ session('ok') }}
        </div>
    @endif

    @if(session('error'))
        <div class="zv-alert zv-alert--danger">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="zv-alert zv-alert--danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="zv-sec-grid">
        @forelse($staff as $u)
            @php
                $name = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: '—';
                $locked = (bool) $u->is_locked;

                $reasonKey = (string) ($u->locked_reason ?? '');
                $reasonFull = $reasonLabels[$reasonKey] ?? ($reasonKey ? trim($reasonKey) : '—');

                $reasonWords = preg_split('/\s+/', trim($reasonFull)) ?: [];
                $reasonPreview = count($reasonWords) > 3
                    ? implode(' ', array_slice($reasonWords, 0, 3)) . '...'
                    : $reasonFull;
            @endphp

            <article class="zv-sec-item">
                <div class="zv-sec-top">
                    <div class="zv-sec-user">
                        <div class="zv-avatar">
                            {{ strtoupper(substr($u->email, 0, 1)) }}
                        </div>

                        <div class="zv-sec-user__content">
                            <div class="zv-sec-name">{{ $name }}</div>
                            <div class="zv-sec-sub">{{ $u->email }}</div>
                            <span class="pill text-capitalize">{{ $u->role_label }}</span>
                        </div>
                    </div>

                    <div class="zv-sec-status">
                        @if($locked)
                            <span class="zv-badge zv-badge--danger">Locked</span>
                        @else
                            <span class="zv-badge zv-badge--ok">Active</span>
                        @endif
                    </div>
                </div>

                <div class="zv-sec-meta">
                    <div class="zv-sec-meta__row">
                        <span class="zv-sec-meta__label">Locked At</span>
                        <span class="zv-sec-meta__value">
                            {{
                                $u->locked_at
                                    ? $u->locked_at->setTimezone('UTC')->setTimezone($viewerTz)->format('M d, Y · H:i')
                                    : '—'
                            }}
                        </span>
                    </div>

                    <div class="zv-sec-meta__row">
                        <span class="zv-sec-meta__label">Reason</span>

                        @if($reasonFull !== '—')
                            <span class="zv-reason-preview-wrap">
                                <span class="zv-reason-preview">{{ $reasonPreview }}</span>
                                <span class="zv-reason-tooltip">
                                    <span class="zv-reason-tooltip__label">Full Reason</span>
                                    <span class="zv-reason-tooltip__text">{{ $reasonFull }}</span>
                                </span>
                            </span>
                        @else
                            <span class="zv-sec-meta__value">—</span>
                        @endif
                    </div>
                </div>

                <div class="zv-sec-btns">
                    @if($locked)
                        <form method="post" action="{{ route('superadmin.security.locked_staff.unlock', $u) }}">
                            @csrf
                            <button class="btn ok small" type="submit">Unlock</button>
                        </form>
                    @else
                        <button
                            type="button"
                            class="btn danger small js-open-lock-modal"
                            data-action="{{ route('superadmin.security.locked_staff.lock', $u) }}"
                            data-name="{{ $name }}"
                        >
                            Lock
                        </button>
                    @endif
                </div>
            </article>
        @empty
            <div class="zv-empty">
                <div class="zv-empty__title">No staff found</div>
                <div class="zv-empty__sub">Try adjusting your filters.</div>
            </div>
        @endforelse
    </div>

    <div class="zv-sec-pager">
        {{ $staff->links() }}
    </div>
</section>

<div class="zv-modal" id="lockReasonModal" aria-hidden="true">
    <div class="zv-modal__backdrop"></div>

    <div class="zv-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="lockReasonModalTitle">
        <div class="zv-modal__head">
            <h3 id="lockReasonModalTitle" class="zv-modal__title">Lock Staff Account</h3>
            <button type="button" class="zv-modal__close" id="closeLockReasonModal" aria-label="Close modal">
                &times;
            </button>
        </div>

        <form method="post" id="lockReasonForm">
            @csrf

            <div class="zv-modal__body">
                <p class="zv-modal__text">
                    Please enter the reason for locking
                    <strong id="lockTargetName">this account</strong>.
                </p>

                <div class="zv-field">
                    <label for="lock_reason">
                        Reason
                        <span class="zv-required">*</span>
                    </label>

                    <textarea
                        id="lock_reason"
                        name="reason"
                        rows="4"
                        class="zv-textarea"
                        placeholder="Enter at least 3 words..."
                        required
                    >{{ old('reason') }}</textarea>

                    <div class="zv-word-help">
                        Minimum 3 words required.
                    </div>

                    <div class="zv-count-error" id="lockReasonError">
                        Reason must be at least 3 words.
                    </div>
                </div>
            </div>

            <div class="zv-modal__foot">
                <button type="button" class="btn ghost small" id="cancelLockReasonModal">Cancel</button>
                <button type="submit" class="btn danger small" id="confirmLockBtn">Confirm Lock</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('lockReasonModal');
    const form = document.getElementById('lockReasonForm');
    const reasonInput = document.getElementById('lock_reason');
    const targetName = document.getElementById('lockTargetName');
    const errorBox = document.getElementById('lockReasonError');

    const openButtons = document.querySelectorAll('.js-open-lock-modal');
    const closeBtn = document.getElementById('closeLockReasonModal');
    const cancelBtn = document.getElementById('cancelLockReasonModal');
    const backdrop = modal?.querySelector('.zv-modal__backdrop');

    function wordCount(value) {
        return (value || '')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .length;
    }

    function validateReason() {
        const count = wordCount(reasonInput.value);
        const valid = count >= 3;
        errorBox.style.display = valid ? 'none' : 'block';
        return valid;
    }

    function openModal(action, name) {
        form.setAttribute('action', action);
        targetName.textContent = name || 'this account';
        reasonInput.value = '';
        errorBox.style.display = 'none';
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('zv-modal-open');

        setTimeout(() => {
            reasonInput.focus();
        }, 50);
    }

    function closeModal() {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('zv-modal-open');
    }

    openButtons.forEach((button) => {
        button.addEventListener('click', function () {
            openModal(this.dataset.action, this.dataset.name);
        });
    });

    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);
    backdrop?.addEventListener('click', closeModal);

    reasonInput?.addEventListener('input', validateReason);

    form?.addEventListener('submit', function (e) {
        if (!validateReason()) {
            e.preventDefault();
            reasonInput.focus();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeModal();
        }
    });
});
</script>
@endpush
@endsection