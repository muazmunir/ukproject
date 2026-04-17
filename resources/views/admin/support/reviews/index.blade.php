@extends('layouts.admin')

@section('title', 'Support Reviews Analytics')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-support-reviews.css') }}">
@endpush

@section('content')
<div class="sr-page">
    <div class="sr-head">
        <div>
            <h1 class="sr-title">Support Reviews Analytics</h1>
            <p class="sr-subtitle text-capitalize">Manager-only visibility into admin support ratings, feedback, and performance.</p>
        </div>
    </div>

    <div class="sr-filterbar">
        <form method="GET" action="{{ route('admin.support.reviews.index') }}" class="sr-filterform" id="js-review-filter">
            @php
                $active = strtolower(request('range', 'lifetime'));

                $selectedYear  = (int) request('year', now()->year);
                $selectedMonth = (int) request('month', now()->month);
                $selectedDay   = request('day', now()->format('Y-m-d'));

                $showDay    = ($active === 'daily');
                $showYear   = in_array($active, ['yearly','monthly'], true);
                $showMonth  = ($active === 'monthly');
                $showCustom = ($active === 'custom') || request()->hasAny(['from','to']);
            @endphp

            <div class="sr-pillgroup" role="group" aria-label="Date filters">
                <button type="button" name="range" value="daily" class="sr-pill {{ $active==='daily' ? 'is-active' : '' }}">Daily</button>
                <button type="button" name="range" value="weekly" class="sr-pill {{ $active==='weekly' ? 'is-active' : '' }}">Weekly</button>
                <button type="button" name="range" value="monthly" class="sr-pill {{ $active==='monthly' ? 'is-active' : '' }}">Monthly</button>
                <button type="button" name="range" value="yearly" class="sr-pill {{ $active==='yearly' ? 'is-active' : '' }}">Yearly</button>
                <button type="button" name="range" value="lifetime" class="sr-pill {{ in_array($active,['lifetime','all'],true) ? 'is-active' : '' }}">All Time</button>
                <button type="button" name="range" value="custom" class="sr-pill {{ $active==='custom' ? 'is-active' : '' }}">Custom</button>
            </div>

            <input type="hidden" name="range" value="{{ $active }}">

            <div class="sr-range">
                <label class="sr-range-field sr-admin-pick">
                    <span>Agent</span>

                    <input
                        type="text"
                        id="admin_lookup"
                        class="sr-input"
                        list="admin_list"
                        placeholder="Search Any Agent"
                        value="{{ $selectedAdmin?->username ?? '' }}"
                        autocomplete="off"
                    >

                    <datalist id="admin_list">
                        @foreach($admins as $admin)
                            <option
                                value="{{ $admin->username }}"
                                data-id="{{ $admin->id }}"
                                label="{{ trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? '')) }} — {{ $admin->email }}"
                            >
                                {{ $admin->username }}
                            </option>
                        @endforeach
                    </datalist>

                    <input type="hidden" name="admin_id" id="admin_id" value="{{ $selectedAdminId }}">
                </label>

                <label class="sr-range-field" style="{{ $showDay ? '' : 'display:none' }}">
                    <span>Day</span>
                    <input type="date" name="day" value="{{ $selectedDay }}">
                </label>

                <label class="sr-range-field" style="{{ $showYear ? '' : 'display:none' }}">
                    <span>Year</span>
                    <select name="year">
                        @for($y = now()->year; $y >= now()->year - 5; $y--)
                            <option value="{{ $y }}" {{ $selectedYear === $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </label>

                <label class="sr-range-field" style="{{ $showMonth ? '' : 'display:none' }}">
                    <span>Month</span>
                    <select name="month">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $selectedMonth === $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                            </option>
                        @endfor
                    </select>
                </label>

                <label class="sr-range-field" style="{{ $showCustom ? '' : 'display:none' }}">
                    <span>From</span>
                    <input type="date" name="from" value="{{ request('from') }}">
                </label>

                <label class="sr-range-field" style="{{ $showCustom ? '' : 'display:none' }}">
                    <span>To</span>
                    <input type="date" name="to" value="{{ request('to') }}">
                </label>

                <a href="{{ route('admin.support.reviews.index') }}"
                   class="sr-linkbtn"
                   id="js-review-reset"
                   style="{{ ($active !== 'lifetime' || request()->hasAny(['from','to','year','month','day','admin_id'])) ? '' : 'display:none' }}">
                    Clear Filter
                </a>
            </div>
        </form>

        <div class="sr-meta">
            <div class="sr-meta-label">Current period</div>
            <div class="sr-meta-value text-capitalize">{{ $periodLabel }}</div>
        </div>
    </div>

    <div class="sr-cards">
        <div class="sr-card">
            <div class="sr-card-label">Total Reviews</div>
            <div class="sr-card-value">{{ number_format($summary['total_reviews']) }}</div>
        </div>

        <div class="sr-card">
            <div class="sr-card-label">Average Rating</div>
            <div class="sr-card-value">{{ number_format($summary['avg_rating'], 2) }}/5</div>
        </div>

        <div class="sr-card">
            <div class="sr-card-label">5-Star Rate</div>
            <div class="sr-card-value">{{ number_format($summary['five_star_rate'], 1) }}%</div>
        </div>

        <div class="sr-card">
            <div class="sr-card-label">Resolved Conversations</div>
            <div class="sr-card-value">{{ number_format($summary['resolved_count']) }}</div>
        </div>
    </div>

    <div class="sr-grid">
        <section class="sr-panel">
            <div class="sr-panel-head">
                <h2>Star Distribution</h2>
            </div>

            <div class="sr-stars">
                @php
                    $maxDist = max($distribution ?: [1]);
                @endphp

                @for($star = 5; $star >= 1; $star--)
                    @php
                        $count = $distribution[$star] ?? 0;
                        $pct = $maxDist > 0 ? ($count / $maxDist) * 100 : 0;
                    @endphp

                    <div class="sr-star-row">
                        <div class="sr-star-label">{{ $star }} Star</div>
                        <div class="sr-bar-wrap">
                            <div class="sr-bar" style="width: {{ $pct }}%"></div>
                        </div>
                        <div class="sr-star-count">{{ $count }}</div>
                    </div>
                @endfor
            </div>
        </section>

        <section class="sr-panel">
            <div class="sr-panel-head">
                <h2>Selected Agent</h2>
            </div>

            @if($selectedAdmin)
                <div class="sr-admin-box">
                    <div class="sr-admin-name">{{ $selectedAdmin->username }}</div>
                    <div class="sr-admin-meta">
                        {{ trim(($selectedAdmin->first_name ?? '') . ' ' . ($selectedAdmin->last_name ?? '')) ?: 'No full name' }}
                    </div>
                    <div class="sr-admin-meta">{{ $selectedAdmin->email ?? '—' }}</div>
                </div>
            @else
                <div class="sr-empty-note text-capitalize">
                    No admin selected. Showing overall review analytics across all admins.
                </div>
            @endif
        </section>
    </div>

    <section class="sr-panel mt-4">
        <div class="sr-panel-head">
            <h2>Agent Performance</h2>
        </div>

        <div class="table-responsive">
            <table class="table sr-table align-middle">
                <thead>
                    <tr>
                        <th>Agent</th>
                        <th>Total Reviews</th>
                        <th>Average Rating</th>
                        <th>5 Stars</th>
                        <th>Low Ratings (1–2)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leaderboard as $row)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $row->username }}</div>
                                <div class="text-muted small">
                                    {{ trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')) ?: '—' }}
                                </div>
                                <div class="text-muted small">{{ $row->email }}</div>
                            </td>
                            <td>{{ $row->total_reviews }}</td>
                            <td><span class="sr-rating-pill">{{ number_format((float)$row->avg_rating, 2) }}/5</span></td>
                            <td>{{ $row->five_star_count }}</td>
                            <td>{{ $row->low_star_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4 text-capitalize">No review data found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $leaderboard->links() }}
        </div>
    </section>

    <section class="sr-panel mt-4">
        <div class="sr-panel-head">
            <h2>Recent Feedback</h2>
        </div>

        <div class="sr-feedback-list">
            @forelse($recentFeedback as $item)
                <div class="sr-feedback-card">
                  <div class="sr-feedback-top">
    <div>
        <div class="sr-feedback-admin">
            Rated Agent: {{ $item->admin_username ?? 'admin' }}
        </div>

        <div class="sr-feedback-rater">
            By:
            <strong>{{ $item->rater_username ?? 'unknown' }}</strong>
            <span class="sr-rater-role">({{ ucfirst($item->scope_role ?? 'user') }})</span>
        </div>

        <div class="sr-feedback-date">
            {{ \Carbon\Carbon::parse($item->created_at)->format('d M Y · H:i') }}
        </div>
    </div>

    <div class="sr-rating-pill">{{ (int)$item->stars }}/5</div>
</div>

<div class="sr-feedback-body">{{ $item->feedback }}</div>

<div class="sr-feedback-foot">
    Conversation #{{ $item->conversation_id }}
</div>
                </div>
            @empty
                <div class="sr-empty-note text-capitalize">
                    No written feedback found for the current filters.
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('js-review-filter');
    if (!form) return;

    const pills = form.querySelectorAll('.sr-pill[name="range"]');
    const rangeInput = form.querySelector('input[type="hidden"][name="range"]');

    const dayField   = form.querySelector('input[name="day"]')?.closest('.sr-range-field');
    const yearField  = form.querySelector('select[name="year"]')?.closest('.sr-range-field');
    const monthField = form.querySelector('select[name="month"]')?.closest('.sr-range-field');
    const fromField  = form.querySelector('input[name="from"]')?.closest('.sr-range-field');
    const toField    = form.querySelector('input[name="to"]')?.closest('.sr-range-field');

    const resetBtn = document.getElementById('js-review-reset');

    const adminLookup = document.getElementById('admin_lookup');
    const adminId = document.getElementById('admin_id');
    const adminList = document.getElementById('admin_list');

    function syncAdminId() {
        const val = (adminLookup.value || '').trim().toLowerCase();
        let foundId = '';

        Array.from(adminList.options).forEach(option => {
            if ((option.value || '').trim().toLowerCase() === val) {
                foundId = option.dataset.id || '';
            }
        });

        adminId.value = foundId;
    }

    function setActivePill(value) {
        pills.forEach(btn => btn.classList.toggle('is-active', btn.value === value));
    }

    function showFields(range) {
        const showDay   = (range === 'daily');
        const showYear  = (range === 'yearly' || range === 'monthly');
        const showMonth = (range === 'monthly');
        const showCust  = (range === 'custom');

        if (dayField)   dayField.style.display   = showDay ? '' : 'none';
        if (yearField)  yearField.style.display  = showYear ? '' : 'none';
        if (monthField) monthField.style.display = showMonth ? '' : 'none';
        if (fromField)  fromField.style.display  = showCust ? '' : 'none';
        if (toField)    toField.style.display    = showCust ? '' : 'none';
    }

    pills.forEach(btn => {
        btn.addEventListener('click', () => {
            const range = btn.value.toLowerCase();
            rangeInput.value = range;

            setActivePill(range);
            showFields(range);

            if (range !== 'daily') {
                const day = form.querySelector('input[name="day"]');
                if (day) day.value = '';
            }

            if (range !== 'custom') {
                const from = form.querySelector('input[name="from"]');
                const to   = form.querySelector('input[name="to"]');
                if (from) from.value = '';
                if (to) to.value = '';
            }

            form.submit();
        });
    });

    form.querySelector('select[name="year"]')?.addEventListener('change', () => form.submit());
    form.querySelector('select[name="month"]')?.addEventListener('change', () => form.submit());
    form.querySelector('input[name="day"]')?.addEventListener('change', () => form.submit());
    form.querySelector('input[name="from"]')?.addEventListener('change', () => form.submit());
    form.querySelector('input[name="to"]')?.addEventListener('change', () => form.submit());

    adminLookup?.addEventListener('input', syncAdminId);
    adminLookup?.addEventListener('change', function () {
        syncAdminId();
        form.submit();
    });

    resetBtn?.addEventListener('click', function () {
        adminId.value = '';
    });

    const initRange = (rangeInput.value || 'lifetime').toLowerCase();
    showFields(initRange);
});
</script>
@endpush