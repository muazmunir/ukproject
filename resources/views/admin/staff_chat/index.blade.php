@extends('layouts.admin')

@section('title','Staff Chat')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/staff_chat.css') }}">
@endpush

@section('content')
@php
  $meId = auth()->id();
  $room = $openRoom; // for consistency with partial
   $tz = auth()->user()->timezone ?? config('app.timezone', 'UTC');
@endphp

<div class="sc-wrap">
  <div class="sc-card">

    {{-- LEFT SIDEBAR --}}
    <aside class="sc-side">
      <div class="sc-side-head">
        <div>
          <div class="sc-title">Staff Chat</div>
          <div class="sc-sub">Groups + Direct</div>
        </div>

        <button class="btn btn-dark bg-black btn-sm sc-newdm" data-bs-toggle="modal" data-bs-target="#startDmModal">
          <i class="bi bi-chat-dots"></i> New DM
        </button>
      </div>

      {{-- SIDEBAR SEARCH --}}
<div class="px-3 pt-2">
  <div class="input-group input-group-sm">
    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
    <input type="text" class="form-control" id="sidebarChatSearch" placeholder="Search Chats…">
  </div>
</div>


      {{-- GROUPS --}}
      <div class="sc-section">
        <div class="sc-section-title">Groups</div>

        <div class="sc-list">
          @foreach($rooms as $r)
            @php
              $active = $room && $room->id === $r->id;
              $lm = $r->latestMessage;
           $time = $r->last_message_at 
  ? \Carbon\Carbon::parse($r->last_message_at)
      ->timezone($tz)
      ->format('d M Y, h:i A') 
  : '';
              $preview = $lm?->body ? \Illuminate\Support\Str::limit($lm->body, 42) : 'No messages yet';
            @endphp

            <a class="sc-item {{ $active ? 'active' : '' }}"
               href="{{ route('admin.staff_chat.show', $r->id) }}"
               data-room-id="{{ $r->id }}">
              <div class="sc-item-row">
                <div class="sc-item-title">
                  <span class="sc-pill sc-pill-group">
                    <i class="bi bi-people"></i>
                  </span>
                  <span class="text-truncate">{{ $r->name ?? 'Group' }}</span>
                </div>

                <div class="sc-item-right">
                  <span class="sc-time">{{ $time }}</span>
                  <span class="sc-badge unread-badge" data-unread-for="{{ $r->id }}" style="display:none">0</span>
                </div>
              </div>

              <div class="sc-item-sub text-truncate">
                {{ $preview }}
              </div>
            </a>
          @endforeach
        </div>
      </div>

      {{-- DIRECT MESSAGES --}}
      <div class="sc-section">
        <div class="sc-section-title">Direct Messages</div>

        <div class="sc-list">
          @forelse($dmRooms as $r)
            @php
              $active = $room && $room->id === $r->id;
              $other = $r->users->firstWhere('id','!=',$meId);
              $otherName = $other->username ?? $other->name ?? 'User';
              $otherRole = strtolower((string)($other->role_label ?? ''));
              $lm = $r->latestMessage;
          $time = $r->last_message_at 
  ? \Carbon\Carbon::parse($r->last_message_at)
      ->timezone($tz)
      ->format('d M Y, h:i A') 
  : '';
              $preview = $lm?->body ? \Illuminate\Support\Str::limit($lm->body, 42) : 'No messages yet';
            @endphp

            <a class="sc-item {{ $active ? 'active' : '' }}"
               href="{{ route('admin.staff_chat.show', $r->id) }}"
               data-room-id="{{ $r->id }}">
              <div class="sc-item-row">
                <div class="sc-item-title">
                  <span class="sc-pill sc-pill-dm">
                    <i class="bi bi-person"></i>
                  </span>
                  <span class="text-truncate">
                    {{ $otherName }}
                    <span class="sc-role-tag role-{{ $otherRole }}">{{ $otherRole }}</span>
                  </span>
                </div>

                <div class="sc-item-right">
                  <span class="sc-time">{{ $time }}</span>
                  <span class="sc-badge unread-badge" data-unread-for="{{ $r->id }}" style="display:none">0</span>
                </div>
              </div>

              <div class="sc-item-sub text-truncate">
                {{ $preview }}
              </div>
            </a>
          @empty
            <div class="sc-empty">No DMs yet. Start a new one.</div>
          @endforelse
        </div>
      </div>
    </aside>

    {{-- RIGHT PANEL --}}
    <main class="sc-main">
      @if($room)
        <div class="sc-main-head">
          <div class="sc-room-title">
            @if($room->room_type === 'dm')
              @php
                $other = $room->users->firstWhere('id','!=',$meId);
                $otherName = $other->username ?? $other->name ?? 'User';
                $otherRole = strtoupper((string)($other->role ?? ''));
              @endphp
              <div class="h5 mb-0">{{ $otherName }}</div>
              <div class="small text-muted">
                Direct • <span class="text-capitalize">{{ $otherRole }}</span>
              </div>
            @else
              <div class="h5 mb-0">{{ $room->name ?? 'Group' }}</div>
              <div class="small text-muted">
                {{ $room->room_type === 'all_staff' ? 'All Staff' : 'Team Group' }}
              </div>
            @endif
          </div>

          <div class="sc-main-actions">
            <button class="btn btn-outline-dark btn-sm" id="btnMarkRead">
              <i class="bi bi-check2-all"></i> Mark Read
            </button>
          </div>
        </div>

        {{-- MESSAGES --}}
        <div class="sc-messages" id="scMessages">
          @include('admin.staff_chat._messages', ['room' => $room, 'meId' => $meId])
        </div>

        {{-- COMPOSER --}}
       {{-- COMPOSER --}}
