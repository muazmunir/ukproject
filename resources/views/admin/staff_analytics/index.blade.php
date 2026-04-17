@extends('layouts.admin')

@section('title', 'Staff Analytics')

@php
    use Carbon\Carbon;

    $activeTab = $filters['tab'] ?? 'overview';
    $selectedPeriod = $filters['period'] ?? 'all';

    $formatDate = function ($date, $format = 'd M Y') {
        return $date ? Carbon::parse($date)->format($format) : '—';
    };

    $formatDateTime = function ($date, $format = 'd M Y, h:i A') {
        return $date ? Carbon::parse($date)->format($format) : '—';
    };

    $formatMinutes = function ($value) {
        return $value !== null ? number_format((float) $value, 1) . ' Min' : '—';
    };

    $formatPercent = function ($value) {
        return $value !== null ? number_format((float) $value, 1) . '%' : '—';
    };

    $formatHours = function ($seconds) {
        return $seconds !== null ? number_format(((float) $seconds / 3600), 2) . ' Hr' : '—';
    };

    $starText = function ($stars) {
        $stars = (int) $stars;
        return str_repeat('★', $stars) . str_repeat('☆', max(0, 5 - $stars));
    };

    $ratingPercent = function ($star) use ($ratingDistribution, $summary) {
        $count = (int) ($ratingDistribution[$star]->total ?? 0);
        $total = (int) ($summary->rating_count ?? 0);

        return $total > 0 ? round(($count / $total) * 100, 1) : 0;
    };
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/super-staff-analytics.css') }}">
@endpush

