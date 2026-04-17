@extends('layouts.admin')

@section('title', 'Withdrawals')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/superadmin-withdrawals.css') }}">

<style>
    .sa-break-text {
        word-break: break-word;
        white-space: normal;
    }

    .sa-failure-cell {
        min-width: 220px;
        max-width: 280px;
    }

    .sa-failure-preview {
        display: inline;
        color: #374151;
        font-size: 13px;
        line-height: 1.5;
    }

    .sa-view-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-top: 8px;
        padding: 6px 10px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        background: #ffffff;
        color: #111827;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .sa-view-btn:hover {
        background: #f9fafb;
        border-color: #9ca3af;
    }

    .sa-modal {
        position: fixed;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(15, 23, 42, 0.55);
        z-index: 9999;
    }

    .sa-modal.is-open {
        display: flex;
    }

    .sa-modal-dialog {
        width: 100%;
        max-width: 720px;
        max-height: 85vh;
        overflow: hidden;
        background: #ffffff;
        border-radius: 18px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.18);
        outline: none;
    }

    .sa-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 18px 22px;
        border-bottom: 1px solid #e5e7eb;
    }

    .sa-modal-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: #111827;
    }

    .sa-modal-close {
        border: none;
        background: transparent;
        font-size: 26px;
        line-height: 1;
        cursor: pointer;
        color: #6b7280;
    }

    .sa-modal-close:hover {
        color: #111827;
    }

    .sa-modal-body {
        padding: 22px;
        overflow-y: auto;
        max-height: calc(85vh - 140px);
    }

    .sa-modal-text {
        margin: 0;
        white-space: pre-wrap;
        word-break: break-word;
        color: #374151;
        font-size: 14px;
        line-height: 1.7;
    }

    .sa-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 16px 22px;
        border-top: 1px solid #e5e7eb;
        background: #fafafa;
    }

    .sa-btn-secondary {
        padding: 10px 14px;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        background: #fff;
        color: #111827;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .sa-btn-secondary:hover {
        background: #f3f4f6;
    }

    @media (max-width: 768px) {
        .sa-modal-dialog {
            max-width: 100%;
            max-height: 90vh;
            border-radius: 14px;
        }

        .sa-failure-cell {
            min-width: 200px;
            max-width: 240px;
        }
    }
</style>
@endpush

