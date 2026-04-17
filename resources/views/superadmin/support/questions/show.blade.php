@extends('superadmin.layout')
@section('title','Q&A View')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-support-qa.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/superadmin-support-qa-view.css') }}">

@endpush

@section('content')
@php
    $tz = auth()->user()->timezone ?? config('app.timezone', 'UTC');

    $fmtUser = function ($u) {
        if (!$u) return '—';

        $name = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
        $username = $u->username ?? null;

        if ($name && $username) {
            return $name.' <span class="text-muted">@'.$username.'</span>';
        }

        return $name ?: ($username ? '@'.$username : ($u->email ?? '—'));
    };

    $statusLabel = match($question->status) {
        'open' => 'Unresolved',
        'taken' => 'Pending',
        'closed', 'answered' => 'Resolved',
        default => ucfirst($question->status),
    };

    $statusClass = match($question->status) {
        'open' => 'warn',
        'taken' => 'muted',
        'closed', 'answered' => 'ok',
        default => 'muted',
    };

    $raisedAt   = $question->created_at ? $question->created_at->timezone($tz)->format('Y-m-d H:i') : '—';
    $takenAt    = $question->taken_at ? \Carbon\Carbon::parse($question->taken_at)->timezone($tz)->format('Y-m-d H:i') : '—';
    $answeredAt = $question->answered_at ? \Carbon\Carbon::parse($question->answered_at)->timezone($tz)->format('Y-m-d H:i') : '—';
@endphp

<div class="qa-wrap qa-view-page">
    <div class="qa-card qa-view-card">
 <div class="qa-head__right p-3">
                <a class="qa-btn ghost" href="{{ route('superadmin.support.questions.index') }}">Back</a>
            </div>
        <div class="qa-head qa-view-head ">
            <div class="qa-head__left">
                <h1 class="qa-title">Question #{{ $question->id }}</h1>
                <div class="qa-sub">View only</div>
            </div>

           
        </div>

        <div class="qa-view-body">
            <div class="qa-overview">
                <div class="qa-overview__label">Question</div>
                <div class="qa-overview__question">
                    {{ $question->question }}
                </div>
            </div>

            <div class="qa-meta-grid">
                <div class="qa-meta-card">
                    <div class="qa-meta-label">Asked By</div>
                    <div class="qa-meta-value">{!! $fmtUser($question->askedBy) !!}</div>
                </div>

                <div class="qa-meta-card">
                    <div class="qa-meta-label">Manager</div>
                    <div class="qa-meta-value">{!! $fmtUser($question->assignedManager) !!}</div>
                </div>

                <div class="qa-meta-card">
                    <div class="qa-meta-label">Status</div>
                    <div class="qa-meta-value">
                        <span class="qa-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>
                </div>
            </div>

            <div class="qa-time-grid">
                <div class="qa-time-card">
                    <div class="qa-meta-label">Raised At</div>
                    <div class="qa-meta-value">{{ $raisedAt }}</div>
                </div>

                <div class="qa-time-card">
                    <div class="qa-meta-label">Pending At</div>
                    <div class="qa-meta-value">{{ $takenAt }}</div>
                </div>

                <div class="qa-time-card">
                    <div class="qa-meta-label">Resolved At</div>
                    <div class="qa-meta-value">{{ $answeredAt }}</div>
                </div>
            </div>

            <div class="qa-thread-card">
                <div class="qa-thread-head">
                    <div class="qa-thread-title">Thread</div>
                </div>

                <div class="qa-thread">
                    @forelse($question->messages->sortBy('created_at') as $m)
                        @php
                            $senderLabel = ($m->type ?? '') === 'system'
                                ? 'System'
                                : ($m->sender ? trim(($m->sender->first_name ?? '').' '.($m->sender->last_name ?? '')) : '—');

                            $senderUsername = ($m->type ?? '') === 'system' ? null : ($m->sender->username ?? null);
                            $senderText = $senderLabel;

                            if ($senderUsername) {
                                $senderText .= ' @'.$senderUsername;
                            }

                            $msgAt = $m->created_at ? $m->created_at->timezone($tz)->format('Y-m-d H:i') : '—';

                            $msgClass =
                                ($m->type ?? '') === 'system'
                                    ? 'qa-msg-system'
                                    : ($m->sender_role === 'manager'
                                        ? 'qa-msg-manager'
                                        : 'qa-msg-admin');
                        @endphp

                        <div class="qa-msg {{ $msgClass }}">
                            <div class="qa-msg-meta">
                                {{ $senderText }} · {{ $msgAt }}
                            </div>
                            <div class="qa-msg-body">{{ $m->body }}</div>
                        </div>
                    @empty
                        <div class="qa-thread-empty">No messages</div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>
</div>
@endsection