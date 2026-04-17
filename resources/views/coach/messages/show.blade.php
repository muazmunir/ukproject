@extends('layouts.role-dashboard')

@section('role-content')
@php
    $activeRole = strtolower((string) session('active_role', 'client'));
    $isCoachMode = $activeRole === 'coach';

    // timezone for timestamps
    $tz = auth()->user()->timezone ?? config('app.timezone');
@endphp

<div class="container">
    <h3 class="mb-3">{{ __('Messages') }}</h3>

    <div class="msg-layout">

        {{-- Sidebar: conversation list --}}
        <aside class="msg-sidebar">
            @forelse($conversations ?? [] as $conv)
                @php
                    $isActive = isset($conversation) && $conv->id === $conversation->id;

                    // In coach mode, the "other" is usually the client. But keep it generic:
                    $other = $conv->client; // ✅ coach inbox always shows client as other


                    $service = $conv->service;
                @endphp

                <a href="{{ route('coach.messages.show', $conv) }}"
                   class="conv-item {{ $isActive ? 'active' : '' }}">

                    <img src="{{ $other->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($other->full_name ?? 'User') }}"
                         alt=""
                         class="conv-avatar">

                    <div class="conv-text">
                        <div class="conv-name">{{ $other->full_name ?? __('User') }}</div>

                        <div class="conv-meta">
                            @if($service)
                                <span class="conv-service">{{ \Illuminate\Support\Str::limit($service->title, 26) }}</span>
                                <span class="conv-dot">•</span>
                            @endif

                            @if($conv->last_message_at)
                                <span class="conv-time">
                                    {{ $conv->last_message_at->timezone($tz)->diffForHumans() }}
                                </span>
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <div class="p-3 text-muted small text-capitalize">{{ __('No conversations yet.') }}</div>
            @endforelse
        </aside>

        {{-- Main: active conversation --}}
        <section class="msg-main">
            @if(isset($conversation))

                {{-- Header --}}
                <header class="msg-header">
                    @php
                        $me = auth()->user();

                        $other = $me->id === $conversation->coach_id
                            ? $conversation->client
                            : $conversation->coach;
                    @endphp

                    <div class="msg-header-left">
                        <img src="{{ $other->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($other->full_name ?? 'User') }}"
                             alt=""
                             class="msg-header-avatar">

                        <div>
                            <div class="msg-header-name">{{ $other->full_name ?? __('User') }}</div>
                            <div class="msg-header-role">
                                {{ $other->role ? ucfirst($other->role) : '' }}
                            </div>
                        </div>
                    </div>
                </header>

                {{-- Messages (includes service cards in timeline) --}}
                <div class="msg-body" id="msgBody">
                    @foreach($conversation->messages as $msg)
                        @php
                            $isMe = ($msg->sender_id === $me->id);
                            $isServiceBubble = ($msg->body === '__SERVICE_CONTEXT__' && $msg->service);
                        @endphp

                        @if($isServiceBubble)
                            @php $svc = $msg->service; @endphp
                            <div class="msg-row service-row">
                                <a href="{{ route('services.show', $svc->id) }}" class="service-bubble">
                                    <img src="{{ $svc->thumbnail_url }}" alt="" class="service-thumb">

                                    <div class="service-text">
                                        <div class="service-title">
                                            {{ \Illuminate\Support\Str::limit($svc->title, 40) }}
                                        </div>

                                        <div class="service-sub">
                                            @if(!is_null($svc->price_value))
                                                ${{ number_format($svc->price_value, 2) }}
                                                <span class="service-unit">/ {{ $svc->price_unit }}</span>
                                            @else
                                                {{ __('Custom package') }}
                                            @endif
                                        </div>

                                        <div class="service-meta">
                                            {{ __('Client started chat from this service') }}
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @else
                            <div class="msg-row {{ $isMe ? 'me' : 'them' }}">
                                <div class="msg-bubble">
                                    {!! nl2br(e($msg->body)) !!}
                                </div>

                                <div class="msg-time">
                                    {{ $msg->created_at->timezone($tz)->format('d M H:i') }}
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Message Input --}}
                <footer class="msg-footer">
                    <form action="{{ route('coach.messages.store', $conversation) }}" method="POST">
                        @csrf
                        <div class="msg-input-wrap">
                            <div class="msg-input-main">
                                <textarea
                                    name="body"
                                    rows="2"
                                    class="form-control msg-input @error('body') is-invalid @enderror"
                                    required></textarea>

                                @error('body')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-dark msg-send-btn">
                                <i class="bi bi-send-fill me-1"></i>{{ __('Send') }}
                            </button>
                        </div>
                    </form>
                </footer>

            @else
                <div class="d-flex flex-column justify-content-center align-items-center h-100 p-4 text-muted">
                    <p class="mb-1 text-capitalize">{{ __('No conversation selected.') }}</p>
                    <small class="text-capitalize text-center">{{ __('Choose a conversation from the left to start chatting.') }}</small>
                </div>
            @endif
        </section>

    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const body = document.getElementById('msgBody');
        if (body) body.scrollTop = body.scrollHeight;
    })();
</script>
@endpush
