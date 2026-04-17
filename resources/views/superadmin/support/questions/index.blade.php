@extends('superadmin.layout')
@section('title','Support Q&A Analytics')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-support-qa.css') }}">

@endpush

@section('content')
@php
    $tz = auth()->user()->timezone ?? config('app.timezone', 'UTC');

    $fmtUser = function ($u) {
        if (!$u) return '—';

        $name = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
        $username = $u->username ?? null;

        if ($name && $username) {
            return $name.'<br><span class="text-muted">@'.$username.'</span>';
        }

        return $name ?: ($username ? '@'.$username : ($u->email ?? '—'));
    };

    $mapStatus = function ($status) {
        return match($status) {
            'open' => 'Unresolved',
            'taken' => 'Pending',
            'closed', 'answered' => 'Resolved',
            default => ucfirst($status),
        };
    };

    $mapBadgeClass = function ($status) {
        return match($status) {
            'open' => 'warn',
            'taken' => 'muted',
            'closed', 'answered' => 'ok',
            default => 'muted',
        };
    };
@endphp

<div class="qa-wrap">
    <div class="qa-card">

        <div class="qa-head">
            <div class="qa-head__content">
                <h1 class="qa-title">Support Q&A — Analytics</h1>
                <div class="qa-sub text-capitalize">View-only. Monitor how agents raise questions and managers resolve them.</div>
            </div>
        </div>

        <div class="qa-section">
            <div class="qa-analytics">
                <div class="qa-cardx">
                    <div class="k">Total Questions</div>
                    <div class="v">{{ $analytics['total'] }}</div>
                </div>

                <div class="qa-cardx">
                    <div class="k">Unresolved</div>
                    <div class="v">{{ $analytics['open'] }}</div>
                </div>

                <div class="qa-cardx">
                    <div class="k">Pending</div>
                    <div class="v">{{ $analytics['taken'] }}</div>
                </div>

                <div class="qa-cardx">
                    <div class="k">Resolved</div>
                    <div class="v">{{ $analytics['answered'] }}</div>
                </div>
            </div>

            <div class="qa-mini">
                <div class="qa-cardx">
                    <div class="k qa-cardx__title">Top Agents (Raised)</div>
                    <table class="qa-mini-table">
                        @forelse($topAdmins as $r)
                            @php $u = $r->askedBy; @endphp
                            <tr>
                                <td>{!! $fmtUser($u) !!}</td>
                                <td>{{ $r->cnt }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-muted">No Data</td>
                                <td>—</td>
                            </tr>
                        @endforelse
                    </table>
                </div>

                <div class="qa-cardx">
                    <div class="k qa-cardx__title">Top Managers (Resolved)</div>
                    <table class="qa-mini-table">
                        @forelse($topManagers as $r)
                            @php $m = $r->assignedManager; @endphp
                            <tr>
                                <td>{!! $fmtUser($m) !!}</td>
                                <td>{{ $r->cnt }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-muted">No data</td>
                                <td>—</td>
                            </tr>
                        @endforelse
                    </table>
                </div>
            </div>

            <form method="get" class="qa-filters">
    <div class="qa-filters__row">
        <input class="qa-input" name="q" value="{{ $q }}" placeholder="Search #ID or question…">

        <select class="qa-select" name="status">
            <option value="">All Statuses</option>
            <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Unresolved</option>
            <option value="taken" {{ request('status') === 'taken' ? 'selected' : '' }}>Pending</option>
            <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Resolved</option>
        </select>

        <select class="qa-select" name="filter_mode" id="filterMode">
            <option value="lifetime" {{ $filterMode==='lifetime'?'selected':'' }}>Lifetime</option>
            <option value="daily" {{ $filterMode==='daily'?'selected':'' }}>Daily</option>
            <option value="weekly" {{ $filterMode==='weekly'?'selected':'' }}>Weekly</option>
            <option value="monthly" {{ $filterMode==='monthly'?'selected':'' }}>Monthly</option>
            <option value="yearly" {{ $filterMode==='yearly'?'selected':'' }}>Yearly</option>
            <option value="custom" {{ $filterMode==='custom'?'selected':'' }}>Custom Range</option>
        </select>

        <input class="qa-input qa-mode qa-mode--daily" type="date" name="day" value="{{ $day }}">
        <input class="qa-input qa-mode qa-mode--weekly" type="date" name="week_day" value="{{ $weekDay }}">
        <input class="qa-input qa-mode qa-mode--monthly" type="month" name="month" value="{{ $month }}">

        <select class="qa-select qa-mode qa-mode--yearly" name="year">
            <option value="">Select year</option>
            @php $curY = (int)now()->format('Y'); @endphp
            @for($y = $curY; $y >= ($curY - 10); $y--)
                <option value="{{ $y }}" {{ (string)$year === (string)$y ? 'selected' : '' }}>
                    {{ $y }}
                </option>
            @endfor
        </select>

        <input class="qa-input qa-mode qa-mode--custom" type="date" name="date_from" value="{{ $dateFrom }}">
        <input class="qa-input qa-mode qa-mode--custom" type="date" name="date_to" value="{{ $dateTo }}">

        <select class="qa-select" name="per">
            @foreach([15,30,50,100] as $n)
                <option value="{{ $n }}" {{ (int)$per === $n ? 'selected' : '' }}>{{ $n }}/page</option>
            @endforeach
        </select>

        <button class="qa-btn" type="submit">Apply</button>
        <a class="qa-btn ghost" href="{{ route('superadmin.support.questions.index') }}">Reset</a>
    </div>
</form>
        </div>

        <div class="qa-section qa-section--table">
            <div class="qa-table-wrap">
                <table class="qa-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Question</th>
                            <th>Asked By</th>
                            <th>Manager</th>
                            <th>Status</th>
                            <th>Raised</th>
                            <th>Pending</th>
                            <th>Resolved</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($items as $it)
                            @php
                                $statusLabel = $mapStatus($it->status);
                                $badgeClass = $mapBadgeClass($it->status);

                                $raisedAt   = $it->created_at ? $it->created_at->timezone($tz)->format('Y-m-d H:i') : '—';
                                $pendingAt  = $it->taken_at ? \Carbon\Carbon::parse($it->taken_at)->timezone($tz)->format('Y-m-d H:i') : '—';
                                $resolvedAt = $it->answered_at ? \Carbon\Carbon::parse($it->answered_at)->timezone($tz)->format('Y-m-d H:i') : '—';
                            @endphp

                            <tr>
                                <td>
                                    <span class="qa-id">#{{ $it->id }}</span>
                                </td>

                                <td class="qa-question-cell">
                                    <div class="qa-question-text">{{ $it->question }}</div>
                                </td>

                                <td>{!! $fmtUser($it->askedBy) !!}</td>
                                <td>{!! $fmtUser($it->assignedManager) !!}</td>

                                <td>
                                    <span class="qa-badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                                </td>

                                <td>{{ $raisedAt }}</td>
                                <td>{{ $pendingAt }}</td>
                                <td>{{ $resolvedAt }}</td>

                                <td class="text-center">
                                    <a class="qa-btn ghost qa-btn-sm"
                                       href="{{ route('superadmin.support.questions.show', $it) }}">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="qa-empty">No Questions Found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="qa-pagination">
                {{ $items->links() }}
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const mode = document.getElementById('filterMode');

    const update = () => {
        const v = mode?.value || 'lifetime';
        document.querySelectorAll('.qa-mode').forEach(el => {
            el.style.display = 'none';
        });
        document.querySelectorAll('.qa-mode--' + v).forEach(el => {
            el.style.display = '';
        });
    };

    mode?.addEventListener('change', update);
    update();
})();
</script>
@endpush