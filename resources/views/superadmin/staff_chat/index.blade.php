@extends('superadmin.layout')

@section('title','Staff Chat')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/staff_chat.css') }}">
@endpush

@section('content')
@php
  $meId = $me ?? auth()->id();
  $room = $openRoom;
   $tz = (string) (auth()->user()->timezone ?? config('app.timezone') ?? 'UTC');

  $nameOf = function($u){
    return $u->username ?? $u->name ?? ('User#'.($u->id ?? ''));
  };
@endphp

<div class="sc-wrap">
  <div class="sc-card">

    {{-- LEFT SIDEBAR --}}
    <aside class="sc-side">
      <div class="sc-side-head">
        <div>
          <div class="sc-title">Staff Chat</div>
          <div class="sc-sub">Superadmin • All conversations</div>
        </div>

        <button class="btn btn-dark bg-black btn-sm sc-newdm" data-bs-toggle="modal" data-bs-target="#startDmModal">
          <i class="bi bi-chat-dots"></i> New DM
        </button>
      </div>

      {{-- SIDEBAR SEARCH --}}
      <div class="px-3 pt-2">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input type="text" class="form-control" id="sidebarChatSearch" placeholder="Search chats…">
        </div>
      </div>

      {{-- GROUPS --}}
      <div class="sc-section">
        <div class="sc-section-title">Groups</div>

        <div class="sc-list">
          @foreach($groupRooms as $r)
            @php
              $active = ($room?->id) && ($r?->id) && ((int)$room->id === (int)$r->id);

              $lm = $r->latestMessage;
$time = $r->last_message_at
  ? \Carbon\Carbon::parse($r->last_message_at)
      ->timezone($tz)
      ->format('d M Y, h:i A')
  : '';

              $preview = $lm?->body ? \Illuminate\Support\Str::limit($lm->body, 42) : 'No Messages Yet';
            @endphp

            <a class="sc-item {{ $active ? 'active' : '' }}"
              href="{{ $r?->id ? route('superadmin.staff_chat.show', ['room' => $r->id]) : '#' }}"

               data-room-id="{{ $r->id }}">
              <div class="sc-item-row">
                <div class="sc-item-title">
                  <span class="sc-pill sc-pill-group"><i class="bi bi-people"></i></span>
                  <span class="text-truncate">{{ $r->name ?? 'Group' }}</span>
                </div>
                <div class="sc-item-right">
                  <span class="sc-time">{{ $time }}</span>
                  <span class="sc-badge unread-badge" data-unread-for="{{ $r->id }}" style="display:none">0</span>
                </div>
              </div>
              <div class="sc-item-sub text-truncate">{{ $preview }}</div>
            </a>
          @endforeach
        </div>
      </div>

      {{-- MY DIRECT MESSAGES (superadmin is a participant) --}}
      <div class="sc-section">
        <div class="sc-section-title">My Direct Messages</div>

        <div class="sc-list">
          @forelse($myDmRooms as $r)
            @php
              $active = $room && $room->id === $r->id;

              $other = $r->users->firstWhere('id','!=',$meId);
              $otherName = $other ? $nameOf($other) : 'User';
              $otherRole = strtolower((string)($other->role ?? ''));

             
  $lm = $r->latestMessage;
  $time = $r->last_message_at
  ? \Carbon\Carbon::parse($r->last_message_at)
      ->timezone($tz)
      ->format('d M Y, h:i A')
  : '';
  $preview = $lm?->body ? \Illuminate\Support\Str::limit($lm->body, 42) : 'No Messages Yet';