<form class="sc-composer" id="sendForm"
      action="{{ route('admin.staff_chat.send', $room->id) }}"
      method="POST" enctype="multipart/form-data">
  @csrf

  <div class="sc-composer-row">
    <textarea name="body" class="form-control sc-input" rows="1"
              placeholder="Type a message…"></textarea>

    <label class="btn btn-light sc-attach" title="Attach files">
      <i class="bi bi-paperclip"></i>
      <input type="file" name="attachments[]" multiple hidden id="scAttachInput">
    </label>

    <button class="btn btn-success sc-send bg-black" type="submit">
      <i class="bi bi-send-fill"></i>
    </button>
  </div>

  {{-- Selected files preview --}}
  <div class="sc-picked" id="scPicked" style="display:none;">
    <div class="sc-picked-head">
      <div class="sc-picked-title">Selected files</div>
      <button type="button" class="sc-picked-clear" id="scPickedClear">Clear all</button>
    </div>

    <div class="sc-picked-list" id="scPickedList"></div>
  </div>

  <div class="sc-hint">
    Images, PDFs, docs etc. Multiple attachments supported.
  </div>
</form>

      @else
        <div class="p-4">
          <div class="alert alert-warning mb-0">No rooms available.</div>
        </div>
      @endif
    </main>
  </div>
</div>

{{-- START DM MODAL --}}
<div class="modal fade" id="startDmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Start Direct Message</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

  {{-- MODAL SEARCH --}}
  <div class="mb-2">
    <div class="input-group input-group-sm">
      <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
      <input type="text" class="form-control" id="dmUserSearch" placeholder="Search user…">
    </div>
  </div>

  {{-- MODAL FILTERS --}}
  <div class="d-flex gap-2 mb-3 flex-wrap">
    <button type="button" class="btn btn-outline-dark btn-sm dm-scope-btn active" data-scope="all">
      All
    </button>

    @if(strtolower((string)auth()->user()->role) === 'admin')
      <button type="button" class="btn btn-outline-dark btn-sm dm-scope-btn" data-scope="my_manager">
        My Manager
      </button>
    @endif

    @if(strtolower((string)auth()->user()->role) === 'manager')
      <button type="button" class="btn btn-outline-dark btn-sm dm-scope-btn" data-scope="my_team">
        My Team
      </button>
    @endif
  </div>

  {{-- USERS LIST --}}
  <div class="sc-userlist" id="dmUserList">
    @forelse($users as $u)
      @php
        $role = strtolower((string)($u->role ?? $u->role_label ?? ''));
        $cls = 'user-pill';
        $isTeam = !empty($u->is_my_team);
        $isMgr  = !empty($u->is_my_manager);
        if($isTeam) $cls .= ' team';
        if($isMgr)  $cls .= ' manager';

        $label = $u->username ?? $u->name ?? 'User';
      @endphp

      <button type="button"
              class="{{ $cls }} dm-user-row"
              data-user-id="{{ $u->id }}"
              data-name="{{ strtolower($label) }}"
              data-role="{{ $role }}"
              data-myteam="{{ $isTeam ? 1 : 0 }}"
              data-mymanager="{{ $isMgr ? 1 : 0 }}">
        <div class="d-flex justify-content-between align-items-center w-100">
          <div class="text-truncate">
            <div class="fw-semibold">{{ $label }}</div>
            <div class="small text-muted text-capitalize">Role: {{ $role }}</div>
          </div>
          <i class="bi bi-chevron-right"></i>
        </div>
      </button>
    @empty
      <div class="text-muted">No users available for DM.</div>
    @endforelse
  </div>

