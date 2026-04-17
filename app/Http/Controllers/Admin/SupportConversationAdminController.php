<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportConversationRead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\SupportQueueService;

class SupportConversationAdminController extends Controller
{
    /* =========================================================
     * INBOX
     * ======================================================= */
    public function index(Request $request)
    {
        $staff = $this->staffOrFail();

        // ✅ auto-assign: normal chats + escalations
        app(SupportQueueService::class)->dispatchAgents();
        app(SupportQueueService::class)->dispatchManagers();

        $q      = $request->q;
        $status = $request->status ?? 'open';
        $role   = $request->role ?? 'all';
        $per    = (int)($request->per ?? 10);

        $dateFrom = $request->date_from;
        $dateTo   = $request->date_to;

        // admins/managers both can use mine/all
        $assigned = $request->assigned ?? 'all';
        $unread   = $request->unread ?? 'all';

        $convs = SupportConversation::query()
            ->with(['user', 'assignedStaff', 'assignedAdmin', 'manager'])
            ->select('support_conversations.*')

            // first client/coach real message (conversation "start" for SLA)
            ->selectSub(function ($sub) {
                $sub->from('support_messages as sm_start')
                    ->selectRaw('MIN(sm_start.created_at)')
                    ->whereColumn('sm_start.support_conversation_id', 'support_conversations.id')
                    ->whereIn('sm_start.sender_type', ['client','coach'])
                    ->where('sm_start.type', 'message');
            }, 'first_customer_message_at')

            // Minutes since first customer message until close (if closed), else until now()
          ->selectRaw("
  CASE
    WHEN support_conversations.sla_started_at IS NULL THEN NULL
    WHEN support_conversations.closed_at IS NOT NULL THEN
      TIMESTAMPDIFF(MINUTE, support_conversations.sla_started_at, support_conversations.closed_at)
    ELSE
      TIMESTAMPDIFF(MINUTE, support_conversations.sla_started_at, NOW())
  END AS admin_age_minutes
")


            // Manager timer: from manager_joined_at until manager_ended_at (if ended) else until now()
          ->selectRaw("
  CASE
    WHEN support_conversations.manager_requested_at IS NULL THEN NULL
    WHEN support_conversations.manager_joined_at IS NULL THEN NULL

    WHEN support_conversations.manager_ended_at IS NOT NULL THEN
      TIMESTAMPDIFF(MINUTE, support_conversations.manager_joined_at, support_conversations.manager_ended_at)

    WHEN support_conversations.closed_at IS NOT NULL THEN
      TIMESTAMPDIFF(MINUTE, support_conversations.manager_joined_at, support_conversations.closed_at)

    ELSE
      TIMESTAMPDIFF(MINUTE, support_conversations.manager_joined_at, NOW())
  END AS manager_age_minutes
")


            // unread_count for this staff member
            ->selectSub(function ($sub) use ($staff) {
                $sub->from('support_messages as sm')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('sm.support_conversation_id', 'support_conversations.id')
                    ->whereIn('sm.sender_type', ['client','coach'])
                    ->where('sm.type', 'message')
                    ->whereRaw(
                        'sm.id > COALESCE((
                          SELECT scr.last_read_message_id
                          FROM support_conversation_reads scr
                          WHERE scr.support_conversation_id = support_conversations.id
                            AND scr.admin_id = ?
                          LIMIT 1
                        ), 0)',
                        [$staff->id]
                    );
            }, 'unread_count')

           ->when($q, function ($qb) use ($q) {
    $qb->where(function ($sub) use ($q) {
        $sub->whereHas('user', function ($u) use ($q) {
            $u->where('username', 'like', "%{$q}%")
              ->orWhere('email', 'like', "%{$q}%");
        })
        ->orWhere('support_conversations.id', $q)
        ->orWhereHas('messages', fn ($m) => $m->where('body','like',"%{$q}%"))
        ->orWhereHas('ratings', fn ($r) => $r->where('feedback','like',"%{$q}%"));
    });
})


            ->when($role !== 'all', fn ($qb) => $qb->where('scope_role', $role))
            ->when($status !== 'all', fn ($qb) => $qb->where('status', $status))
            ->when($dateFrom, fn ($qb) => $qb->whereDate('created_at','>=',$dateFrom))
            ->when($dateTo, fn ($qb) => $qb->whereDate('created_at','<=',$dateTo));

        // ✅ assignment filter uses assigned_staff_id now
        if ($assigned === 'mine') {
            $convs->where('assigned_staff_id', $staff->id);
        } elseif ($assigned === 'unassigned') {
            $convs->whereNull('assigned_staff_id');
        } else {
            // all
        }

        if ($unread === 'unread') {
            $convs->havingRaw('unread_count > 0');
        }

        $convs = $convs
            ->orderByDesc('last_message_at')
            ->paginate($per)
            ->appends($request->query());

        return view('admin.support.index', compact(
            'convs','q','role','status','per','dateFrom','dateTo','assigned','unread'
        ));
    }

