@extends('layouts.admin')

@section('title','Message Manager')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/staff_dm.css') }}">
@endpush

@section('content')
@php
  $meId = auth()->id();
  $lastId = (int) optional($messages->last())->id;
@endphp

<div class="dm-shell">
  <aside class="dm-side">
    <div class="dm-side-head">

       <div class="dm-filters mt-2">
  <button type="button" class="dm-filter-btn" id="filterActive">Active</button>
  <button type="button" class="dm-filter-btn is-active" id="filterAll">All</button>
</div>


      <div class="dm-h1">Direct Messages</div>
      <div class="dm-h2 text-capitalize">Your managers (current + archived)</div>
    </div>

    <div class="dm-side-list" id="dmSideList">
      @foreach($threads as $t)
        @php
  $name = $t->manager->username ?? $t->manager->name ?? 'Manager';
  $isActive = (bool)$t->is_active;
  $pill = $isActive ? 'Active' : 'Archived';
  $unread = (int) ($t->unread_count ?? 0);
@endphp

<a class="dm-item {{ $thread->id === $t->id ? 'active' : '' }} {{ $unread ? 'unread' : '' }}"
   data-active="{{ $isActive ? 1 : 0 }}"
   data-thread-id="{{ $t->id }}"
   href="{{ route('admin.dm.agent.show', $t) }}">
  <div class="dm-item-top">
    <div class="dm-item-name">
      {{ $name }}
      @if($unread)
        <span class="dm-unread-badge">{{ $unread }}</span>
      @endif
    </div>
    <div class="dm-item-time">
      @if($t->last_message_at)
        {{ $t->last_message_at->copy()->timezone(auth()->user()->timezone ?: config('app.timezone'))->format('H:i') }}
      @endif
    </div>
  </div>

  <div class="dm-item-btm">
    <span class="dm-pill {{ $isActive ? 'on' : 'off' }}">{{ $pill }}</span>
    <span class="dm-sub text-capitalize">
      @if($t->last_message_at)
        Last Message {{ $t->last_message_at->diffForHumans() }}
      @else
        No Messages Yet
      @endif
    </span>
  </div>
</a>

      @endforeach
    </div>
  </aside>

  <main class="dm-main">
    <div class="dm-topbar">
      <div class="dm-top-left">
        <div class="dm-title">
          {{ $thread->manager->username ?? $thread->manager->name ?? 'Manager' }}
        </div>
        <div class="dm-subtitle text-capitalize">
          @if($canSend)
            You can message your current manager.
          @else
            Archived thread. You were reassigned — read-only.
          @endif
        </div>
      </div>

      <div class="dm-top-right">
        <span class="dm-badge">
          {{ $thread->manager->role_label ?? 'Manager' }}
        </span>
      </div>
    </div>

    @if(!$canSend)
      <div class="dm-banner text-capitalize">
        <i class="bi bi-lock"></i>
        This conversation is archived because you are no longer assigned to this manager.
      </div>
    @endif

    <div id="dmMessages" class="dm-messages">
      @foreach($messages as $m)
        @include('shared.dm.partials.message', ['m'=>$m,'meId'=>$meId])
      @endforeach
    </div>

    <form id="dmForm" class="dm-inputbar" autocomplete="off"
          @if(!$canSend) style="opacity:.55;pointer-events:none" @endif>
      @csrf
      <textarea id="dmBody" name="body" rows="1"
                placeholder="{{ $canSend ? 'Type a message…' : 'Read-only' }}"
                maxlength="5000"></textarea>
      <button type="submit" class="dm-send">
        <i class="bi bi-send-fill"></i>
      </button>
    </form>
  </main>
</div>

@push('scripts')

<script>
(function(){
  const list   = document.getElementById('dmSideList');
  const btnA   = document.getElementById('filterActive');
  const btnAll = document.getElementById('filterAll');
  const btns   = [btnA, btnAll];

  function setMode(mode){
    const items = list?.querySelectorAll('.dm-item') || [];

    items.forEach(a => {
      const isActive = a.dataset.active === '1';
      a.style.display = (mode === 'active' && !isActive) ? 'none' : '';
    });

    // toggle active state via CSS class
    btns.forEach(b => b?.classList.remove('is-active'));
    (mode === 'active' ? btnA : btnAll)?.classList.add('is-active');
  }

  btnA?.addEventListener('click', () => setMode('active'));
  btnAll?.addEventListener('click', () => setMode('all'));

  // default: ALL
  setMode('all');
})();
</script>

<script>


(function(){
  const sendUrl   = @json(route('admin.dm.agent.send', $thread));
  const latestUrl = @json(route('admin.dm.agent.latest', $thread));
  let lastId      = {{ (int) $lastId }};

  const box  = document.getElementById('dmMessages');
  const form = document.getElementById('dmForm');
  const ta   = document.getElementById('dmBody');

  function scrollBottom(){ box.scrollTop = box.scrollHeight; }
  function autoGrow(el){ el.style.height='auto'; el.style.height = el.scrollHeight+'px'; }

  autoGrow(ta);
  ta.addEventListener('input', () => autoGrow(ta));

  ta.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      form?.requestSubmit();
    }
  });

  scrollBottom();

  async function poll(){
    try{
      const r = await fetch(latestUrl + '?after_id=' + lastId, {
        headers:{'X-Requested-With':'XMLHttpRequest'}
      });
      const data = await r.json();
      if(data.ok && data.html){
        box.insertAdjacentHTML('beforeend', data.html);
        lastId = data.last_id || lastId;
        scrollBottom();
      }
    }catch(e){}
  }
  setInterval(poll, 2500);

  form?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const body = (ta.value || '').trim();
    if(!body) return;

    ta.value = '';
    autoGrow(ta);

    const fd = new FormData(form);
    fd.set('body', body);

    try{
      const r = await fetch(sendUrl, {
        method:'POST',
        headers:{'X-Requested-With':'XMLHttpRequest'},
        body: fd
      });
      const data = await r.json();
      if(data.ok && data.html){
        box.insertAdjacentHTML('beforeend', data.html);
        lastId = Math.max(lastId, data.id || lastId);
        scrollBottom();
      }
    }catch(e){}
  });
})();
</script>
@endpush
@endsection
