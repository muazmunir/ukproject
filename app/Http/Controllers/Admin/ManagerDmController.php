<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffDmThread;
use App\Models\StaffDmMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManagerDmController extends Controller
{
  private function ensureManager(): void
  {
    $role = strtolower((string) auth()->user()->role);
    abort_unless($role === 'manager', 403);
  }


  private function threadsForManagerWithUnread()
{
  $me = auth()->id();

  return StaffDmThread::query()
    ->with('agent')
    ->where('manager_id', $me)
    ->select('staff_dm_threads.*')
    ->selectSub(function ($q) use ($me) {
      $q->from('staff_dm_messages as m')
        ->selectRaw('COUNT(*)')
        ->whereColumn('m.thread_id', 'staff_dm_threads.id')
        ->whereRaw('m.id > COALESCE(staff_dm_threads.manager_last_read_id, 0)')
        ->where('m.sender_id', '!=', $me); // unread for manager = messages not sent by manager (agent)
    }, 'unread_count')
    ->orderByDesc('last_message_at')
    ->orderByDesc('id');
}


 public function index()
{
  $this->ensureManager();

  $threads = $this->threadsForManagerWithUnread()->get();
  $active  = $threads->firstWhere('is_active', true) ?? $threads->first();

  return view('admin.dm.manager.index', compact('threads','active'));
}

  public function show(StaffDmThread $thread)
{
  $this->ensureManager();
  abort_unless((int)$thread->manager_id === (int)auth()->id(), 403);

  $thread->load(['agent','manager']);

  // ✅ mark as read when opened
  $latestId = (int) $thread->messages()->max('id');
  $thread->update(['manager_last_read_id' => $latestId]);

  $threads = $this->threadsForManagerWithUnread()->get();

  $messages = $thread->messages()->with('sender')->latest('id')->take(50)->get()->reverse()->values();

  $canSend = (bool) $thread->is_active;

  return view('admin.dm.manager.show', compact('thread','threads','messages','canSend'));
}


  public function send(Request $r, StaffDmThread $thread)
  {
    $this->ensureManager();
    abort_unless((int)$thread->manager_id === (int)auth()->id(), 403);
    abort_unless((bool)$thread->is_active, 403);

    $r->validate(['body' => ['required','string','min:1','max:5000']]);
    $body = trim((string) $r->body);

    $msg = null;

    DB::transaction(function () use ($thread, $body, &$msg) {
      $msg = StaffDmMessage::create([
        'thread_id' => $thread->id,
        'sender_id' => auth()->id(),
        'body' => $body,
      ]);

      $thread->update([
        'last_message_id' => $msg->id,
        'last_message_at' => now(),
      ]);
    });

    $msg->load('sender');

    return response()->json([
      'ok' => true,
      'id' => $msg->id,
      'html' => view('shared.dm.partials.message', [
        'm' => $msg,
        'meId' => auth()->id(),
      ])->render(),
    ]);
  }

  public function latest(Request $r, StaffDmThread $thread)
{
  $this->ensureManager();
  abort_unless((int)$thread->manager_id === (int)auth()->id(), 403);

  $afterId = (int) $r->query('after_id', 0);

  $new = $thread->messages()
    ->with('sender')
    ->where('id','>', $afterId)
    ->orderBy('id')
    ->get();

  $html = '';
  $last = $afterId;

  foreach ($new as $m) {
    $html .= view('shared.dm.partials.message', ['m'=>$m, 'meId'=>auth()->id()])->render();
    $last = $m->id;
  }

  // ✅ mark read if we received anything (manager is viewing this thread)
  if ($last > $afterId) {
    $thread->update(['manager_last_read_id' => (int) $last]);
  }

  return response()->json(['ok'=>true,'html'=>$html,'last_id'=>$last]);
}

}