    /* =========================================================
     * SHOW
     * ======================================================= */
    public function show(SupportConversation $conversation)
    {
        $staff = $this->staffOrFail();
        $this->abortIfNoAccess($staff, $conversation);

        // load minimal relations
        $conversation->load(['user','assignedStaff','assignedAdmin','ratings.ratedAdmin','manager']);

        // mark read for THIS conversation row
        $this->markReadForStaff($staff, $conversation);

        // THREAD MODE
        $threadId = $conversation->thread_id;

        if ($threadId) {
            $threadConvs = SupportConversation::where('thread_id', $threadId)
                ->where('user_id', $conversation->user_id)
                ->where('scope_role', $conversation->scope_role)
                ->orderBy('id', 'asc')
                ->get();
        } else {
            $threadConvs = collect([$conversation]);
        }

        $threadConvIds = $threadConvs->pluck('id')->all();

        $threadMessages = SupportMessage::query()
            ->with('sender')
            ->whereIn('support_conversation_id', $threadConvIds)
            ->orderBy('id', 'asc')
            ->get();

        $threadRatings = $conversation->ratings;
        if ($threadConvs->count() > 1) {
            $threadRatings = \App\Models\SupportConversationRating::query()
                ->with('ratedAdmin')
                ->whereIn('support_conversation_id', $threadConvIds)
                ->orderBy('id', 'asc')
                ->get();
        }

        $events = collect();

        foreach ($threadMessages as $msg) {
          if ($msg->type === 'system') {
    $events->push((object)[
        'type'  => 'system',
        'at'    => $msg->created_at,
        'model' => $msg, // ✅ keep real message (has id, meta, created_at)
    ]);
} else {

                $events->push((object)[
                    'type'  => 'message',
                    'at'    => $msg->created_at,
                    'model' => $msg,
                ]);
            }
        }

        foreach ($threadRatings as $r) {
            $events->push((object)[
                'type'  => 'rating',
                'at'    => $r->created_at,
                'model' => $r,
            ]);
        }

        $events = $events->sortBy('at')->values();

        $otherConversation = SupportConversation::where('user_id', $conversation->user_id)
            ->where('scope_role', $conversation->scope_role === 'coach' ? 'client' : 'coach')
            ->first();

        return view('admin.support.show', [
            'conversation'      => $conversation,
            'events'            => $events,
            'threadConvs'       => $threadConvs,
            'threadId'          => $threadId,
            'adminTz'           => auth()->user()->timezone ?? config('app.timezone'),
            'otherConversation' => $otherConversation,
        ]);
    }

    /* =========================================================
     * MARK READ (AJAX)
     * ======================================================= */
    public function markRead(Request $request, SupportConversation $conversation)
    {
        $staff = $this->staffOrFail();
        $this->abortIfNoAccess($staff, $conversation);

        $lastId = (int)($request->last_id ?? $conversation->messages()->max('id') ?? 0);
        if ($lastId <= 0) return response()->json(['ok'=>true]);

        SupportConversationRead::updateOrCreate(
            ['support_conversation_id'=>$conversation->id,'admin_id'=>$staff->id],
            ['last_read_message_id'=>$lastId,'last_read_at'=>now()]
        );

        return response()->json(['ok'=>true]);
    }

