@extends('layouts.admin')
@section('title','Support Q&A')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-support-qa.css') }}">
@endpush

@section('content')
@php
    $staff = auth()->user();
    $isAdmin = in_array($staff->role, ['admin','superadmin'], true);
    $isManager = in_array($staff->role, ['manager','superadmin'], true);
    $tz = $staff->timezone ?? config('app.timezone', 'UTC');

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

    $fmtUser = function ($u) {
        if (!$u) return '—';

        $name = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
        $username = $u->username ?? null;

        if ($name && $username) {
            return $name.'<br><span class="text-muted">@'.$username.'</span>';
        }

        return $name ?: ($username ? '@'.$username : ($u->email ?? '—'));
    };

    $mineLabel = $isManager && !$isAdmin ? 'Taken By Me' : 'My Questions';

    $limitWords = function ($text, $words = 10) {
        $text = trim(strip_tags((string) $text));

        if ($text === '') {
            return '—';
        }

        $parts = preg_split('/\s+/', $text);

        if (!$parts || count($parts) <= $words) {
            return $text;
        }

        return implode(' ', array_slice($parts, 0, $words)) . '...';
    };
@endphp

<div class="qa-wrap">
    <div class="qa-card">

        <div class="qa-head">
            <div class="qa-head__content">
                <h1 class="qa-title">Support Q&A</h1>
                <div class="qa-sub text-capitalize">
                    View-only. Monitor how agents raise questions and managers resolve them.
                </div>
            </div>

            @if($isAdmin)
                <a class="qa-btn ghost" href="{{ route('admin.support.questions.create') }}">
                    + Ask Question
                </a>
            @endif
        </div>

        <div class="qa-section">
            <form method="get" class="qa-filters">
                <div class="qa-filters__row">

                    <input class="qa-input"
                           name="q"
                           value="{{ $q }}"
                           placeholder="Search #ID or question…">

                    <select class="qa-select" name="status">
                        <option value="all" {{ $status==='all'?'selected':'' }}>All Statuses</option>
                        <option value="open" {{ $status==='open'?'selected':'' }}>Unresolved</option>
                        <option value="taken" {{ $status==='taken'?'selected':'' }}>Pending</option>
                        <option value="answered" {{ $status==='answered'?'selected':'' }}>Resolved</option>
                    </select>

                    <select class="qa-select" name="mine">
                        <option value="0" {{ $mine==='0'?'selected':'' }}>All</option>
                        <option value="1" {{ $mine==='1'?'selected':'' }}>
                            {{ $mineLabel }}
                        </option>
                    </select>

                    <button class="qa-btn" type="submit">Apply</button>
                    <a class="qa-btn ghost" href="{{ route('admin.support.questions.index') }}">Reset</a>
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

                                $raisedAt = $it->created_at
                                    ? $it->created_at->timezone($tz)->format('Y-m-d H:i')
                                    : '—';

                                $pendingAt = $it->taken_at
                                    ? \Carbon\Carbon::parse($it->taken_at)->timezone($tz)->format('Y-m-d H:i')
                                    : '—';

                                $resolvedAt = $it->answered_at
                                    ? \Carbon\Carbon::parse($it->answered_at)->timezone($tz)->format('Y-m-d H:i')
                                    : '—';

                                $latestAnswer = null;
                                if ($it->status === 'closed') {
                                    $latestAnswer = $it->messages
                                        ->where('sender_role','manager')
                                        ->where('type','message')
                                        ->sortByDesc('created_at')
                                        ->first();
                                }

                                $fullQuestion = trim((string) $it->question);
                                $shortQuestion = $limitWords($fullQuestion, 10);

                                $fullAnswer = $latestAnswer?->body ? trim((string) $latestAnswer->body) : '';
                                $shortAnswer = $fullAnswer !== '' ? $limitWords($fullAnswer, 10) : '';
                            @endphp

                            <tr>
                                <td>
                                    <span class="qa-id">#{{ $it->id }}</span>
                                </td>

                                <td class="qa-question-cell">
                                    <div class="qa-question-text"
                                         title="{{ $fullQuestion }}">
                                        {{ $shortQuestion }}
                                    </div>

                                    
                                </td>

                                <td>{!! $fmtUser($it->askedBy) !!}</td>
                                <td>{!! $fmtUser($it->assignedManager) !!}</td>

                                <td>
                                    <span class="qa-badge {{ $badgeClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>

                                <td>{{ $raisedAt }}</td>
                                <td>{{ $pendingAt }}</td>
                                <td>{{ $resolvedAt }}</td>

                                <td class="text-center">
                                    <a class="qa-btn ghost qa-btn-sm"
                                       href="{{ route('admin.support.questions.show', $it) }}">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="qa-empty text-capitalize">No questions found.</td>
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