@endphp


            <a class="sc-item {{ $active ? 'active' : '' }}"
               href="{{ route('superadmin.staff_chat.show', ['room' => $r->id]) }}"
               data-room-id="{{ $r->id }}">
              <div class="sc-item-row">
                <div class="sc-item-title">
                  <span class="sc-pill sc-pill-dm"><i class="bi bi-person"></i></span>
                  <span class="text-truncate">
                    {{ $otherName }}
                    <span class="sc-role-tag role-{{ $otherRole }}">{{ $otherRole }}</span>
                    <span class="sc-role-tag role-superadmin">me</span>
                  </span>
                </div>

                <div class="sc-item-right">
                  <span class="sc-time">{{ $time }}</span>
                  <span class="sc-badge unread-badge" data-unread-for="{{ $r->id }}" style="display:none">0</span>
                </div>
              </div>

              <div class="sc-item-sub text-truncate">{{ $preview }}</div>
            </a>
          @empty
            <div class="sc-empty">No direct messages yet.</div>
          @endforelse
        </div>
      </div>

      {{-- DM AUDIT (rooms not containing superadmin) --}}
      <div class="sc-section">
        <div class="sc-section-title">DM Audit (Others)</div>

        <div class="sc-list">
          @forelse($auditDmRooms as $r)
            @php
              $active = $room && $room->id === $r->id;

              $u1 = $r->users->values()->get(0);
              $u2 = $r->users->values()->get(1);

              $u1Name = $u1 ? $nameOf($u1) : 'User';
              $u2Name = $u2 ? $nameOf($u2) : 'User';

              $u1Role = strtolower((string)($u1->role_label ?? ''));
              $u2Role = strtolower((string)($u2->role_label ?? ''));

              
  $lm = $r->latestMessage;
  $time = $r->last_message_at
    ? \Carbon\Carbon::parse($r->last_message_at)->timezone($tz)->format('H:i')
    : '';
  $preview = $lm?->body ? \Illuminate\Support\Str::limit($lm->body, 42) : 'No Messages Yet';



              $dmTitle = $u1Name . ' ↔ ' . $u2Name;
            @endphp

            <a class="sc-item {{ $active ? 'active' : '' }}"
               href="{{ route('superadmin.staff_chat.show', ['room' => $r->id]) }}"
               data-room-id="{{ $r->id }}">
              <div class="sc-item-row">
                <div class="sc-item-title">
                  <span class="sc-pill sc-pill-dm"><i class="bi bi-shield-lock"></i></span>
                  <span class="text-truncate">
                    {{ $dmTitle }}
                    <span class="sc-role-tag role-{{ $u1Role }}">{{ $u1Role }}</span>
                    <span class="sc-role-tag role-{{ $u2Role }}">{{ $u2Role }}</span>
                  </span>
                </div>

                <div class="sc-item-right">
                  <span class="sc-time">{{ $time }}</span>
                  <span class="sc-badge unread-badge" data-unread-for="{{ $r->id }}" style="display:none">0</span>
                </div>
              </div>

              <div class="sc-item-sub text-truncate">{{ $preview }}</div>
            </a>
          @empty
            <div class="sc-empty">No audit DMs found.</div>
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
                // superadmin may or may not be part of this dm
                $isMine = $room->users->contains('id', $meId);

                $u1 = $room->users->values()->get(0);
                $u2 = $room->users->values()->get(1);

                $u1Name = $u1 ? $nameOf($u1) : 'User';
                $u2Name = $u2 ? $nameOf($u2) : 'User';

                $u1Role = strtolower((string)($u1->role_label ?? ''));
                $u2Role = strtolower((string)($u2->role_label ?? ''));
              @endphp

              <div class="h5 mb-0">{{ $u1Name }} ↔ {{ $u2Name }}</div>
              <div class="small text-muted">
                {{ $isMine ? 'My DM' : 'Audit DM' }} •
                <span class="text-capitalize">{{ $u1Role }}</span> &amp; <span class="text-capitalize">{{ $u2Role }}</span>
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

        <div class="sc-messages" id="scMessages">
          @include('superadmin.staff_chat._messages', ['room' => $room, 'meId' => $meId])
        </div>

        <form class="sc-composer" id="sendForm"
              action="{{ route('superadmin.staff_chat.send', ['room' => $room->id]) }}"
              method="POST" enctype="multipart/form-data">
          @csrf

          <div class="sc-composer-row">
            <textarea name="body" class="form-control sc-input" rows="1" placeholder="Type a message…"></textarea>

            <label class="btn btn-light sc-attach" title="Attach files">
              <i class="bi bi-paperclip"></i>
              <input type="file" name="attachments[]" multiple hidden id="scAttachInput">
            </label>

            <button class="btn btn-success sc-send bg-black" type="submit">
              <i class="bi bi-send-fill"></i>
            </button>
          </div>

          <div class="sc-picked" id="scPicked" style="display:none;">
            <div class="sc-picked-head">
              <div class="sc-picked-title">Selected files</div>
              <button type="button" class="sc-picked-clear" id="scPickedClear">Clear all</button>
            </div>
            <div class="sc-picked-list" id="scPickedList"></div>
          </div>

          <div class="sc-hint">Admin can post to any room. (DMs allowed only with managers)</div>
        </form>
      @else
        <div class="p-4">
          <div class="alert alert-warning mb-0">No rooms available.</div>
        </div>
      @endif
    </main>
  </div>