    /* =========================================================
     * LATEST (POLL)
     * ======================================================= */
    public function latest(Request $request)
    {
        $staff = $this->staffOrFail();

        // keep queues moving
        app(SupportQueueService::class)->dispatchAgents();
        app(SupportQueueService::class)->dispatchManagers();

        $request->validate([
            'conversation_id' => ['required','integer'],
            'after_id'        => ['nullable','integer'],
        ]);

        $conversation = SupportConversation::findOrFail($request->conversation_id);
        $this->abortIfNoAccess($staff, $conversation);

        $threadId = $conversation->thread_id;

        $convIds = [$conversation->id];
        if ($threadId) {
            $convIds = SupportConversation::where('thread_id', $threadId)
                ->where('user_id', $conversation->user_id)
                ->where('scope_role', $conversation->scope_role)
                ->pluck('id')
                ->all();
        }

        $q = SupportMessage::query()
            ->with('sender')
            ->whereIn('support_conversation_id', $convIds)
            ->orderBy('id', 'asc');

        if ($request->after_id) {
            $q->where('id', '>', (int)$request->after_id);
        }

        $msgs = $q->get();

        if ($msgs->isEmpty()) {
            return response()->json(['ok' => true, 'html' => '', 'last' => $request->after_id]);
        }

        $html = '';
        foreach ($msgs as $m) {
            $html .= $m->type === 'system'
                ? view('support._system', ['msg' => $m])->render()
                : view('support._message', ['msg' => $m, 'viewerIsAdmin' => true])->render();
        }

        return response()->json(['ok' => true, 'html' => $html, 'last' => $msgs->last()->id]);
    }

