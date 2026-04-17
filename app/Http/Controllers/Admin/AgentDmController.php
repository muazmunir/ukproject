<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffTeamMember;
use App\Models\StaffTeam;
use App\Models\StaffDmThread;
use App\Models\StaffDmMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class AgentDmController extends Controller
{
  private function ensureAgent(): void
  {
    $role = strtolower((string) auth()->user()->role);
    abort_unless(in_array($role, ['admin','super_admin'], true), 403);
  }

  private function activeTeamForAgent(int $agentId): ?StaffTeam
  {
    $member = StaffTeamMember::where('agent_id', $agentId)->whereNull('end_at')->latest('id')->first();
    return $member ? StaffTeam::with('manager')->find($member->team_id) : null;
  }


  private function threadsForAgentWithUnread()
{
  $me = auth()->id();

  return StaffDmThread::query()
    ->with('manager')
    ->where('agent_id', $me)
    ->select('staff_dm_threads.*')
    ->selectSub(function ($q) use ($me) {
      $q->from('staff_dm_messages as m')
        ->selectRaw('COUNT(*)')
        ->whereColumn('m.thread_id', 'staff_dm_threads.id')
        ->whereRaw('m.id > COALESCE(staff_dm_threads.agent_last_read_id, 0)')
        ->where('m.sender_id', '!=', $me);
    }, 'unread_count')
    ->orderByDesc('last_message_at')
    ->orderByDesc('id');
}

 public function index()
{
  $this->ensureAgent();

  $threads = $this->threadsForAgentWithUnread()->get();
  $active  = $threads->firstWhere('is_active', true) ?? $threads->first();

  return view('admin.dm.agent.index', compact('threads','active'));
}


 public function show(StaffDmThread $thread)
{
  $this->ensureAgent();
  abort_unless((int)$thread->agent_id === (int)auth()->id(), 403);

  $thread->load(['manager','agent']);

  // mark as read when opened
  $thread->update([
    'agent_last_read_id' => $thread->last_message_id,
  ]);

  $threads = $this->threadsForAgentWithUnread()->get();

  $messages = $thread->messages()->with('sender')->latest('id')->take(50)->get()->reverse()->values();

  $canSend = (bool) $thread->is_active;

  return view('admin.dm.agent.show', compact('thread','threads','messages','canSend'));
}


  public function send(Request $r, StaffDmThread $thread)
  {
    $this->ensureAgent();
    abort_unless((int)$thread->agent_id === (int)auth()->id(), 403);
    abort_unless((bool)$thread->is_active, 403); // cannot message old managers

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
  $this->ensureAgent();
  abort_unless((int)$thread->agent_id === (int)auth()->id(), 403);

  $afterId = (int) $r->query('after_id', 0);

  $new = $thread->messages()
    ->with('sender')
    ->where('id', '>', $afterId)
    ->orderBy('id')
    ->get();

  $html = '';
  $last = $afterId;

  foreach ($new as $m) {
    $html .= view('shared.dm.partials.message', ['m'=>$m, 'meId'=>auth()->id()])->render();
    $last = $m->id;
  }

  // ✅ mark read if we received anything (agent is viewing this thread)
  if ($last > $afterId) {
    $thread->update([
      'agent_last_read_id' => $last,
    ]);
  }

  return response()->json(['ok'=>true,'html'=>$html,'last_id'=>$last]);
}
}