@section('content')
<div class="sa-withdrawals-page">

    <div class="sa-page-header">
        <div>
            <h1 class="sa-page-title">Withdrawals Management</h1>
            <p class="sa-page-subtitle">
                Monitor coach payout records, statuses, and provider references.
            </p>
        </div>
    </div>

    @if (session('success'))
        <div class="sa-alert sa-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="sa-alert sa-alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="sa-card sa-filters-card">
        <div class="sa-card-header">
            <h2>Filters</h2>
            <p>Filter withdrawals by period, status, provider, and coach details.</p>
        </div>

        <form method="GET" action="{{ route('admin.withdrawals.index') }}" id="withdrawalsFilterForm">
            <div class="sa-filter-grid">
                <div class="sa-field">
                    <label for="period">Period</label>
                    <select name="period" id="period" class="sa-input">
                        @foreach(($filterOptions['periods'] ?? []) as $value => $label)
                            <option value="{{ $value }}" {{ ($period ?? 'all') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sa-field js-period-field" data-period-field="daily">
                    <label for="day">Select Day</label>
                    <input
                        type="date"
                        name="day"
                        id="day"
                        class="sa-input"
                        value="{{ old('day', $selectedDay ?? '') }}"
                    >
                </div>

                <div class="sa-field js-period-field" data-period-field="weekly">
                    <label for="week">Select Week</label>
                    <input
                        type="week"
                        name="week"
                        id="week"
                        class="sa-input"
                        value="{{ old('week', $selectedWeek ?? '') }}"
                    >
                </div>

                <div class="sa-field js-period-field" data-period-field="monthly">
                    <label for="month">Select Month</label>
                    <input
                        type="month"
                        name="month"
                        id="month"
                        class="sa-input"
                        value="{{ old('month', $selectedMonth ?? '') }}"
                    >
                </div>

                <div class="sa-field js-period-field" data-period-field="yearly">
                    <label for="year">Select Year</label>
                    <input
                        type="number"
                        min="2000"
                        max="{{ now()->year + 5 }}"
                        name="year"
                        id="year"
                        class="sa-input"
                        value="{{ old('year', $selectedYear ?: now()->year) }}"
                    >
                </div>

                <div class="sa-field js-period-field" data-period-field="custom">
                    <label for="from">From</label>
                    <input
                        type="date"
                        name="from"
                        id="from"
                        class="sa-input"
                        value="{{ old('from', $customFrom ?? '') }}"
                    >
                </div>

                <div class="sa-field js-period-field" data-period-field="custom">
                    <label for="to">To</label>
                    <input
                        type="date"
                        name="to"
                        id="to"
                        class="sa-input"
                        value="{{ old('to', $customTo ?? '') }}"
                    >
                </div>

                <div class="sa-field">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="sa-input">
                        @foreach(($filterOptions['statuses'] ?? []) as $value => $label)
                            <option value="{{ $value }}" {{ ($status ?? 'all') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sa-field">
                    <label for="provider">Provider</label>
                    <select name="provider" id="provider" class="sa-input">
                        @foreach(($filterOptions['providers'] ?? []) as $value => $label)
                            <option value="{{ $value }}" {{ ($provider ?? 'all') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sa-field sa-field-search">
                    <label for="search">Search</label>
                    <input
                        type="text"
                        name="search"
                        id="search"
                        class="sa-input"
                        value="{{ old('search', $search ?? '') }}"
                        placeholder="Coach Name, Email, Payout ID, Transfer ID..."
                    >
                </div>
            </div>

            <div class="sa-filter-actions">
                <button type="submit" class="sa-btn sa-btn-primary">Apply Filters</button>
                <a href="{{ route('admin.withdrawals.index') }}" class="sa-btn sa-btn-light">Reset</a>
            </div>

            @if(!empty($startDate) || !empty($endDate))
                <div class="sa-filter-meta">
                    <strong>Current Date Range:</strong>
                    {{ $startDate ?? 'N/A' }} → {{ $endDate ?? 'N/A' }}
                </div>
            @endif
        </form>
    </div>

    <div class="sa-card sa-table-card">
        <div class="sa-card-header sa-card-header-between">
            <div>
                <h2>Withdrawal Records</h2>
                <p>Detailed payout rows for admin review.</p>
            </div>
            <div class="sa-table-count">
                Showing {{ $withdrawals->firstItem() ?? 0 }} - {{ $withdrawals->lastItem() ?? 0 }}
                of {{ $withdrawals->total() }}
            </div>
        </div>

        <div class="sa-table-responsive">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Coach</th>
                        <th>Provider</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Transfer Ref</th>
                        <th>Created</th>
                        <th>Paid</th>
                        <th>Failed</th>
                        <th>Failure Reason</th>
                        {{-- <th class="text-center">Action</th> --}}
                    </tr>
                </thead>
                <tbody>
                    @forelse ($withdrawals as $withdrawal)
                        @php
                            $user = optional(optional($withdrawal->coachProfile)->user);
                            $statusValue = strtolower((string) ($withdrawal->status ?? ''));
                            $currency = strtoupper((string) ($withdrawal->currency ?? 'USD'));
                            $amountMinor = (int) ($withdrawal->amount_minor ?? 0);
                            $amount = number_format($amountMinor / 100, 2);
                            $failureReason = (string) ($withdrawal->failure_reason ?? '');
                        @endphp

                        <tr>
                            <td>
                                <span class="sa-id-badge">#{{ $withdrawal->id }}</span>
                            </td>

                            <td>
                                <div class="sa-user-cell">
                                    <strong>{{ $user->full_name ?: ($user->name ?? 'N/A') }}</strong>
                                    <span>{{ $user->email ?? 'No email' }}</span>
                                </div>
                            </td>

                            <td>
                                <span class="sa-provider-pill">
                                    {{ strtoupper((string) ($withdrawal->provider ?? 'N/A')) }}
                                </span>
                            </td>

                            <td>
                                <div class="sa-amount-cell">
                                    <strong>{{ $amount }} {{ $currency }}</strong>
                                </div>
                            </td>

                            <td>
                                <span class="sa-status-badge sa-status-{{ str_replace('_', '-', $statusValue ?: 'unknown') }}">
                                    {{ ucfirst(str_replace('_', ' ', (string) ($withdrawal->status ?? 'Unknown'))) }}
                                </span>
                            </td>

                            <td class="sa-break-text">
                                {{ $withdrawal->provider_transfer_id ?: '—' }}
                            </td>

                            <td>
                                <div class="sa-date-cell">
                                    <strong>{{ optional($withdrawal->created_at)->format('d M Y') ?: '—' }}</strong>
                                    <span>{{ optional($withdrawal->created_at)->format('h:i A') ?: '' }}</span>
                                </div>
                            </td>

                            <td>
                                @if($withdrawal->paid_at)
                                    <div class="sa-date-cell">
                                        <strong>{{ optional($withdrawal->paid_at)->format('d M Y') }}</strong>
                                        <span>{{ optional($withdrawal->paid_at)->format('h:i A') }}</span>
                                    </div>
                                @else
                                    —
                                @endif
                            </td>

                            <td>
                                @if($withdrawal->failed_at)
                                    <div class="sa-date-cell">
                                        <strong>{{ optional($withdrawal->failed_at)->format('d M Y') }}</strong>
                                        <span>{{ optional($withdrawal->failed_at)->format('h:i A') }}</span>
                                    </div>
                                @else
                                    —
                                @endif
                            </td>

                            <td class="sa-break-text sa-failure-cell">
                                @if($failureReason !== '')
                                    <span class="sa-failure-preview">
                                        {{ \Illuminate\Support\Str::limit($failureReason, 70) }}
                                    </span>
                                    <br>
                                    <button
                                        type="button"
                                        class="sa-view-btn js-view-failure"
                                        data-failure-reason='@json($failureReason)'
                                    >
                                        View Details
                                    </button>
                                @else
                                    —
                                @endif
                            </td>

                            {{-- <td class="text-center">
                                <a href="{{ route('admin.withdrawals.show', $withdrawal->id) }}" class="sa-btn sa-btn-light">
                                    View
                                </a>
                            </td> --}}
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">
                                <div class="sa-empty-state">
                                    <h4>No withdrawals found</h4>
                                    <p>Try changing filters, date range, or search keywords.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($withdrawals->hasPages())
            <div class="sa-pagination-wrap">
                {{ $withdrawals->links() }}
            </div>
        @endif
    </div>
</div>

<div id="failureReasonModal" class="sa-modal" aria-hidden="true">
    <div
        class="sa-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="failureReasonModalTitle"
        tabindex="-1"
    >
        <div class="sa-modal-header">
            <h3 id="failureReasonModalTitle">Failure Reason</h3>
            <button type="button" class="sa-modal-close" id="closeFailureModal" aria-label="Close">&times;</button>
        </div>

        <div class="sa-modal-body">
            <p class="sa-modal-text" id="failureReasonModalText">—</p>
        </div>

        <div class="sa-modal-footer">
            <button type="button" class="sa-btn-secondary" id="copyFailureReasonBtn">Copy</button>
            <button type="button" class="sa-btn-secondary" id="dismissFailureModal">Close</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const periodSelect = document.getElementById('period');
        const periodFields = document.querySelectorAll('.js-period-field');

        function togglePeriodFields() {
            const currentPeriod = periodSelect ? periodSelect.value : 'all';

            periodFields.forEach(function (field) {
                const acceptedPeriod = field.getAttribute('data-period-field');
                const shouldShow = acceptedPeriod === currentPeriod;

                field.style.display = shouldShow ? '' : 'none';

                field.querySelectorAll('input, select').forEach(function (input) {
                    input.disabled = !shouldShow;
                });
            });
        }

        if (periodSelect) {
            togglePeriodFields();
            periodSelect.addEventListener('change', togglePeriodFields);
        }

        const modal = document.getElementById('failureReasonModal');
        const modalDialog = modal ? modal.querySelector('.sa-modal-dialog') : null;
        const modalText = document.getElementById('failureReasonModalText');
        const closeFailureModal = document.getElementById('closeFailureModal');
        const dismissFailureModal = document.getElementById('dismissFailureModal');
        const copyFailureReasonBtn = document.getElementById('copyFailureReasonBtn');
        const triggerButtons = document.querySelectorAll('.js-view-failure');

        let lastFocusedElement = null;

        function getFailureReason(button) {
            const raw = button.getAttribute('data-failure-reason');

            if (!raw) {
                return '—';
            }

            try {
                return JSON.parse(raw);
            } catch (error) {
                return raw;
            }
        }

        function openFailureModal(text) {
            if (!modal || !modalText) return;

            lastFocusedElement = document.activeElement;
            modalText.textContent = text || '—';
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            if (modalDialog) {
                modalDialog.focus();
            }
        }

        function closeModal() {
            if (!modal) return;

            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';

            if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
                lastFocusedElement.focus();
            }
        }

        triggerButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                openFailureModal(getFailureReason(button));
            });
        });

        if (closeFailureModal) {
            closeFailureModal.addEventListener('click', closeModal);
        }

        if (dismissFailureModal) {
            dismissFailureModal.addEventListener('click', closeModal);
        }

        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal && modal.classList.contains('is-open')) {
                closeModal();
            }
        });

        function fallbackCopyText(text) {
            const tempInput = document.createElement('textarea');
            tempInput.value = text;
            tempInput.setAttribute('readonly', '');
            tempInput.style.position = 'absolute';
            tempInput.style.left = '-9999px';
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
        }

        if (copyFailureReasonBtn) {
            copyFailureReasonBtn.addEventListener('click', async function () {
                const textToCopy = modalText ? (modalText.textContent || '') : '';

                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(textToCopy);
                    } else {
                        fallbackCopyText(textToCopy);
                    }

                    copyFailureReasonBtn.textContent = 'Copied';
                } catch (error) {
                    copyFailureReasonBtn.textContent = 'Copy failed';
                }

                setTimeout(function () {
                    copyFailureReasonBtn.textContent = 'Copy';
                }, 1500);
            });
        }
    })();
</script>
@endpush