    /* =========================================================
     * SEND MESSAGE
     * - only current owner can reply
     * ======================================================= */
    public function storeMessage(Request $request, SupportConversation $conversation)
    {
        $staff = $this->staffOrFail();
        $this->abortIfNoAccess($staff, $conversation);

        abort_unless($conversation->status === 'open', 403);

        // ✅ only assigned owner can send
        abort_unless((int)$conversation->assigned_staff_id === (int)$staff->id, 403);

        $data = $request->validate([
            'body' => ['required','string','max:5000'],
        ]);

       $isEscalationManager = (
    $staff->role === 'manager'
    && !is_null($conversation->manager_requested_at)
    && (int)$conversation->manager_id === (int)$staff->id
);

$senderType = $isEscalationManager ? 'manager' : 'admin';


        $msg = SupportMessage::create([
            'support_conversation_id' => $conversation->id,
            'sender_id'   => $staff->id,
            'sender_type' => $senderType,
            'type'        => 'message',
            'body'        => $data['body'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        if ($request->expectsJson()) {
          return response()->json([
  'ok'   => true,
  'html' => view('support._message', [
    'msg' => $msg->load('sender'),
    'viewerIsAdmin' => true,
  ])->render(),
  'last' => $msg->id,
]);

        }

        return back()->with('ok', 'Sent');
    }

    /* =========================================================
     * REQUEST MANAGER (ESCALATION)
     * - owner (admin OR manager) can request escalation
     * - ownership becomes NULL until another manager is auto-assigned
     * ======================================================= */
    public function requestManager(Request $request, SupportConversation $conversation)
    {
        $staff = $this->staffOrFail();
        $this->abortIfNoAccess($staff, $conversation);

        abort_unless($conversation->status === 'open', 403);

        // only current owner can escalate
        abort_unless((int)$conversation->assigned_staff_id === (int)$staff->id, 403);

        if ($conversation->manager_requested_at) {
            return back()->withErrors(['manager' => 'Manager has already been requested.']);
        }

        DB::transaction(function () use ($conversation, $staff) {

            // ✅ A) remove ownership immediately
            $conversation->update([
                'manager_requested_at' => now(),
                'manager_requested_by' => $staff->id,
                'status'               => 'waiting_manager',

                // clear current manager assignment/session so queue can assign cleanly
                'manager_id'        => null,
                'manager_joined_at' => null,
                'manager_ended_at'  => null,

                // remove current ownership (A)
                'assigned_staff_id'   => null,
                'assigned_staff_role' => null,
                'assigned_admin_id'   => null,
                'sla_started_at' => null,

            ]);

            SupportMessage::create([
                'support_conversation_id' => $conversation->id,
                'sender_id'   => $staff->id,
                'sender_type' => 'system',
                'type'        => 'system',
                'body'        => '',
                'meta'        => [
                    'event'      => 'manager_requested',
                    'agent_id'   => $staff->id,
                    'agent_name'     => ($staff->username ?: ($staff->email ?? 'Staff')),
'agent_username' => ($staff->username ?? null),

                ],
            ]);
        });

        // ✅ auto-assign another manager immediately (no join click)
        app(SupportQueueService::class)->dispatchManagers();

        return back()->with('ok', 'Escalated to manager. It will be auto-assigned to an available manager.');
    }

    /* =========================================================
     * END CONVERSATION (only owner)
     * ======================================================= */
    public function adminEnd(Request $request, SupportConversation $conversation)
    {
        $staff = $this->staffOrFail();
        $this->abortIfNoAccess($staff, $conversation);

        abort_unless($conversation->status === 'open', 403);
        abort_unless((int)$conversation->assigned_staff_id === (int)$staff->id, 403);

        DB::transaction(function () use ($conversation, $staff) {
            $conversation->update([
                'closed_at'       => now(),
                'closed_by'       => $staff->id,
                'closed_by_role'  => $staff->role, // admin or manager
                'status'          => 'closed',
                'rating_required' => 0,
            ]);

         SupportMessage::create([
  'support_conversation_id' => $conversation->id,
  'sender_id'   => $staff->id,
  'sender_type' => 'system',
  'type'        => 'system',
  'body'        => '',
  'meta'        => [
    'event'          => 'staff_ended',
    'staff_id'       => $staff->id,
    'staff_role'     => $staff->role,
    'staff_username' => $staff->username ?? null,
    'staff_name'     => ($staff->username ?: ($staff->email ?? 'Staff')), // fallback
  ],
]);

        });

        return back()->with('ok', 'Conversation ended.');
    }

    /* =========================================================
     * RESOLVE (rating required) - only owner
     * ======================================================= */
    public function adminResolve(Request $request, SupportConversation $conversation)
    {
        $staff = $this->staffOrFail();
        $this->abortIfNoAccess($staff, $conversation);

        abort_unless($conversation->status === 'open', 403);
        abort_unless((int)$conversation->assigned_staff_id === (int)$staff->id, 403);

        DB::transaction(function () use ($conversation, $staff) {

            // if manager, end manager session too
          $managerFields = [];
if (
    $staff->role === 'manager'
    && !is_null($conversation->manager_requested_at) // only if escalated
) {
    $managerFields['manager_id']        = $staff->id;
    $managerFields['manager_joined_at'] = $conversation->manager_joined_at ?: now();
    $managerFields['manager_ended_at']  = now();
}


            $conversation->update(array_merge($managerFields, [
                'closed_at'       => now(),
                'closed_by'       => $staff->id,
                'closed_by_role'  => $staff->role,
                'status'          => 'resolved',
                'rating_required' => 1,
            ]));

           SupportMessage::create([
  'support_conversation_id' => $conversation->id,
  'sender_id'   => $staff->id,
  'sender_type' => 'system',
  'type'        => 'system',
  'body'        => '',
  'meta'        => [
    'event'          => 'staff_resolved',
    'staff_id'       => $staff->id,
    'staff_role'     => $staff->role,
    'staff_username' => $staff->username ?? null,
    'staff_name'     => ($staff->username ?: ($staff->email ?? 'Staff')), // fallback
  ],
]);

        });

        return back()->with('ok', 'Conversation resolved. User must rate before messaging again.');
    }

    /* =========================================================
     * HELPERS
     * ======================================================= */
    private function staffOrFail()
    {
        $u = auth()->user();
        abort_unless($u && in_array($u->role, ['admin','manager'], true), 403);
        return $u;
    }

    private function abortIfNoAccess($staff, SupportConversation $c): void
    {
        abort_unless(in_array($staff->role, ['admin','manager'], true), 403);
        // ✅ you want all staff can view all chats
    }

    private function markReadForStaff($staff, SupportConversation $conversation): void
    {
        $lastId = (int) ($conversation->messages()->max('id') ?? 0);
        if ($lastId <= 0) return;

        SupportConversationRead::updateOrCreate(
            ['support_conversation_id' => $conversation->id, 'admin_id' => $staff->id],
            ['last_read_message_id' => $lastId, 'last_read_at' => now()]
        );
    }
}