@section('content')
<div class="sa-page">
    <div class="sa-container">

        {{-- Header --}}
        <section class="sa-hero-card">
            <div class="sa-hero-top">
                <div class="sa-hero-copy">
                    <span class="sa-eyebrow">Support Performance Dashboard</span>
                    <h1 class="sa-page-title">Staff Analytics</h1>
                    <p class="sa-page-subtitle text-capitalize">
                        Track your team’s support performance, ratings, escalations, and dispute operations
                        with clearer process health metrics and fairer agent contribution visibility.
                    </p>
                </div>

                <div class="sa-hero-stats">
                    <div class="sa-mini-stat">
                        <span class="sa-mini-stat-label">Total Staff</span>
                        <strong class="sa-mini-stat-value">{{ number_format($summary->total_staff ?? 0) }}</strong>
                    </div>
                    <div class="sa-mini-stat">
                        <span class="sa-mini-stat-label">Agents</span>
                        <strong class="sa-mini-stat-value">{{ number_format($summary->total_agents ?? 0) }}</strong>
                    </div>
                    <div class="sa-mini-stat">
                        <span class="sa-mini-stat-label">Rated Staff</span>
                        <strong class="sa-mini-stat-value">{{ number_format($summary->rated_staff_count ?? 0) }}</strong>
                    </div>
                </div>
            </div>
        </section>

        {{-- Tabs --}}
        <div class="sa-tabs-wrap">
            <nav class="sa-tabs">
                @foreach($tabOptions as $key => $label)
                    <a
                        href="{{ route('admin.staff_analytics.index', array_merge(request()->query(), ['tab' => $key])) }}"
                        class="sa-tab {{ $activeTab === $key ? 'is-active' : '' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.staff_analytics.index') }}" class="sa-filter-card">
            <input type="hidden" name="tab" value="{{ $activeTab }}">

            <div class="sa-filter-grid">
                <div class="sa-field sa-field-lg">
                    <label class="sa-label">Search Staff</label>
                    <input
                        type="text"
                        name="q"
                        value="{{ $filters['q'] }}"
                        class="sa-input"
                        placeholder="Search by username, name, or email">
                </div>

                <div class="sa-field">
                    <label class="sa-label">Period</label>
                    <select name="period" id="saPeriodSelect" class="sa-select">
                        @foreach($periodOptions as $key => $label)
                            <option value="{{ $key }}" {{ $selectedPeriod === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sa-field sa-period-field" data-period-field="daily" style="{{ $selectedPeriod === 'daily' ? '' : 'display:none;' }}">
                    <label class="sa-label">Select Day</label>
                    <input
                        type="date"
                        name="day"
                        value="{{ $filters['day'] ?? now()->format('Y-m-d') }}"
                        class="sa-input">
                </div>

                <div class="sa-field sa-period-field" data-period-field="weekly" style="{{ $selectedPeriod === 'weekly' ? '' : 'display:none;' }}">
                    <label class="sa-label">Select Week</label>
                    <input
                        type="week"
                        name="week"
                        value="{{ $filters['week'] ?? now()->format('o-\WW') }}"
                        class="sa-input">
                </div>

                <div class="sa-field sa-period-field" data-period-field="monthly" style="{{ $selectedPeriod === 'monthly' ? '' : 'display:none;' }}">
                    <label class="sa-label">Select Month</label>
                    <input
                        type="month"
                        name="month"
                        value="{{ $filters['month'] ?? now()->format('Y-m') }}"
                        class="sa-input">
                </div>

                <div class="sa-field sa-period-field" data-period-field="yearly" style="{{ $selectedPeriod === 'yearly' ? '' : 'display:none;' }}">
                    <label class="sa-label">Select Year</label>
                    <input
                        type="number"
                        name="year"
                        min="2020"
                        max="2100"
                        value="{{ $filters['year'] ?? now()->year }}"
                        class="sa-input"
                        placeholder="YYYY">
                </div>

                <div class="sa-field sa-period-field" data-period-field="custom" style="{{ $selectedPeriod === 'custom' ? '' : 'display:none;' }}">
                    <label class="sa-label">From Date</label>
                    <input
                        type="date"
                        name="from"
                        value="{{ $filters['custom_from'] ? $filters['custom_from']->format('Y-m-d') : '' }}"
                        class="sa-input">
                </div>

                <div class="sa-field sa-period-field" data-period-field="custom" style="{{ $selectedPeriod === 'custom' ? '' : 'display:none;' }}">
                    <label class="sa-label">To Date</label>
                    <input
                        type="date"
                        name="to"
                        value="{{ $filters['custom_to'] ? $filters['custom_to']->format('Y-m-d') : '' }}"
                        class="sa-input">
                </div>

                <div class="sa-field">
                    <label class="sa-label">Team</label>
                    <select name="team_id" id="teamSelect" class="sa-select">
                        <option value="">All Teams</option>
                        @foreach($teamOptions as $team)
                            <option value="{{ $team->id }}" {{ (int) $filters['team_id'] === (int) $team->id ? 'selected' : '' }}>
                                {{ $team->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sa-field">
                    <label class="sa-label">Staff</label>
                    <select name="staff_id" id="staffSelect" class="sa-select">
                        <option value="">All Staff</option>
                        @foreach($staffOptions as $staff)
                            <option
                                value="{{ $staff->id }}"
                                data-team-id="{{ $staff->team_id }}"
                                {{ (int) $filters['staff_id'] === (int) $staff->id ? 'selected' : '' }}>
                                {{ $staff->label }} ({{ $staff->role }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sa-field">
                    <label class="sa-label">Sort By</label>
                    <select name="sort" class="sa-select">
                        @foreach($sortOptions as $key => $label)
                            <option value="{{ $key }}" {{ $filters['sort'] === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sa-field">
                    <label class="sa-label">Staff Per Page</label>
                    <select name="staff_per" class="sa-select">
                        @foreach([10, 15, 20, 30, 50] as $limit)
                            <option value="{{ $limit }}" {{ (int) $filters['staff_per'] === $limit ? 'selected' : '' }}>
                                {{ $limit }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sa-field">
                    <label class="sa-label">Ratings Per Page</label>
                    <select name="ratings_per" class="sa-select">
                        @foreach([10, 12, 15, 20, 30, 50] as $limit)
                            <option value="{{ $limit }}" {{ (int) $filters['ratings_per'] === $limit ? 'selected' : '' }}>
                                {{ $limit }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sa-field">
                    <label class="sa-label">Leaderboard Size</label>
                    <select name="leaderboard_limit" class="sa-select">
                        @foreach([3, 5, 7, 10] as $limit)
                            <option value="{{ $limit }}" {{ (int) $filters['leaderboard_limit'] === $limit ? 'selected' : '' }}>
                                Top {{ $limit }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sa-field">
                    <label class="sa-label">Stars</label>
                    <select name="stars" class="sa-select">
                        <option value="">All Ratings</option>
                        @for($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}" {{ (int) $filters['stars'] === $i ? 'selected' : '' }}>
                                {{ $i }} Star
                            </option>
                        @endfor
                    </select>
                </div>
            </div>

            <div class="sa-filter-actions">
                <button type="submit" class="sa-btn sa-btn-primary">Apply Filters</button>
                <a href="{{ route('admin.staff_analytics.index') }}" class="sa-btn sa-btn-secondary">Reset</a>

                @if($selectedStaff)
                    <a href="{{ route('admin.staff_analytics.index', array_merge(request()->except('staff_id'), ['tab' => 'overview'])) }}" class="sa-btn sa-btn-light">
                        Clear Selected Staff
                    </a>
                @endif
            </div>
        </form>

        {{-- KPI Cards --}}
        <section class="sa-kpi-grid">
            <div class="sa-kpi-card">
                <span class="sa-kpi-label">Chats Completed</span>
                <strong class="sa-kpi-value">{{ number_format($summary->completed_chats ?? 0) }}</strong>
                <span class="sa-kpi-note">Resolved conversations closed by staff</span>
            </div>

            <div class="sa-kpi-card">
                <span class="sa-kpi-label">Average Handling Time</span>
                <strong class="sa-kpi-value">{{ $formatMinutes($summary->avg_handling_minutes ?? null) }}</strong>
                <span class="sa-kpi-note">Support chat handling time</span>
            </div>

            <div class="sa-kpi-card">
                <span class="sa-kpi-label">Requeued Chats</span>
                <strong class="sa-kpi-value">{{ number_format($summary->requeued_chats ?? 0) }}</strong>
                <span class="sa-kpi-note">Returned to queue</span>
            </div>

            <div class="sa-kpi-card">
                <span class="sa-kpi-label">Escalations Requested</span>
                <strong class="sa-kpi-value">{{ number_format($summary->escalated_chats ?? 0) }}</strong>
                <span class="sa-kpi-note">Manager escalations</span>
            </div>

            <div class="sa-kpi-card">
                <span class="sa-kpi-label">Average Rating</span>
                <strong class="sa-kpi-value">
                    {{ $summary->avg_rating !== null ? number_format($summary->avg_rating, 2) . ' / 5' : '—' }}
                </strong>
                <span class="sa-kpi-note">{{ number_format($summary->rating_count ?? 0) }} total ratings</span>
            </div>

            <div class="sa-kpi-card">
                <span class="sa-kpi-label">Disputes Created</span>
                <strong class="sa-kpi-value">{{ number_format($summary->disputes_created ?? 0) }}</strong>
                <span class="sa-kpi-note">Overall dispute intake</span>
            </div>
        </section>

        {{-- Overview Tab --}}
        @if($activeTab === 'overview')
            <section class="sa-grid sa-grid-2">
                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <div>
                            <h3 class="sa-panel-title">Ratings Distribution</h3>
                        </div>
                    </div>
                    <div class="sa-chart-card">
                        <canvas id="ratingsDistributionChart"></canvas>
                    </div>

                    <div class="sa-rating-stack">
                        @for($star = 5; $star >= 1; $star--)
                            <div class="sa-rating-row">
                                <div class="sa-rating-left">
                                    <a href="{{ route('admin.staff_analytics.index', array_merge(request()->query(), ['tab' => 'ratings', 'stars' => $star])) }}" class="sa-star-link">
                                        {{ $star }} Star
                                    </a>
                                </div>
                                <div class="sa-rating-progress">
                                    <div class="sa-rating-progress-bar" style="width: {{ $ratingPercent($star) }}%;"></div>
                                </div>
                                <div class="sa-rating-right">
                                    {{ number_format((int) ($ratingDistribution[$star]->total ?? 0)) }} ({{ $ratingPercent($star) }}%)
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <div>
                            <h3 class="sa-panel-title">Overview Comparison</h3>
                        </div>
                    </div>
                    <div class="sa-chart-card">
                        <canvas id="overviewComparisonChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="sa-grid sa-grid-2">
                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <div>
                            <h3 class="sa-panel-title">Role Distribution</h3>
                        </div>
                    </div>
                    <div class="sa-chart-card chart-card-sm">
                        <canvas id="roleDistributionChart"></canvas>
                    </div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <div>
                            <h3 class="sa-panel-title">Top Rated Staff</h3>
                        </div>
                    </div>
                    <div class="sa-chart-card chart-card-sm">
                        <canvas id="topRatedChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="sa-grid sa-grid-2">
                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <div>
                            <h3 class="sa-panel-title">Highest Rated</h3>
                        </div>
                    </div>

                    <div class="sa-list">
                        @forelse($topRatedStaff as $index => $staff)
                            <div class="sa-list-item">
                                <div class="sa-rank sa-rank-success">#{{ $index + 1 }}</div>
                                <div class="sa-list-content">
                                    <div class="sa-list-top">
                                        <a href="{{ $staff->details_url }}" class="sa-link-title">{{ $staff->display_name }}</a>
                                        <span class="sa-badge">{{ $staff->display_role }}</span>
                                    </div>
                                    <div class="sa-list-meta">
                                        <span>{{ $staff->team_name }}</span>
                                        <span>Manager: {{ $staff->team_manager }}</span>
                                        <span>{{ $staff->avg_rating !== null ? number_format($staff->avg_rating, 2) . ' / 5' : '—' }}</span>
                                        <span>{{ number_format($staff->rating_count) }} Ratings</span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="sa-empty-state text-capitalize">No rated staff found for selected filters.</div>
                        @endforelse
                    </div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <div>
                            <h3 class="sa-panel-title">Lowest Rated</h3>
                        </div>
                    </div>

                    <div class="sa-list">
                        @forelse($lowestRatedStaff as $index => $staff)
                            <div class="sa-list-item">
                                <div class="sa-rank sa-rank-danger">#{{ $index + 1 }}</div>
                                <div class="sa-list-content">
                                    <div class="sa-list-top">
                                        <a href="{{ $staff->details_url }}" class="sa-link-title">{{ $staff->display_name }}</a>
                                        <span class="sa-badge">{{ $staff->display_role }}</span>
                                    </div>
                                    <div class="sa-list-meta">
                                        <span>{{ $staff->team_name }}</span>
                                        <span>Manager: {{ $staff->team_manager }}</span>
                                        <span>{{ $staff->avg_rating !== null ? number_format($staff->avg_rating, 2) . ' / 5' : '—' }}</span>
                                        <span>{{ number_format($staff->rating_count) }} Ratings</span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="sa-empty-state text-capitalize">No low-rated staff found for selected filters.</div>
                        @endforelse
                    </div>
                </div>
            </section>
        @endif

        {{-- Performance Tab --}}
        @if($activeTab === 'performance')
            <section class="sa-grid sa-grid-2">
                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <h3 class="sa-panel-title">Handling Time</h3>
                    </div>
                    <div class="sa-chart-card">
                        <canvas id="handlingTimeChart"></canvas>
                    </div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <h3 class="sa-panel-title">Requeue</h3>
                    </div>
                    <div class="sa-chart-card">
                        <canvas id="requeueChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="sa-panel">
                <div class="sa-panel-head">
                    <h3 class="sa-panel-title">Staff Performance Table</h3>
                </div>

                <div class="sa-table-wrap">
                    <table class="sa-table">
                        <thead>
                            <tr>
                                <th>Staff</th>
                                <th>Role</th>
                                <th>Team</th>
                                <th>Manager</th>
                                <th>Completed</th>
                                <th>Closed</th>
                                <th>Avg Handling</th>
                                <th>Requeued</th>
                                <th>Escalations Requested</th>
                                <th>Escalations Handled</th>
                                <th>Rating</th>
                                <th>Ratings</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($staffPerformance as $row)
                                <tr>
                                    <td>
                                        <div class="sa-person-cell">
                                            <a href="{{ $row->details_url }}" class="sa-name-link">{{ $row->display_name }}</a>
                                            <span>{{ $row->email ?: '—' }}</span>
                                        </div>
                                    </td>
                                    <td><span class="sa-badge">{{ $row->display_role }}</span></td>
                                    <td>{{ $row->team_name }}</td>
                                    <td>{{ $row->team_manager }}</td>
                                    <td>{{ number_format($row->completed_chats) }}</td>
                                    <td>{{ number_format($row->closed_chats) }}</td>
                                    <td>{{ $row->avg_handling_minutes !== null ? number_format($row->avg_handling_minutes, 1) . ' Min' : '—' }}</td>
                                    <td>{{ number_format($row->requeued_chats) }}</td>
                                    <td>{{ number_format($row->escalations_requested) }}</td>
                                    <td>{{ number_format($row->escalations_handled) }}</td>
                                    <td class="sa-rating-text">
                                        {{ $row->avg_rating !== null ? number_format($row->avg_rating, 2) . ' / 5' : '—' }}
                                    </td>
                                    <td>{{ number_format($row->rating_count) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12">
                                        <div class="sa-empty-state">No staff performance records found.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($staffPerformance->hasPages())
                    <div class="sa-pagination-wrap">
                        {{ $staffPerformance->links() }}
                    </div>
                @endif
            </section>
        @endif

        {{-- Ratings Tab --}}
        @if($activeTab === 'ratings')
            <section class="sa-grid sa-grid-2">
                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <h3 class="sa-panel-title">Top Rated Staff</h3>
                    </div>
                    <div class="sa-chart-card">
                        <canvas id="topRatedChartRatingsTab"></canvas>
                    </div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <h3 class="sa-panel-title">Lowest Rated Staff</h3>
                    </div>
                    <div class="sa-chart-card">
                        <canvas id="lowestRatedChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="sa-grid sa-grid-5">
                @for($star = 5; $star >= 1; $star--)
                    <div class="sa-stat-card">
                        <a
                            href="{{ route('admin.staff_analytics.index', array_merge(request()->query(), ['tab' => 'ratings', 'stars' => $star])) }}"
                            class="sa-stat-link">
                            <span class="sa-stat-label">{{ $star }} Star</span>
                            <strong class="sa-stat-value">{{ number_format((int) ($ratingDistribution[$star]->total ?? 0)) }}</strong>
                            <span class="sa-stat-note">{{ $ratingPercent($star) }}% of total ratings</span>
                        </a>
                    </div>
                @endfor
            </section>

            <section class="sa-panel">
                <div class="sa-panel-head">
                    <h3 class="sa-panel-title">Ratings & Feedback</h3>
                </div>

                <div class="sa-table-wrap">
                    <table class="sa-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Staff</th>
                                <th>Role</th>
                                <th>Customer</th>
                                <th>Stars</th>
                                <th>Feedback</th>
                                <th>Conversation ID</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ratings as $row)
                                <tr>
                                    <td>{{ $formatDateTime($row->created_at) }}</td>
                                    <td>
                                        @if($row->details_url)
                                            <a href="{{ $row->details_url }}" class="sa-name-link">{{ $row->staff_name }}</a>
                                        @else
                                            {{ $row->staff_name }}
                                        @endif
                                    </td>
                                    <td><span class="sa-badge">{{ $row->staff_role }}</span></td>
                                    <td>{{ $row->customer_name }}</td>
                                    <td>
                                        <a
                                            href="{{ route('admin.staff_analytics.index', array_merge(request()->query(), ['tab' => 'ratings', 'stars' => $row->stars])) }}"
                                            class="sa-star-link">
                                            <div class="sa-stars-block">
                                                <span class="sa-stars">{{ $starText($row->stars) }}</span>
                                                <strong>{{ $row->stars }}/5</strong>
                                            </div>
                                        </a>
                                    </td>
                                    <td class="sa-feedback-text">{{ $row->feedback ?: 'No feedback submitted' }}</td>
                                    <td>
                                        @if(!empty($row->conversation_url))
                                            <a href="{{ $row->conversation_url }}" class="sa-conv-link">#{{ $row->conversation_id }}</a>
                                        @else
                                            #{{ $row->conversation_id }}
                                        @endif
                                    </td>
                                    <td>{{ $row->conversation_status ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="sa-empty-state text-capitalize">No ratings found for selected filters.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($ratings->hasPages())
                    <div class="sa-pagination-wrap">
                        {{ $ratings->links() }}
                    </div>
                @endif
            </section>
        @endif

        {{-- Disputes Tab --}}
        @if($activeTab === 'disputes')
            <section class="sa-grid sa-grid-6">
                <div class="sa-kpi-card">
                    <span class="sa-kpi-label">Disputes Created</span>
                    <strong class="sa-kpi-value">{{ number_format($summary->disputes_created ?? 0) }}</strong>
                    <span class="sa-kpi-note">Overall dispute intake</span>
                </div>

                <div class="sa-kpi-card">
                    <span class="sa-kpi-label">Resolved Disputes</span>
                    <strong class="sa-kpi-value">{{ number_format($summary->resolved_disputes ?? 0) }}</strong>
                    <span class="sa-kpi-note">Fully resolved disputes</span>
                </div>

                <div class="sa-kpi-card">
                    <span class="sa-kpi-label">Queue Disputes</span>
                    <strong class="sa-kpi-value">{{ number_format($summary->queue_disputes ?? 0) }}</strong>
                    <span class="sa-kpi-note">Waiting in queue and unassigned</span>
                </div>

                <div class="sa-kpi-card">
                    <span class="sa-kpi-label">Opened Disputes</span>
                    <strong class="sa-kpi-value">{{ number_format($summary->opened_disputes ?? 0) }}</strong>
                    <span class="sa-kpi-note">Active assigned work</span>
                </div>

                <div class="sa-kpi-card">
                    <span class="sa-kpi-label">In Review</span>
                    <strong class="sa-kpi-value">{{ number_format($summary->in_review_disputes ?? 0) }}</strong>
                    <span class="sa-kpi-note">Review-stage disputes</span>
                </div>

                <div class="sa-kpi-card">
                    <span class="sa-kpi-label">Resolution Rate</span>
                    <strong class="sa-kpi-value">{{ $formatPercent($summary->resolution_rate ?? 0) }}</strong>
                    <span class="sa-kpi-note">Resolved vs created</span>
                </div>
            </section>

            <section class="sa-grid sa-grid-2">
                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <h3 class="sa-panel-title">Process Efficiency</h3>
                    </div>

                    <div class="sa-kpi-grid sa-kpi-grid-nested">
                        <div class="sa-kpi-card">
                            <span class="sa-kpi-label">Avg Handling Time</span>
                            <strong class="sa-kpi-value">{{ $formatMinutes($summary->avg_dispute_handling_minutes ?? null) }}</strong>
                            <span class="sa-kpi-note">Decision-based SLA time</span>
                        </div>

                        <div class="sa-kpi-card">
                            <span class="sa-kpi-label">Avg Resolution Time</span>
                            <strong class="sa-kpi-value">{{ $formatMinutes($summary->avg_dispute_resolution_minutes ?? null) }}</strong>
                            <span class="sa-kpi-note">Created to resolved</span>
                        </div>
                    </div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <h3 class="sa-panel-title">Dispute Status Mix</h3>
                    </div>
                    <div class="sa-chart-card">
                        <canvas id="disputeStatusChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="sa-grid sa-grid-2">
                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <h3 class="sa-panel-title">Dispute Actions</h3>
                    </div>
                    <div class="sa-chart-card">
                        <canvas id="disputeActionsChart"></canvas>
                    </div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <h3 class="sa-panel-title">Most Dispute Decisions</h3>
                    </div>
                    <div class="sa-chart-card">
                        <canvas id="disputeDecisionChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="sa-grid sa-grid-2">
                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <h3 class="sa-panel-title">Most Resolved Disputes</h3>
                    </div>
                    <div class="sa-chart-card">
                        <canvas id="disputeResolutionChart"></canvas>
                    </div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <h3 class="sa-panel-title">Most Active Assigned Disputes</h3>
                    </div>
                    <div class="sa-chart-card">
                        <canvas id="openDisputeLoadChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="sa-grid sa-grid-2">
                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <h3 class="sa-panel-title">Most Sent To Review</h3>
                    </div>
                    <div class="sa-chart-card">
                        <canvas id="sentToReviewChart"></canvas>
                    </div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel-head">
                        <h3 class="sa-panel-title">Top Action Leaders</h3>
                    </div>

                    <div class="sa-list">
                        @forelse($disputeDecisionLeaders as $index => $staff)
                            <div class="sa-list-item">
                                <div class="sa-rank sa-rank-dark">#{{ $index + 1 }}</div>
                                <div class="sa-list-content">
                                    <div class="sa-list-top">
                                        <a href="{{ $staff->details_url }}" class="sa-link-title">{{ $staff->display_name }}</a>
                                        <span class="sa-badge">{{ $staff->display_role }}</span>
                                    </div>
                                    <div class="sa-list-meta">
                                        <span>{{ $staff->team_name }}</span>
                                        <span>Manager: {{ $staff->team_manager }}</span>
                                        <span>{{ number_format($staff->dispute_decisions ?? 0) }} Decisions</span>
                                        <span>{{ number_format($staff->resolved_disputes ?? 0) }} Resolved</span>
                                        <span>{{ number_format($staff->sent_to_review ?? 0) }} Sent to Review</span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="sa-empty-state">No dispute contribution data found.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="sa-panel">
                <div class="sa-panel-head">
                    <h3 class="sa-panel-title">Dispute Staff Contribution Table</h3>
                </div>

                <div class="sa-table-wrap">
                    <table class="sa-table">
                        <thead>
                            <tr>
                                <th>Staff</th>
                                <th>Role</th>
                                <th>Team</th>
                                <th>Manager</th>
                                <th>Decisions</th>
                                <th>Resolved</th>
                                <th>Opened Load</th>
                                <th>Sent To Review</th>
                                <th>Pay Coach</th>
                                <th>Refund Full</th>
                                <th>Refund Service</th>
                                <th>Rejected</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($staffPerformance as $row)
                                <tr>
                                    <td>
                                        <div class="sa-person-cell">
                                            <a href="{{ $row->details_url }}" class="sa-name-link">{{ $row->display_name }}</a>
                                            <span>{{ $row->email ?: '—' }}</span>
                                        </div>
                                    </td>
                                    <td><span class="sa-badge">{{ $row->display_role }}</span></td>
                                    <td>{{ $row->team_name }}</td>
                                    <td>{{ $row->team_manager }}</td>
                                    <td>{{ number_format($row->dispute_decisions ?? 0) }}</td>
                                    <td>{{ number_format($row->resolved_disputes ?? 0) }}</td>
                                    <td>{{ number_format($row->opened_disputes ?? 0) }}</td>
                                    <td>{{ number_format($row->sent_to_review ?? 0) }}</td>
                                    <td>{{ number_format($row->dispute_pay_coach ?? 0) }}</td>
                                    <td>{{ number_format($row->dispute_refund_full ?? 0) }}</td>
                                    <td>{{ number_format($row->dispute_refund_service ?? 0) }}</td>
                                    <td>{{ number_format($row->dispute_rejected ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12">
                                        <div class="sa-empty-state">No dispute records found.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($staffPerformance->hasPages())
                    <div class="sa-pagination-wrap">
                        {{ $staffPerformance->links() }}
                    </div>
                @endif
            </section>
        @endif

        {{-- Staff Details --}}
        @if($activeTab === 'staff' || $selectedStaff)
            <section class="sa-panel sa-staff-focus-panel">
                <div class="sa-panel-head">
                    <h3 class="sa-panel-title">Staff Details</h3>
                </div>

                @if($selectedStaff)
                    <div class="sa-profile-card">
                        <div class="sa-profile-main">
                            <div class="sa-profile-avatar">
                                {{ strtoupper(substr($selectedStaff->name, 0, 1)) }}
                            </div>

                            <div class="sa-profile-copy">
                                <h2 class="sa-profile-name">{{ $selectedStaff->name }}</h2>
                                <div class="sa-profile-meta">
                                    <span class="sa-badge">{{ $selectedStaff->role }}</span>
                                    <span>{{ $selectedStaff->email ?: '—' }}</span>
                                    <span>Team: {{ $selectedStaff->team_name }}</span>
                                    <span>Manager: {{ $selectedStaff->team_manager }}</span>
                                    <span>Joined: {{ $formatDate($selectedStaff->service_start_at) }}</span>
                                    <span>Service Length: {{ number_format($selectedStaff->service_days) }} Days</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sa-kpi-grid sa-kpi-grid-detail">
                        <div class="sa-kpi-card">
                            <span class="sa-kpi-label">Completed Chats</span>
                            <strong class="sa-kpi-value">{{ number_format($selectedStaff->metrics->completed_chats) }}</strong>
                        </div>

                        <div class="sa-kpi-card">
                            <span class="sa-kpi-label">Closed Chats</span>
                            <strong class="sa-kpi-value">{{ number_format($selectedStaff->metrics->closed_chats) }}</strong>
                        </div>

                        <div class="sa-kpi-card">
                            <span class="sa-kpi-label">Avg Handling</span>
                            <strong class="sa-kpi-value">{{ $formatMinutes($selectedStaff->metrics->avg_handling_minutes) }}</strong>
                        </div>

                        <div class="sa-kpi-card">
                            <span class="sa-kpi-label">Requeued</span>
                            <strong class="sa-kpi-value">{{ number_format($selectedStaff->metrics->requeued_chats) }}</strong>
                        </div>

                        <div class="sa-kpi-card">
                            <span class="sa-kpi-label">Escalations Requested</span>
                            <strong class="sa-kpi-value">{{ number_format($selectedStaff->metrics->escalations_requested) }}</strong>
                        </div>

                        <div class="sa-kpi-card">
                            <span class="sa-kpi-label">Escalations Handled</span>
                            <strong class="sa-kpi-value">{{ number_format($selectedStaff->metrics->escalations_handled) }}</strong>
                        </div>

                        <div class="sa-kpi-card">
                            <span class="sa-kpi-label">Average Rating</span>
                            <strong class="sa-kpi-value">
                                {{ $selectedStaff->metrics->avg_rating !== null ? number_format($selectedStaff->metrics->avg_rating, 2) . ' / 5' : '—' }}
                            </strong>
                        </div>

                        <div class="sa-kpi-card">
                            <span class="sa-kpi-label">Ratings Count</span>
                            <strong class="sa-kpi-value">{{ number_format($selectedStaff->metrics->rating_count) }}</strong>
                        </div>

                        <div class="sa-kpi-card">
                            <span class="sa-kpi-label">Dispute Decisions</span>
                            <strong class="sa-kpi-value">{{ number_format($selectedStaff->metrics->dispute_decisions ?? 0) }}</strong>
                        </div>

                        <div class="sa-kpi-card">
                            <span class="sa-kpi-label">Resolved Disputes</span>
                            <strong class="sa-kpi-value">{{ number_format($selectedStaff->metrics->resolved_disputes ?? 0) }}</strong>
                        </div>

                        <div class="sa-kpi-card">
                            <span class="sa-kpi-label">Opened Disputes</span>
                            <strong class="sa-kpi-value">{{ number_format($selectedStaff->metrics->opened_disputes ?? 0) }}</strong>
                        </div>

                        <div class="sa-kpi-card">
                            <span class="sa-kpi-label">Sent To Review</span>
                            <strong class="sa-kpi-value">{{ number_format($selectedStaff->metrics->sent_to_review ?? 0) }}</strong>
                        </div>
                    </div>

                    <div class="sa-grid sa-grid-2">
                        <div class="sa-panel sa-panel-nested">
                            <div class="sa-panel-head">
                                <h3 class="sa-panel-title">Personal Rating Distribution</h3>
                            </div>

                            <div class="sa-chart-card chart-card-sm">
                                <canvas id="selectedStaffRatingsChart"></canvas>
                            </div>

                            <div class="sa-rating-stack">
                                @php $selectedTotal = (int) ($selectedStaff->metrics->rating_count ?? 0); @endphp
                                @for($star = 5; $star >= 1; $star--)
                                    @php
                                        $count = (int) ($selectedStaff->rating_distribution[$star]->total ?? 0);
                                        $percent = $selectedTotal > 0 ? round(($count / $selectedTotal) * 100, 1) : 0;
                                    @endphp
                                    <div class="sa-rating-row">
                                        <div class="sa-rating-left">{{ $star }} Star</div>
                                        <div class="sa-rating-progress">
                                            <div class="sa-rating-progress-bar" style="width: {{ $percent }}%;"></div>
                                        </div>
                                        <div class="sa-rating-right">{{ number_format($count) }} ({{ $percent }}%)</div>
                                    </div>
                                @endfor
                            </div>
                        </div>

                        <div class="sa-panel sa-panel-nested">
                            <div class="sa-panel-head">
                                <h3 class="sa-panel-title">Recent Ratings</h3>
                            </div>

                            <div class="sa-comment-list">
                                @forelse($selectedStaff->recent_ratings as $rating)
                                    <div class="sa-comment-item">
                                        <div class="sa-comment-top">
                                            <strong>{{ $rating->customer_name }}</strong>
                                            <span>{{ $starText($rating->stars) }} ({{ $rating->stars }}/5)</span>
                                        </div>
                                        <div class="sa-comment-meta">
                                            <span>{{ $formatDateTime($rating->created_at) }}</span>
                                            <span>
                                                @if(!empty($rating->conversation_url))
                                                    <a href="{{ $rating->conversation_url }}" class="sa-conv-link">Conversation #{{ $rating->conversation_id }}</a>
                                                @else
                                                    Conversation #{{ $rating->conversation_id }}
                                                @endif
                                            </span>
                                            <span>{{ $rating->conversation_status ?: '—' }}</span>
                                        </div>
                                        <p class="sa-comment-body">
                                            {{ $rating->feedback ?: 'No feedback submitted for this rating.' }}
                                        </p>
                                    </div>
                                @empty
                                    <div class="sa-empty-state">No recent ratings found for this staff member.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="sa-panel sa-panel-nested sa-mt-18">
                        <div class="sa-panel-head">
                            <h3 class="sa-panel-title">Recent Conversations</h3>
                        </div>

                        <div class="sa-table-wrap">
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>Conversation ID</th>
                                        <th>Thread ID</th>
                                        <th>Status</th>
                                        <th>Customer</th>
                                        <th>Scope Role</th>
                                        <th>Created</th>
                                        <th>Closed</th>
                                        <th>Escalated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($selectedStaff->recent_conversations as $conversation)
                                        <tr>
                                            <td>
                                                @if(!empty($conversation->details_url))
                                                    <a href="{{ $conversation->details_url }}" class="sa-conv-link">#{{ $conversation->id }}</a>
                                                @else
                                                    #{{ $conversation->id }}
                                                @endif
                                            </td>
                                            <td>{{ $conversation->thread_id ?: '—' }}</td>
                                            <td>{{ $conversation->status ?: '—' }}</td>
                                            <td>{{ $conversation->customer_name }}</td>
                                            <td>{{ $conversation->scope_role ?: '—' }}</td>
                                            <td>{{ $formatDateTime($conversation->created_at) }}</td>
                                            <td>{{ $formatDateTime($conversation->closed_at) }}</td>
                                            <td>{{ $formatDateTime($conversation->manager_requested_at) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8">
                                                <div class="sa-empty-state">No recent conversations found for this staff member.</div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="sa-panel sa-panel-nested sa-mt-18">
                        <div class="sa-panel-head">
                            <h3 class="sa-panel-title">Recent Disputes</h3>
                        </div>

                        <div class="sa-table-wrap">
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>Dispute ID</th>
                                        <th>Status</th>
                                        <th>Label</th>
                                        <th>Reservation</th>
                                        <th>Client</th>
                                        <th>Coach</th>
                                        <th>Decision</th>
                                        <th>Handling Time</th>
                                        <th>Decided At</th>
                                        <th>Resolved At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($selectedStaff->recent_disputes as $dispute)
                                        <tr>
                                            <td>
                                                @if(!empty($dispute->details_url))
                                                    <a href="{{ $dispute->details_url }}" class="sa-conv-link">#{{ $dispute->id }}</a>
                                                @else
                                                    #{{ $dispute->id }}
                                                @endif
                                            </td>
                                            <td>{{ $dispute->status ?: '—' }}</td>
                                            <td>{{ $dispute->title_label ?: '—' }}</td>
                                            <td>{{ $dispute->reservation_id ?: '—' }}</td>
                                            <td>{{ $dispute->client_name }}</td>
                                            <td>{{ $dispute->coach_name }}</td>
                                            <td>{{ $dispute->decision_action ?: '—' }}</td>
                                            <td>{{ $formatHours($dispute->sla_total_seconds ?? 0) }}</td>
                                            <td>{{ $formatDateTime($dispute->decided_at) }}</td>
                                            <td>{{ $formatDateTime($dispute->resolved_at) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10">
                                                <div class="sa-empty-state">No recent disputes found for this staff member.</div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="sa-empty-state">
                        Select a staff member from filters or tables to view full performance, ratings, conversations, and dispute details.
                    </div>
                @endif
            </section>
        @endif

    </div>
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (() => {
            const chartData = @json($chartData);
            const selectedStaffChart = @json($selectedStaff->rating_distribution_chart ?? null);

            const commonFont = { size: 11, weight: '600' };

            const baseOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            boxWidth: 12,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { size: 12, weight: '700' }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.96)',
                        padding: 12,
                        titleFont: { size: 13, weight: '700' },
                        bodyFont: { size: 12, weight: '600' }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: commonFont
                        },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.18)'
                        }
                    },
                    x: {
                        ticks: {
                            font: commonFont
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            };

            const makeBarChart = (id, labels, values, labelText, color = 'rgba(14, 165, 233, 0.75)', border = 'rgba(14, 165, 233, 1)', maxOverride = null) => {
                const el = document.getElementById(id);
                if (!el) return;

                const options = {
                    ...baseOptions,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                font: commonFont
                            },
                            grid: {
                                color: 'rgba(148, 163, 184, 0.18)'
                            }
                        },
                        x: {
                            ticks: {
                                font: commonFont
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                };

                if (maxOverride !== null) {
                    options.scales.y.max = maxOverride;
                }

                new Chart(el, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: labelText,
                            data: values,
                            borderRadius: 8,
                            maxBarThickness: 40,
                            backgroundColor: color,
                            borderColor: border,
                            borderWidth: 1
                        }]
                    },
                    options
                });
            };

            const makeLineChart = (id, labels, values, labelText) => {
                const el = document.getElementById(id);
                if (!el) return;

                new Chart(el, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: labelText,
                            data: values,
                            tension: 0.35,
                            fill: false,
                            borderWidth: 3,
                            borderColor: 'rgba(37, 99, 235, 1)',
                            pointRadius: 4,
                            pointHoverRadius: 5,
                            pointBackgroundColor: 'rgba(37, 99, 235, 1)'
                        }]
                    },
                    options: baseOptions
                });
            };

            const makePieChart = (id, labels, values) => {
                const el = document.getElementById(id);
                if (!el) return;

                new Chart(el, {
                    type: 'pie',
                    data: {
                        labels,
                        datasets: [{
                            data: values,
                            backgroundColor: [
                                'rgba(37, 99, 235, 0.9)',
                                'rgba(16, 185, 129, 0.85)',
                                'rgba(245, 158, 11, 0.85)',
                                'rgba(239, 68, 68, 0.85)',
                                'rgba(139, 92, 246, 0.85)',
                                'rgba(99, 102, 241, 0.85)'
                            ],
                            borderColor: '#ffffff',
                            borderWidth: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    font: { size: 12, weight: '700' }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const chart = context.chart;
                                        const data = chart.data.datasets[0].data || [];
                                        const total = data.reduce((a, b) => a + Number(b || 0), 0);
                                        const value = Number(context.raw || 0);
                                        const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                        return `${context.label}: ${value} (${pct}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            };

            makePieChart(
                'ratingsDistributionChart',
                chartData.ratings_distribution.labels,
                chartData.ratings_distribution.values
            );

            makeLineChart(
                'overviewComparisonChart',
                chartData.overview_comparison.labels,
                chartData.overview_comparison.values,
                'Overview'
            );

            makePieChart(
                'roleDistributionChart',
                chartData.role_distribution.labels,
                chartData.role_distribution.values
            );

            makeBarChart(
                'topRatedChart',
                chartData.top_rated_chart.labels,
                chartData.top_rated_chart.values,
                'Average Rating',
                'rgba(34, 197, 94, 0.72)',
                'rgba(34, 197, 94, 1)',
                5
            );

            makeBarChart(
                'topRatedChartRatingsTab',
                chartData.top_rated_chart.labels,
                chartData.top_rated_chart.values,
                'Average Rating',
                'rgba(34, 197, 94, 0.72)',
                'rgba(34, 197, 94, 1)',
                5
            );

            makeBarChart(
                'lowestRatedChart',
                chartData.lowest_rated_chart.labels,
                chartData.lowest_rated_chart.values,
                'Average Rating',
                'rgba(239, 68, 68, 0.72)',
                'rgba(239, 68, 68, 1)',
                5
            );

            makeBarChart(
                'handlingTimeChart',
                chartData.handling_time_chart.labels,
                chartData.handling_time_chart.values,
                'Handling Minutes',
                'rgba(14, 165, 233, 0.72)',
                'rgba(14, 165, 233, 1)'
            );

            makeBarChart(
                'requeueChart',
                chartData.requeue_chart.labels,
                chartData.requeue_chart.values,
                'Requeued Chats',
                'rgba(99, 102, 241, 0.72)',
                'rgba(99, 102, 241, 1)'
            );

            makeBarChart(
                'disputeActionsChart',
                chartData.dispute_actions_chart?.labels || [],
                chartData.dispute_actions_chart?.values || [],
                'Dispute Actions',
                'rgba(99, 102, 241, 0.76)',
                'rgba(99, 102, 241, 1)'
            );

            makePieChart(
                'disputeStatusChart',
                chartData.dispute_status_chart?.labels || [],
                chartData.dispute_status_chart?.values || []
            );

            makeBarChart(
                'disputeDecisionChart',
                chartData.dispute_decision_chart?.labels || [],
                chartData.dispute_decision_chart?.values || [],
                'Dispute Decisions',
                'rgba(245, 158, 11, 0.76)',
                'rgba(245, 158, 11, 1)'
            );

            makeBarChart(
                'disputeResolutionChart',
                chartData.dispute_resolution_chart?.labels || [],
                chartData.dispute_resolution_chart?.values || [],
                'Resolved Disputes',
                'rgba(236, 72, 153, 0.76)',
                'rgba(236, 72, 153, 1)'
            );

            makeBarChart(
                'openDisputeLoadChart',
                chartData.dispute_open_load_chart?.labels || [],
                chartData.dispute_open_load_chart?.values || [],
                'Opened Disputes',
                'rgba(168, 85, 247, 0.76)',
                'rgba(168, 85, 247, 1)'
            );

            makeBarChart(
                'sentToReviewChart',
                chartData.sent_to_review_chart?.labels || [],
                chartData.sent_to_review_chart?.values || [],
                'Sent To Review',
                'rgba(16, 185, 129, 0.76)',
                'rgba(16, 185, 129, 1)'
            );

            if (selectedStaffChart) {
                makeBarChart(
                    'selectedStaffRatingsChart',
                    selectedStaffChart.labels,
                    selectedStaffChart.values,
                    'Ratings',
                    'rgba(37, 99, 235, 0.75)',
                    'rgba(37, 99, 235, 1)'
                );
            }

            const periodSelect = document.getElementById('saPeriodSelect');
            const periodFields = document.querySelectorAll('.sa-period-field');

            const togglePeriodFields = () => {
                const period = periodSelect ? periodSelect.value : 'all';

                periodFields.forEach(field => {
                    const matches = field.getAttribute('data-period-field') === period;
                    field.style.display = matches ? '' : 'none';
                });
            };

            if (periodSelect) {
                periodSelect.addEventListener('change', togglePeriodFields);
                togglePeriodFields();
            }
        })();

        const teamSelect = document.getElementById('teamSelect');
        const staffSelect = document.getElementById('staffSelect');

        if (teamSelect && staffSelect) {
            const filterStaff = () => {
                const selectedTeam = teamSelect.value;

                Array.from(staffSelect.options).forEach(option => {
                    const teamId = option.getAttribute('data-team-id');

                    if (!option.value) {
                        option.style.display = 'block';
                        return;
                    }

                    if (!selectedTeam) {
                        option.style.display = 'block';
                    } else {
                        option.style.display = (teamId == selectedTeam) ? 'block' : 'none';
                    }
                });

                const selectedOption = staffSelect.options[staffSelect.selectedIndex];
                if (selectedOption && selectedOption.style.display === 'none') {
                    staffSelect.value = '';
                }
            };

            teamSelect.addEventListener('change', filterStaff);
            filterStaff();
        }
    </script>
@endpush