<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\StaffChatRoom;
use App\Models\StaffChatMessage;
use App\Models\StaffChatAttachment;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperStaffChatController extends Controller
{
  private function superOnly(): void
  {
    abort_unless(strtolower((string) auth()->user()->role) === 'superadmin', 403);
  }

  private function isManagerUser(int $userId): bool
  {
    $role = strtolower((string) Users::where('id', $userId)->value('role'));
    return $role === 'manager';
  }

  private function isRoomMember(int $roomId, int $userId): bool
  {
    return DB::table('staff_chat_room_users')
      ->where('room_id', $roomId)
      ->where('user_id', $userId)
      ->exists();
  }

  /**
   * Build a clear label for DMs:
   * - if superadmin is a member => "My DM • Superadmin & ManagerName"
   * - if not a member (audit) => "Audit DM • AdminName ↔ ManagerName"
   */
  private function dmLabel(StaffChatRoom $room, int $meId): array
  {
    $users = $room->users ?? collect();

    // defensive: if relationship not loaded
    if ($users->isEmpty()) {
      return [
        'dm_title' => 'Direct Message',
        'dm_sub'   => 'DM',
        'dm_left'  => null,
        'dm_right' => null,
        'dm_is_my' => false,
      ];
    }

    $isMy = $users->contains('id', $meId);

    // Make it stable: order by role priority for label
    $roleWeight = function ($u) {
      $r = strtolower((string)($u->role ?? ''));
      return match ($r) {
        'superadmin' => 0,
        'manager'    => 1,
        'admin'      => 2,
        default      => 9,
      };
    };

    $sorted = $users->sortBy($roleWeight)->values();

    // If my DM: show "Superadmin & Other"
    if ($isMy) {
      $other = $users->firstWhere('id', '!=', $meId);

      $otherName = $other->username ?? $other->name ?? 'User';
      $otherRole = strtoupper((string)($other->role ?? ''));

      return [
        'dm_title' => $otherName,
        'dm_sub'   => "My DM • SUPERADMIN & {$otherRole}",
        'dm_left'  => $meId,
        'dm_right' => (int)($other?->id ?? 0),
        'dm_is_my' => true,
      ];
    }

    // Audit DM: show "UserA ↔ UserB" (who ↔ who clarity)
    $a = $sorted->get(0);
    $b = $sorted->get(1);

    $aName = $a?->username ?? $a?->name ?? 'User';
    $bName = $b?->username ?? $b?->name ?? 'User';

    $aRole = strtoupper((string)($a?->role ?? ''));
    $bRole = strtoupper((string)($b?->role ?? ''));

    return [
      'dm_title' => "{$aName} ↔ {$bName}",
      'dm_sub'   => "Audit DM • {$aRole} & {$bRole}",
      'dm_left'  => (int)($a?->id ?? 0),
      'dm_right' => (int)($b?->id ?? 0),
      'dm_is_my' => false,
    ];
  }

  private function attachDmMeta($rooms, int $meId)
  {
    return $rooms->map(function ($r) use ($meId) {
      if ($r->room_type !== 'dm') return $r;

      $meta = $this->dmLabel($r, $meId);

      // used in blade sidebar + header
      $r->dm_title = $meta['dm_title'];
      $r->dm_sub   = $meta['dm_sub'];
      $r->dm_is_my = $meta['dm_is_my'];

      // optional (if you want IDs)
      $r->dm_left_user_id  = $meta['dm_left'];
      $r->dm_right_user_id = $meta['dm_right'];

      return $r;
    });
  }

  /**
   * ✅ Superadmin can SEND:
   * - to group/team rooms (allowed)
   * - to DM rooms ONLY IF room is "my DM" and other is manager
   */
  private function ensureSuperCanSend(StaffChatRoom $room): void
  {
    $me = (int) auth()->id();

    if ($room->room_type === 'dm') {
      // Must be member => prevents injecting into admin↔manager audit DM
      abort_unless($this->isRoomMember($room->id, $me), 403, 'You cannot message inside audit DMs.');

      // must be DM with manager
      $ids = DB::table('staff_chat_room_users')
        ->where('room_id', $room->id)
        ->pluck('user_id')
        ->map(fn($x) => (int)$x)
        ->all();

      $otherId = collect($ids)->first(fn($id) => $id !== $me);
      abort_if(!$otherId, 403, 'Invalid DM room.');
      abort_unless($this->isManagerUser((int)$otherId), 403, 'Superadmin can DM managers only.');
    }
  }

  public function index(Request $request)
  {
    $this->superOnly();

    $me = (int) auth()->id();
    $q  = trim((string) $request->get('q', ''));
    $openId = (int) $request->get('open', 0);

    $groupRooms = StaffChatRoom::query()
      ->whereIn('room_type', ['all_staff','team_group'])
      ->with('latestMessage.user')
      ->when($q !== '', function ($qq) use ($q) {
        $qq->where(function ($x) use ($q) {
          $x->where('name', 'like', "%{$q}%")
            ->orWhere('room_type', 'like', "%{$q}%");
        });
      })
      ->orderByRaw("CASE WHEN room_type='all_staff' THEN 0 ELSE 1 END")
      ->orderBy('name')
      ->get();

    $allDmRooms = StaffChatRoom::query()
      ->where('room_type', 'dm')
      ->with(['latestMessage.user','users'])
      ->orderByDesc('last_message_at')
      ->get();

    // split my vs audit
    $myDmRooms = $allDmRooms->filter(fn($r) => $r->users->contains('id', $me))->values();
    $auditDmRooms = $allDmRooms->reject(fn($r) => $r->users->contains('id', $me))->values();

    // search filter across both participants in DM
    if ($q !== '') {
      $needle = strtolower($q);

      $myDmRooms = $myDmRooms->filter(function ($r) use ($needle) {
        $names = $r->users->map(fn($u) => strtolower((string)($u->username ?? $u->name ?? '')))->implode(' ');
        return str_contains($names, $needle);
      })->values();

      $auditDmRooms = $auditDmRooms->filter(function ($r) use ($needle) {
        $names = $r->users->map(fn($u) => strtolower((string)($u->username ?? $u->name ?? '')))->implode(' ');
        return str_contains($names, $needle);
      })->values();
    }

    // ✅ attach DM labels (who↔who clarity)
    $myDmRooms = $this->attachDmMeta($myDmRooms, $me);
    $auditDmRooms = $this->attachDmMeta($auditDmRooms, $me);

    // ✅ OPEN the requested room if provided
   $openRoom = null;

if ($openId > 0) {
  $openRoom = StaffChatRoom::query()
    ->where('id', $openId)
    ->with(['users','latestMessage.user'])
    ->first();
}

if (!$openRoom) {
  $first = $myDmRooms->first() ?? $auditDmRooms->first() ?? $groupRooms->first();
  $openRoom = $first?->id
    ? StaffChatRoom::query()
        ->where('id', $first->id)
        ->with(['users','latestMessage.user'])
        ->first()
    : null;
}


    // attach DM meta to open room as well (header)
    if ($openRoom && $openRoom->room_type === 'dm' && $openRoom->relationLoaded('users')) {
      $meta = $this->dmLabel($openRoom, $me);
      $openRoom->dm_title = $meta['dm_title'];
      $openRoom->dm_sub   = $meta['dm_sub'];
      $openRoom->dm_is_my = $meta['dm_is_my'];
    }

    // ✅ superadmin can start DM with managers only
    $users = Users::query()
      ->whereRaw('LOWER(role) = "manager"')
      ->orderBy('username')
      ->get(['id','username','role']);

    return view('superadmin.staff_chat.index', compact(
      'groupRooms','myDmRooms','auditDmRooms','openRoom','users','me','q'
    ));
  }

  public function show(StaffChatRoom $room, Request $request)
  {
    $this->superOnly();
    // ✅ now it will actually open that room
    return $this->index($request->merge(['open' => $room->id]));
  }

  public function startDm(Request $r)
  {
    $this->superOnly();

    $r->validate(['user_id' => ['required','integer','exists:users,id']]);

    $me = (int) auth()->id();
    $other = (int) $r->user_id;

    abort_unless($this->isManagerUser($other), 403, 'Superadmin can DM managers only.');

    // ✅ ensure existing DM is EXACTLY 2 members
    $room = StaffChatRoom::query()
      ->where('room_type','dm')
      ->whereRaw('(select count(*) from staff_chat_room_users ru where ru.room_id = staff_chat_rooms.id) = 2')
      ->whereExists(function ($q) use ($me) {
        $q->selectRaw(1)->from('staff_chat_room_users as a')
          ->whereColumn('a.room_id','staff_chat_rooms.id')
          ->where('a.user_id', $me);
      })
      ->whereExists(function ($q) use ($other) {
        $q->selectRaw(1)->from('staff_chat_room_users as b')
          ->whereColumn('b.room_id','staff_chat_rooms.id')
          ->where('b.user_id', $other);
      })
      ->first();

    if ($room) return response()->json(['ok'=>true,'room_id'=>$room->id]);

    $room = DB::transaction(function () use ($me, $other) {
      $room = StaffChatRoom::create(['room_type' => 'dm']);

      DB::table('staff_chat_room_users')->insert([
        ['room_id'=>$room->id,'user_id'=>$me,'created_at'=>now(),'updated_at'=>now()],
        ['room_id'=>$room->id,'user_id'=>$other,'created_at'=>now(),'updated_at'=>now()],
      ]);

      return $room;
    });

    return response()->json(['ok'=>true,'room_id'=>$room->id]);
  }

 public function send(Request $r, StaffChatRoom $room)
{
    $this->superOnly();
    $this->ensureSuperCanSend($room);

    $r->validate([
        'body' => ['nullable','string'],
        'attachments' => ['nullable','array'],
        'attachments.*' => ['file','max:10240'],
    ]);

    if (!$r->filled('body') && !$r->hasFile('attachments')) {
        if ($r->expectsJson()) {
            return response()->json(['ok' => false, 'message' => 'Empty message.'], 422);
        }
        return back()->withErrors(['body' => 'Empty message.'])->withInput();
    }

    $msg = DB::transaction(function () use ($r, $room) {

        $msg = StaffChatMessage::create([
            'room_id' => $room->id,
            'user_id' => auth()->id(),
            'body'    => $r->input('body'),
            'type'    => $r->hasFile('attachments') ? 'attachment' : 'message',
        ]);

        if ($r->hasFile('attachments')) {
            foreach ($r->file('attachments', []) as $file) {
                $path = $file->store('staff_chat', 'public');

                StaffChatAttachment::create([
                    'message_id' => $msg->id,
                    'disk' => 'public',
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'size' => (int) $file->getSize(),
                ]);
            }
        }

        $room->update([
            'last_message_at' => now(),
            'last_message_id' => $msg->id,
        ]);

        return $msg;
    });

    // ✅ IMPORTANT: JSON for fetch/AJAX, redirect for normal form submit
    if ($r->expectsJson()) {
        return response()->json(['ok' => true, 'message_id' => $msg->id]);
    }

    return redirect()
        ->route('superadmin.staff_chat.show', ['room' => $room->id])
        ->with('sent', true);
}

  public function markRead(StaffChatRoom $room)
  {
    $this->superOnly();

    $me = (int) auth()->id();

    // ✅ if not a member, do nothing (audit view)
    if (!$this->isRoomMember($room->id, $me)) {
      return response()->json(['ok'=>true,'skipped'=>true]);
    }

    $lastId = (int) $room->messages()->max('id');

    DB::table('staff_chat_room_users')
      ->where('room_id', $room->id)
      ->where('user_id', $me)
      ->update([
        'last_read_message_id' => $lastId ?: null,
        'last_read_at' => now(),
        'updated_at' => now(),
      ]);

    return response()->json(['ok'=>true,'last_read_message_id'=>$lastId]);
  }

  public function unreads()
  {
    $this->superOnly();
    $me = (int) auth()->id();

    $rows = DB::table('staff_chat_room_users as ru')
      ->join('staff_chat_rooms as r', 'r.id', '=', 'ru.room_id')
      ->join('staff_chat_messages as m', function ($j) {
        $j->on('m.room_id', '=', 'r.id')->whereNull('m.deleted_at');
      })
      ->where('ru.user_id', $me)
      ->whereColumn('m.user_id','!=','ru.user_id')
      ->whereRaw('m.id > COALESCE(ru.last_read_message_id, 0)')
      ->select('r.id as room_id', DB::raw('COUNT(m.id) as unread'))
      ->groupBy('r.id')
      ->get();

    $map = [];
    foreach ($rows as $x) $map[$x->room_id] = (int)$x->unread;

    return response()->json(['ok'=>true,'data'=>$map]);
  }
}
