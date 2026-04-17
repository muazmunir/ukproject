@extends('superadmin.layout')
@section('title','DM Audit')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/staff_dm_superadmin.css') }}">
@endpush

@section('content')
@php
  $meId = auth()->id();
  $mgrId = (int) $thread->manager_id;
  $agentId = (int) $thread->agent_id;

  $mgrName = $thread->manager->username ?? $thread->manager->name ?? 'Manager';
  $agentName = $thread->agent->username ?? $thread->agent->name ?? 'Agent';

  $isActive = (bool) $thread->is_active;
@endphp

<div class="dm-shell dm-audit">
  <aside class="dm-side">
    <div class="dm-side-head">
      <div class="dm-h1">DM Audit</div>
      <div class="dm-h2 text-capitalize">All manager-agent chats (active + archived)</div>

      <form class="dm-filters" method="get" action="{{ route('superadmin.dm.index') }}">
        <input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Search manager/agent...">

        <select class="form-select form-select-sm" name="status">
          <option value="" @selected(request('status')==='')>All</option>
          <option value="active" @selected(request('status')==='active')>Active</option>
          <option value="archived" @selected(request('status')==='archived')>Archived</option>
        </select>

        <input class="form-control form-control-sm" name="manager_id" value="{{ request('manager_id') }}" placeholder="Manager ID">
        <input class="form-control form-control-sm" name="agent_id" value="{{ request('agent_id') }}" placeholder="Agent ID">

        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-dark bg-black w-100">Apply</button>
          <a class="btn btn-sm btn-outline-dark w-100" href="{{ route('superadmin.dm.index') }}">Reset</a>
        </div>
      </form>
    </div>

    <div class="dm-side-list" id="dmSideList">
      @foreach($threads as $t)
        @php
          $mName = $t->manager->username ?? $t->manager->name ?? 'Manager';
          $aName = $t->agent->username ?? $t->agent->name ?? 'Agent';
          $pill  = $t->is_active ? 'Active' : 'Archived';
        @endphp

        <a class="dm-item {{ (int)$thread->id === (int)$t->id ? 'active' : '' }}"
           href="{{ route('superadmin.dm.show', $t) . '?' . http_build_query(request()->query()) }}">
          <div class="dm-item-top">
            <div class="dm-item-name">
              <span class="dm-pair">{{ $mName }}</span>
              <span class="dm-arrow">↔</span>
              <span class="dm-pair">{{ $aName }}</span>
            </div>
            <div class="dm-item-time">
              @if($t->last_message_at)
                {{ $t->last_message_at->copy()->timezone(auth()->user()->timezone ?: config('app.timezone'))->format('H:i') }}
              @endif
            </div>
          </div>

          <div class="dm-item-btm">
            <span class="dm-pill {{ $t->is_active ? 'on' : 'off' }}">{{ $pill }}</span>
            <span class="dm-sub text-capitalize">
              @if($t->last_message_at)
                Last message {{ $t->last_message_at->diffForHumans() }}
              @else
                No messages yet
              @endif
            </span>
          </div>
        </a>
      @endforeach

      <div class="p-2">
        {{ $threads->links() }}
      </div>
    </div>
  </aside>

  <main class="dm-main">
    <div class="dm-topbar">
      <div class="dm-top-left">
        <div class="dm-title">
          {{ $mgrName }} <span class="dm-arrow">↔</span> {{ $agentName }}
        </div>
        <div class="dm-subtitle text-capitalize">
          Superadmin audit view — read-only.
          @if($isActive) Active thread. @else Archived thread. @endif
        </div>
      </div>

      <div class="dm-top-right">
        <span class="dm-badge">{{ $isActive ? 'Active' : 'Archived' }}</span>
      </div>
    </div>

    <div id="dmMessages" class="dm-messages">
      @foreach($messages as $m)
        @php
          $isManagerMsg = (int)$m->sender_id === $mgrId;
          $bubbleClass = $isManagerMsg ? 'bubble-manager' : 'bubble-agent';
          $senderLabel = $isManagerMsg ? 'Manager' : 'Agent';
        @endphp

        <div class="dm-row {{ $isManagerMsg ? 'left' : 'right' }}">
          <div class="dm-meta">
            <span class="dm-who">{{ $senderLabel }}</span>
            <span class="dm-when">
              {{ optional($m->created_at)->timezone(auth()->user()->timezone ?: config('app.timezone'))->format('d M, H:i') }}
            </span>
          </div>

          <div class="dm-bubble {{ $bubbleClass }}">
            {!! nl2br(e($m->body)) !!}
          </div>
        </div>
      @endforeach
    </div>

    <div class="dm-audit-footer text-muted small text-capitalize">
      Superadmin can view all messages. Sending is disabled for audit integrity.
    </div>
  </main>
</div>
@endsection