</div>


      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function(){
  const csrf = @json(csrf_token());

  const openRoomId = @json($room?->id);
  const unreadsUrl = @json(route('admin.staff_chat.unreads'));
  const markReadUrl = openRoomId ? @json(route('admin.staff_chat.read', $room->id)) : null;
  const dmStartUrl  = @json(route('admin.staff_chat.dm.start'));


  // ===== Attachments picker (preview + remove) =====
  const fileInput = document.getElementById('scAttachInput');
  const pickedBox = document.getElementById('scPicked');
  const pickedList = document.getElementById('scPickedList');
  const pickedClear = document.getElementById('scPickedClear');

  let pickedFiles = []; // our own list, since input.files is read-only

  function humanSize(bytes){
    if(!bytes) return '0 B';
    const units = ['B','KB','MB','GB'];
    let i = 0;
    let n = bytes;
    while(n >= 1024 && i < units.length-1){ n /= 1024; i++; }
    return `${n.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
  }

  function isImage(file){
    return file && file.type && file.type.startsWith('image/');
  }

  function iconFor(file){
    if(isImage(file)) return 'bi-image';
    const t = (file.type || '').toLowerCase();
    if(t.includes('pdf')) return 'bi-filetype-pdf';
    if(t.includes('word')) return 'bi-filetype-doc';
    if(t.includes('excel') || t.includes('spreadsheet')) return 'bi-filetype-xls';
    if(t.includes('zip')) return 'bi-file-zip';
    return 'bi-file-earmark';
  }

  function syncInputFiles(){
    const dt = new DataTransfer();
    pickedFiles.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
  }

  function renderPicked(){
    if(!pickedFiles.length){
      pickedBox.style.display = 'none';
      pickedList.innerHTML = '';
      return;
    }

    pickedBox.style.display = 'block';
    pickedList.innerHTML = pickedFiles.map((f, idx) => {
      return `
        <div class="sc-picked-item" data-idx="${idx}">
          <span class="sc-picked-ic"><i class="bi ${iconFor(f)}"></i></span>
          <div class="sc-picked-name" title="${f.name}">${f.name}</div>
          <div class="sc-picked-meta">${humanSize(f.size)}</div>
          <button type="button" class="sc-picked-del" title="Remove">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      `;
    }).join('');

    // bind remove buttons
    pickedList.querySelectorAll('.sc-picked-item').forEach(row => {
      const btn = row.querySelector('.sc-picked-del');
      btn.addEventListener('click', () => {
        const idx = parseInt(row.getAttribute('data-idx'), 10);
        pickedFiles.splice(idx, 1);
        syncInputFiles();
        renderPicked();
      });
    });
  }

  if(fileInput){
    fileInput.addEventListener('change', () => {
      // Add newly selected files to our list (keep existing)
      const incoming = Array.from(fileInput.files || []);

      // avoid duplicates by (name + size + lastModified)
      const key = (f) => `${f.name}|${f.size}|${f.lastModified}`;
      const existing = new Set(pickedFiles.map(key));

      incoming.forEach(f => {
        if(!existing.has(key(f))){
          pickedFiles.push(f);
          existing.add(key(f));
        }
      });

      syncInputFiles();
      renderPicked();
    });
  }

  if(pickedClear){
    pickedClear.addEventListener('click', () => {
      pickedFiles = [];
      syncInputFiles();
      renderPicked();
    });
  }

  function updateUnreadBadges(map){
    document.querySelectorAll('[data-unread-for]').forEach(el => {
      const rid = el.getAttribute('data-unread-for');
      const n = map && map[rid] ? parseInt(map[rid],10) : 0;
      if(n > 0){
        el.textContent = n;
        el.style.display = 'inline-flex';
      } else {
        el.style.display = 'none';
      }
    });
  }



    // =========================
  // Sidebar chat search
  // =========================
  const sidebarSearch = document.getElementById('sidebarChatSearch');
  if (sidebarSearch) {
    sidebarSearch.addEventListener('input', function(){
      const q = (this.value || '').toLowerCase().trim();

      document.querySelectorAll('.sc-side .sc-item').forEach(a => {
        const text = (a.innerText || '').toLowerCase();
        a.style.display = !q || text.includes(q) ? '' : 'none';
      });
    });
  }

  // =========================
  // Start DM modal: scope + search
  // =========================
  const dmSearch = document.getElementById('dmUserSearch');
  const dmList   = document.getElementById('dmUserList');
  let dmScope = 'all';

  function applyDmFilters(){
    if(!dmList) return;

    const q = (dmSearch?.value || '').toLowerCase().trim();

    dmList.querySelectorAll('.dm-user-row').forEach(row => {
      const name = row.getAttribute('data-name') || '';
      const role = row.getAttribute('data-role') || '';
      const isTeam = row.getAttribute('data-myteam') === '1';
      const isMgr  = row.getAttribute('data-mymanager') === '1';

      // scope match
      let okScope = true;
      if(dmScope === 'my_team') okScope = isTeam;
      if(dmScope === 'my_manager') okScope = isMgr;

      // text match
      const hay = (name + ' ' + role).toLowerCase();
      const okText = !q || hay.includes(q);

      row.style.display = (okScope && okText) ? '' : 'none';
    });
  }

  document.querySelectorAll('.dm-scope-btn').forEach(btn => {
    btn.addEventListener('click', function(){
      document.querySelectorAll('.dm-scope-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      dmScope = this.getAttribute('data-scope') || 'all';
      applyDmFilters();
    });
  });

  if(dmSearch){
    dmSearch.addEventListener('input', applyDmFilters);
  }

  // when modal opens, reset filters
  const startDmModal = document.getElementById('startDmModal');
  if(startDmModal){
    startDmModal.addEventListener('shown.bs.modal', function(){
      dmScope = 'all';
      document.querySelectorAll('.dm-scope-btn').forEach(b => {
        b.classList.toggle('active', (b.getAttribute('data-scope') === 'all'));
      });
      if(dmSearch) dmSearch.value = '';
      applyDmFilters();
    });
  }

  async function pollUnreads(){
    try{
      const res = await fetch(unreadsUrl, { headers: { 'Accept':'application/json' } });
      const json = await res.json();
      if(json && json.ok) updateUnreadBadges(json.data || {});
    }catch(e){}
  }

  async function markRead(){
    if(!markReadUrl) return;
    try{
      await fetch(markReadUrl, {
        method:'POST',
        headers:{
          'X-CSRF-TOKEN': csrf,
          'Accept':'application/json'
        }
      });
      // refresh unread state immediately
      pollUnreads();
    }catch(e){}
  }

  // mark read on load if room open
  if(openRoomId) setTimeout(markRead, 350);

  // mark read button
  const btn = document.getElementById('btnMarkRead');
  if(btn) btn.addEventListener('click', function(e){
    e.preventDefault();
    markRead();
  });

  // send form (ajax + reload for correctness)
  const sendForm = document.getElementById('sendForm');
  if(sendForm){
    sendForm.addEventListener('submit', async function(e){
      e.preventDefault();
      const fd = new FormData(sendForm);

      try{
        const res = await fetch(sendForm.action, {
          method:'POST',
          headers:{ 'X-CSRF-TOKEN': csrf, 'Accept':'application/json' },
          body: fd
        });
        const json = await res.json();
        if(json && json.ok){
  // reset local selected files
  if(fileInput){
    pickedFiles = [];
    syncInputFiles();
    renderPicked();
  }
  window.location.reload();
}
else{
          alert('Message failed.');
        }
      }catch(err){
        alert('Message failed.');
      }
    });
  }

  // Start DM clicks
  document.querySelectorAll('#startDmModal [data-user-id]').forEach(btn => {
    btn.addEventListener('click', async function(){
      const uid = this.getAttribute('data-user-id');
      try{
        const res = await fetch(dmStartUrl, {
          method:'POST',
          headers:{
            'X-CSRF-TOKEN': csrf,
            'Accept':'application/json',
            'Content-Type':'application/json'
          },
          body: JSON.stringify({ user_id: uid })
        });
        const json = await res.json();
        if(json && json.ok && json.room_id){
          window.location.href = @json(url('/admin/staff-chat/room')) + '/' + json.room_id;
        }else{
          alert('DM not allowed or failed.');
        }
      }catch(e){
        alert('DM not allowed or failed.');
      }
    });
  });

  // polling
  pollUnreads();
  setInterval(pollUnreads, 5000);

})();
</script>
@endpush

@endsection