</div>

{{-- START DM MODAL (managers only) --}}
<div class="modal fade" id="startDmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Start Direct Message</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="input-group input-group-sm mb-2">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input type="text" class="form-control" id="dmUserSearch" placeholder="Search manager…">
        </div>

        <div class="sc-userlist" id="dmUserList">
          @forelse($users as $u)
            @php $label = $u->username ?? $u->name ?? 'User'; @endphp
            <button type="button" class="user-pill dm-user-row"
                    data-user-id="{{ $u->id }}"
                    data-name="{{ strtolower($label) }}">
              <div class="d-flex justify-content-between align-items-center w-100">
                <div class="text-truncate">
                  <div class="fw-semibold">{{ $label }}</div>
                  <div class="small text-muted text-capitalize">Role: {{ strtolower((string)$u->role) }}</div>
                </div>
                <i class="bi bi-chevron-right"></i>
              </div>
            </button>
          @empty
            <div class="text-muted">No managers found.</div>
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
  const unreadsUrl = @json(route('superadmin.staff_chat.unreads'));
  const markReadUrl = openRoomId ? @json(route('superadmin.staff_chat.read', ['room' => $room->id])) : null;

  const dmStartUrl  = @json(route('superadmin.staff_chat.dm.start'));
 const roomBaseUrl = @json(url('/superadmin/staff-chat/room'));


  // ===== Attachments picker =====
  // keep your existing attachment JS here (same as admin version)
  // =============================


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
      if(n > 0){ el.textContent = n; el.style.display = 'inline-flex'; }
      else { el.style.display = 'none'; }
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
        headers:{ 'X-CSRF-TOKEN': csrf, 'Accept':'application/json' }
      });
      pollUnreads();
    }catch(e){}
  }

  if(openRoomId) setTimeout(markRead, 350);
  const btn = document.getElementById('btnMarkRead');
  if(btn) btn.addEventListener('click', e => { e.preventDefault(); markRead(); });

  // Sidebar search (filters all items)
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

  // DM modal search
  const dmSearch = document.getElementById('dmUserSearch');
  const dmList = document.getElementById('dmUserList');
  function applyDmSearch(){
    const q = (dmSearch?.value || '').toLowerCase().trim();
    dmList?.querySelectorAll('.dm-user-row').forEach(row => {
      const name = row.getAttribute('data-name') || '';
      row.style.display = !q || name.includes(q) ? '' : 'none';
    });
  }
  if(dmSearch) dmSearch.addEventListener('input', applyDmSearch);

  // Start DM click
 document.querySelectorAll('#startDmModal [data-user-id]').forEach(btn => {
  btn.addEventListener('click', async function(){
    const uid = this.getAttribute('data-user-id');

    try {
      const res = await fetch(dmStartUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ user_id: uid })
      });

      const text = await res.text();
      console.log('DM start status:', res.status);
      console.log('DM start raw response:', text);

      let json = {};
      try { json = JSON.parse(text); } catch (e) {}

      if (res.ok && json.ok && json.room_id) {
        window.location.href = roomBaseUrl + '/' + json.room_id;
      } else {
        alert(json.message || ('Failed with status ' + res.status));
      }
    } catch (e) {
      console.error('DM start exception:', e);
      alert(e.message || 'DM failed');
    }
  });
});

  pollUnreads();
  setInterval(pollUnreads, 5000);
})();
</script>
@endpush

@endsection
