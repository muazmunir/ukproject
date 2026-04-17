<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffChatRoom;
use App\Models\StaffChatMessage;
use App\Models\StaffChatAttachment;
use App\Models\StaffTeam;
use App\Models\StaffTeamMember;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StaffChatController extends Controller
{
 private function role(): string {
  return strtolower((string) auth()->user()->role);
}

private function isAdminRole(): bool {
  return $this->role() === 'admin';
}

private function isManagerRole(): bool {
  return $this->role() === 'manager';
}

private function isSuperAdminRole(): bool {
  return $this->role() === 'superadmin';
}

  private function myTeamId(): ?int
  {
    $me = auth()->id();

    // manager => active team where manager_id = me
    if ($this->isManagerRole()) {
      return StaffTeam::query()
        ->where('manager_id', $me)
        ->where('is_active', true)
        ->whereNull('deleted_at')
        ->value('id');
    }

    // admin => active assignment in staff_team_members
    if ($this->isAdminRole()) {
      return StaffTeamMember::query()
        ->where('agent_id', $me)
        ->whereNull('end_at')
        ->value('team_id');
    }

    return null;
  }

  private function ensureAllStaffRoom(): StaffChatRoom
{
    $room = StaffChatRoom::firstOrCreate([
        'room_type' => 'all_staff'
    ], [
        'name' => 'All Staff',
    ]);

    // ALWAYS sync users
    $ids = Users::query()
        ->whereIn(DB::raw('LOWER(role)'), ['admin','manager','superadmin'])
        ->pluck('id')
        ->all();

    foreach ($ids as $uid) {
        DB::table('staff_chat_room_users')->updateOrInsert([
            'room_id' => $room->id,
            'user_id' => $uid,
        ], [
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    return $room;
}
  private function ensureMyTeamRoom(): ?StaffChatRoom
  {
    $teamId = $this->myTeamId();
    if (!$teamId) return null;

    $room = StaffChatRoom::query()
      ->where('room_type','team_group')
      ->where('team_id', $teamId)
      ->first();

    if ($room) return $room;

    return DB::transaction(function () use ($teamId) {
      $team = StaffTeam::with(['activeMembers.agent','manager'])->findOrFail($teamId);

      $room = StaffChatRoom::create([
        'room_type' => 'team_group',
        'team_id' => $team->id,
        'name' => $team->name,
      ]);

      $memberIds = $team->activeMembers->pluck('agent_id')->all();
      $all = array_unique(array_merge($memberIds, [(int)$team->manager_id]));

      foreach ($all as $uid) {
        DB::table('staff_chat_room_users')->insert([
          'room_id' => $room->id,
          'user_id' => $uid,
          'created_at' => now(),
          'updated_at' => now(),
        ]);
      }
      return $room;
    });
  }

  private function authorizeRoomAccess(StaffChatRoom $room): void
  {
    $me = auth()->id();

    $isMember = DB::table('staff_chat_room_users')
      ->where('room_id', $room->id)
      ->where('user_id', $me)
      ->exists();

    abort_unless($isMember, 403, 'Not allowed.');
  }


 private function pickDmOtherUser(StaffChatRoom $room, int $meId): ?Users
{
  $users  = $room->users ?? collect();
  $others = $users->filter(fn($u) => (int)$u->id !== (int)$meId)->values();

  if ($others->isEmpty()) return null;

  // If DM is corrupted (3 users), pick based on MY role rules
  $myRole = strtolower((string)auth()->user()->role);

  // Admin: prefer manager only
  if ($myRole === 'admin') {
    $mgr = $others->first(fn($u) => strtolower((string)$u->role) === 'manager');
    return $mgr ?: $others->first();
  }

  // Manager: prefer admin/manager first, superadmin last
  if ($myRole === 'manager') {
    $preferred = $others->sortBy(function($u){
      $r = strtolower((string)($u->role ?? ''));
      return $r === 'superadmin' ? 1 : 0; // superadmin last
    })->values();
    return $preferred->first();
  }

  // fallback
  return $others->first();
}


private function attachDmDisplayMeta($dmRooms, int $meId)
{
  return $dmRooms->map(function($r) use ($meId) {
    $other = $this->pickDmOtherUser($r, $meId);

    $r->dm_other_name = $other->username ?? $other->name ?? 'User';
    $r->dm_other_role = strtolower((string)($other->role ?? ''));

    return $r;
  });
}


  public function index()
  {
    $me = auth()->id();

    $allStaff = $this->ensureAllStaffRoom();
    $teamRoom = $this->ensureMyTeamRoom();

    $rooms = StaffChatRoom::query()
      ->whereIn('id', array_filter([$allStaff?->id, $teamRoom?->id]))
      ->with('latestMessage.user')
      ->get();

    // DMs where I am a member
    $dmRooms = StaffChatRoom::query()
      ->where('room_type','dm')
      ->whereExists(function ($q) use ($me) {
        $q->selectRaw(1)->from('staff_chat_room_users as ru')
          ->whereColumn('ru.room_id','staff_chat_rooms.id')
          ->where('ru.user_id', $me);
      })
      ->with(['latestMessage.user','users'])
      ->orderByDesc('last_message_at')
      ->get();
      $dmRooms = $this->attachDmDisplayMeta($dmRooms, (int)$me);


    // for sidebar "start DM" list
    $users = $this->buildDmUserListForMe();

    // pick first room to open
    $openRoom = $dmRooms->first() ?? $rooms->first() ?? $allStaff;

    return view('admin.staff_chat.index', compact('rooms','dmRooms','openRoom','users','me'));
  }

  public function show(StaffChatRoom $room)
{
    $this->authorizeRoomAccess($room);

    $me = auth()->id();

    $allStaff = $this->ensureAllStaffRoom();
    $teamRoom = $this->ensureMyTeamRoom();

    $rooms = StaffChatRoom::query()
        ->whereIn('id', array_filter([$allStaff?->id, $teamRoom?->id]))
        ->with('latestMessage.user')
        ->get();

    $dmRooms = StaffChatRoom::query()
        ->where('room_type','dm')
        ->whereExists(function ($q) use ($me) {
            $q->selectRaw(1)->from('staff_chat_room_users as ru')
              ->whereColumn('ru.room_id','staff_chat_rooms.id')
              ->where('ru.user_id', $me);
        })
        ->with(['latestMessage.user','users'])
        ->orderByDesc('last_message_at')
        ->get();
        $dmRooms = $this->attachDmDisplayMeta($dmRooms, (int)$me);


    $users = $this->buildDmUserListForMe();

    // ✅ IMPORTANT: in the blade we use $openRoom, so set it here
    $openRoom = $room->load(['users','latestMessage.user']);

    return view('admin.staff_chat.index', compact('rooms','dmRooms','openRoom','users','me'));
}

  public function send(Request $r, StaffChatRoom $room)
  {
    $this->authorizeRoomAccess($room);

    $r->validate([
      'body' => ['nullable','string'],
      'attachments' => ['nullable','array'],
      'attachments.*' => ['file','max:10240'], // 10MB each
    ]);

    abort_if(!$r->filled('body') && !$r->hasFile('attachments'), 422, 'Empty message.');

    return DB::transaction(function () use ($r, $room) {

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

      return response()->json(['ok' => true, 'message_id' => $msg->id]);
    });
  }

  public function markRead(StaffChatRoom $room)
  {
    $this->authorizeRoomAccess($room);

    $lastId = (int) $room->messages()->max('id');

    DB::table('staff_chat_room_users')
      ->where('room_id', $room->id)
      ->where('user_id', auth()->id())
      ->update([
        'last_read_message_id' => $lastId ?: null,
        'last_read_at' => now(),
        'updated_at' => now(),
      ]);

    return response()->json(['ok' => true, 'last_read_message_id' => $lastId]);
  }


 

  public function unreads()
  {
    $me = auth()->id();

    // unread per room = max(message_id in room) - last_read_message_id logic using counts
    $rows = DB::table('staff_chat_room_users as ru')
      ->join('staff_chat_rooms as r', 'r.id', '=', 'ru.room_id')
      ->leftJoin('staff_chat_messages as m', function ($j) {
        $j->on('m.room_id', '=', 'r.id');
      })
      ->where('ru.user_id', $me)
      ->whereNull('m.deleted_at')
      ->where(function ($q) {
        $q->whereNull('ru.last_read_message_id')
          ->orWhereColumn('m.id', '>', 'ru.last_read_message_id');
      })
      ->whereColumn('m.user_id','!=','ru.user_id') // don't count my own
      ->select('r.id as room_id', DB::raw('COUNT(m.id) as unread'))
      ->groupBy('r.id')
      ->get();

    $map = [];
    foreach ($rows as $x) $map[$x->room_id] = (int)$x->unread;

    return response()->json(['ok' => true, 'data' => $map]);
  }

  private function buildDmUserListForMe()
  {
    $me = auth()->user();
    $myTeamId = $this->myTeamId();

    // base query
   // base query
$q = Users::query()->where('id','!=',$me->id);

if ($this->isAdminRole()) {
    // admin can DM ONLY their own manager
    $myTeamId = $this->myTeamId();
    $myManagerId = null;

    if ($myTeamId) {
        $myManagerId = (int) StaffTeam::query()->where('id', $myTeamId)->value('manager_id');
    }

    if ($myManagerId) {
        $q->where('id', $myManagerId);
    } else {
        // no team => no DM target
        $q->whereRaw('1=0');
    }
}

elseif ($this->isManagerRole()) {
    // manager can DM admin + manager + superadmin
    $q->whereIn('role', ['admin','manager','superadmin']);
}
elseif ($this->isSuperAdminRole()) {
    // superadmin can DM anyone (optional but recommended)
    $q->whereIn('role', ['admin','manager','superadmin']);
}
else {
    // if some other role ever appears, block list
    $q->whereRaw('1=0');
}

$users = $q->orderBy('username')->get(['id','username','role']);


    // add "is_my_team" flag for coloring
    $teamAgentIds = [];
    $myManagerId = null;

    if ($myTeamId) {
      $team = StaffTeam::with(['activeMembers'])->find($myTeamId);
      $teamAgentIds = $team?->activeMembers?->pluck('agent_id')->all() ?? [];
      $myManagerId = (int) ($team?->manager_id ?? 0);
    }

    return $users->map(function ($u) use ($teamAgentIds, $myManagerId) {
      $u->is_my_team = in_array($u->id, $teamAgentIds, true);
      $u->is_my_manager = $myManagerId && $u->id === $myManagerId;
      return $u;
    });
  }

  public function startDm(Request $r)
  {
    $r->validate(['user_id' => ['required','integer','exists:users,id']]);

    $me = auth()->id();
    $other = (int) $r->user_id;

    // enforce permissions:
   $otherRole = strtolower((string) Users::where('id',$other)->value('role'));

// admin can DM manager only
if ($this->isAdminRole()) {
    abort_unless($otherRole === 'manager', 403);
}

// manager can DM admin + manager + superadmin
if ($this->isManagerRole()) {
    abort_unless(in_array($otherRole, ['admin','manager','superadmin'], true), 403);
}

// superadmin can DM anyone (optional but recommended)
if ($this->isSuperAdminRole()) {
    abort_unless(in_array($otherRole, ['admin','manager','superadmin'], true), 403);
}

    // find existing dm room between 2 users
    $room = StaffChatRoom::query()
      ->where('room_type','dm')
      ->whereExists(function ($q) use ($me, $other) {
        $q->selectRaw(1)->from('staff_chat_room_users as a')
          ->whereColumn('a.room_id','staff_chat_rooms.id')
          ->where('a.user_id', $me);
      })
      ->whereExists(function ($q) use ($me, $other) {
        $q->selectRaw(1)->from('staff_chat_room_users as b')
          ->whereColumn('b.room_id','staff_chat_rooms.id')
          ->where('b.user_id', $other);
      })
      ->first();

    if ($room) {
      return response()->json(['ok' => true, 'room_id' => $room->id]);
    }

    $room = DB::transaction(function () use ($me, $other) {
      $room = StaffChatRoom::create(['room_type' => 'dm']);

      DB::table('staff_chat_room_users')->insert([
        ['room_id'=>$room->id,'user_id'=>$me,'created_at'=>now(),'updated_at'=>now()],
        ['room_id'=>$room->id,'user_id'=>$other,'created_at'=>now(),'updated_at'=>now()],
      ]);

      return $room;
    });

    return response()->json(['ok' => true, 'room_id' => $room->id]);
  }
}
