<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportConversationRead;
use App\Models\SupportConversationRating;
use App\Services\SupportQueueService;

class SuperSupportConversationController extends Controller
{
    /* =========================================================
     * INBOX + STATS + LEADERBOARD
     * ======================================================= */
    public function index(Request $request)
    {
        $staff = $this->superOrFail();

        // -----------------------------
        // BASIC FILTERS
        // -----------------------------
        $q        = (string) $request->query('q', '');
        $status   = (string) $request->query('status', 'open');
        $role     = (string) $request->query('role', 'all');
        $assigned = (string) $request->query('assigned', 'all'); // all/mine/unassigned
        $unread   = (string) $request->query('unread', 'all');   // all/unread

        $per = (int) $request->query('per', 10);
        $per = in_array($per, [10,20,50,100], true) ? $per : 10;

        // -----------------------------
        // RANGE (single window for everything)
        // -----------------------------
        $range = (string) $request->query('range', 'monthly');
        [$rangeStart, $rangeEnd] = $this->resolveRange($request);

        $day   = (string) $request->query('day', '');
        $month = (string) $request->query('month', '');
        $year  = (string) $request->query('year', '');
        $from  = (string) $request->query('from', '');
        $to    = (string) $request->query('to', '');

        // -----------------------------
        // CONVERSATIONS LIST (Inbox table)
        // Range applies on created_at
        // -----------------------------
        $convsQuery = SupportConversation::query()
            ->with(['user','assignedStaff','assignedAdmin','manager'])
            ->select('support_conversations.*')

            // Filter by range window
            ->when($rangeStart, fn ($qb) => $qb->whereBetween('support_conversations.created_at', [$rangeStart, $rangeEnd]))

            // first customer message
            ->selectSub(function ($sub) {
                $sub->from('support_messages as sm_start')
                    ->selectRaw('MIN(sm_start.created_at)')
                    ->whereColumn('sm_start.support_conversation_id', 'support_conversations.id')
                    ->whereIn('sm_start.sender_type', ['client','coach'])
                    ->where('sm_start.type', 'message');
            }, 'first_customer_message_at')

            // Admin SLA timer in minutes
            ->selectRaw("
                CASE
                  WHEN support_conversations.sla_started_at IS NULL THEN NULL
                  WHEN support_conversations.closed_at IS NOT NULL THEN
                    TIMESTAMPDIFF(MINUTE, support_conversations.sla_started_at, support_conversations.closed_at)
                  ELSE
                    TIMESTAMPDIFF(MINUTE, support_conversations.sla_started_at, NOW())
                END AS admin_age_minutes
            ")

            // Manager timer in minutes
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

            // unread_count for THIS superadmin (reads table uses admin_id)
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

            // search: user + id + message + rating feedback
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($sub) use ($q) {
                    $sub->whereHas('user', function ($u) use ($q) {
                        $u->where('username', 'like', "%{$q}%")
                          ->orWhere('email', 'like', "%{$q}%")
                          ->orWhere('first_name', 'like', "%{$q}%")
                          ->orWhere('last_name', 'like', "%{$q}%");
                    })
                    ->orWhere('support_conversations.id', $q)
                    ->orWhereHas('messages', fn ($m) => $m->where('body', 'like', "%{$q}%"))
                    ->orWhereHas('ratings', fn ($r) => $r->where('feedback', 'like', "%{$q}%"));
                });
            })

            // role + status
            ->when($role !== 'all', fn ($qb) => $qb->where('scope_role', $role))
            ->when($status !== 'all', fn ($qb) => $qb->where('status', $status));

        // assigned filter
        if ($assigned === 'mine') {
            $convsQuery->where('assigned_staff_id', $staff->id);
        } elseif ($assigned === 'unassigned') {
            $convsQuery->whereNull('assigned_staff_id');
        }

        // unread filter
        if ($unread === 'unread') {
            $convsQuery->havingRaw('unread_count > 0');
        }

        $convs = $convsQuery
            ->orderByDesc(DB::raw('COALESCE(last_message_at, created_at)'))
            ->paginate($per)
            ->appends($request->query());

        // -----------------------------
        // STATS (Range applies)
        // -----------------------------
        $baseStats = SupportConversation::query()
            ->when($rangeStart, fn ($qb) => $qb->whereBetween('created_at', [$rangeStart, $rangeEnd]));

        $stats = [
            'open'            => (clone $baseStats)->where('status', 'open')->count(),
            'closed'          => (clone $baseStats)->where('status', 'closed')->count(),
            'resolved'        => (clone $baseStats)->where('status', 'resolved')->count(),

            'unassigned_open' => (clone $baseStats)->where('status', 'open')->whereNull('assigned_staff_id')->count(),
            'assigned_open'   => (clone $baseStats)->where('status', 'open')->whereNotNull('assigned_staff_id')->count(),

            'manager_waiting' => (clone $baseStats)->where('status', 'waiting_manager')
                                                  ->whereNotNull('manager_requested_at')
                                                  ->whereNull('manager_joined_at')
                                                  ->count(),

            'manager_active'  => (clone $baseStats)->where('status', 'open')
                                                  ->whereNotNull('manager_joined_at')
                                                  ->whereNull('manager_ended_at')
                                                  ->count(),
        ];

        // -----------------------------
        // LEADERBOARD (Range applies)
        // Ratings -> by ratings.created_at
        // Manager requests -> by conversations.manager_requested_at, grouped by manager_requested_by
        // -----------------------------
        $agentRatings = SupportConversationRating::query()
            ->select([
                'admin_id',
                DB::raw('COUNT(*) as ratings_count'),
                DB::raw('AVG(stars) as avg_stars'),
                DB::raw('SUM(CASE WHEN stars = 5 THEN 1 ELSE 0 END) as five_star_count'),
            ])
            ->whereNotNull('admin_id')
            ->when($rangeStart, fn ($qb) => $qb->whereBetween('created_at', [$rangeStart, $rangeEnd]))
            ->groupBy('admin_id');

        $managerRequests = SupportConversation::query()
            ->select([
                'manager_requested_by',
                DB::raw('COUNT(*) as manager_requests'),
            ])
            ->whereNotNull('manager_requested_by')
            ->whereNotNull('manager_requested_at')
            ->when($rangeStart, fn ($qb) => $qb->whereBetween('manager_requested_at', [$rangeStart, $rangeEnd]))
            ->groupBy('manager_requested_by');

        $agentLeaderboard = DB::query()
            ->fromSub($agentRatings, 'r')
            ->join('users as u', 'u.id', '=', 'r.admin_id')
            ->leftJoinSub($managerRequests, 'm', function ($j) {
                $j->on('m.manager_requested_by', '=', 'u.id');
            })
            ->whereIn(DB::raw('LOWER(COALESCE(u.role,""))'), ['admin','manager','super_admin','superadmin'])
            ->select([
                'u.id',
                'u.first_name',
                'u.last_name',
                'u.email',
                'u.role',
                'r.ratings_count',
                'r.avg_stars',
                'r.five_star_count',
                DB::raw('CASE WHEN r.ratings_count > 0 THEN (r.five_star_count / r.ratings_count) * 100 ELSE 0 END as five_star_pct'),
                DB::raw('COALESCE(m.manager_requests, 0) as manager_requests'),
            ])
            ->orderByDesc('r.avg_stars')
            ->orderByDesc('r.ratings_count')
            ->limit(20)
            ->get();

        return view('superadmin.support.index', compact(
            'convs',
            'q','status','role','per','assigned','unread',
            'stats','agentLeaderboard',
            'range','day','month','year','from','to'
        ));
    }

    /**
     * Resolve range -> returns [start, end]
     */
    private function resolveRange(Request $request): array
    {
        $range = (string) $request->query('range', 'monthly');

        // defaults
        $day   = (string) $request->query('day', now()->toDateString()); // YYYY-MM-DD
        $month = (string) $request->query('month', now()->format('Y-m')); // YYYY-MM
        $year  = (string) $request->query('year', now()->format('Y')); // YYYY

        $from  = (string) $request->query('from', '');
        $to    = (string) $request->query('to', '');

        switch ($range) {
            case 'daily': {
                $d = Carbon::parse($day);
                return [$d->copy()->startOfDay(), $d->copy()->endOfDay()];
            }

            case 'weekly':
                return [now()->startOfWeek(), now()->endOfWeek()];

            case 'monthly': {
                try {
                    $m = Carbon::createFromFormat('Y-m', $month);
                } catch (\Throwable $e) {
                    $m = now();
                }
                return [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()];
            }

            case 'yearly': {
                $y = (int) $year;
                if ($y < 2000 || $y > 2100) $y = (int) now()->format('Y');
                $d = Carbon::createFromDate($y, 1, 1);
                return [$d->copy()->startOfYear(), $d->copy()->endOfYear()];
            }

            case 'custom': {
                $start = $from ? Carbon::parse($from)->startOfDay() : null;
                $end   = $to   ? Carbon::parse($to)->endOfDay()     : now();
                return [$start, $end];
            }

            case 'lifetime':
            default:
                return [null, now()];
        }
    }

    /* =========================================================
     * SHOW (thread mode + ratings included)
     * ======================================================= */
    public function show(SupportConversation $conversation)
    {
        $staff = $this->superOrFail();

        $conversation->load(['user','assignedStaff','assignedAdmin','ratings.ratedAdmin','manager']);

        // mark read
        $this->markReadForStaff($staff, $conversation);

        // thread mode
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

        // ratings for entire thread
        $threadRatings = $threadConvs->count() > 1
            ? SupportConversationRating::query()
                ->with('ratedAdmin')
                ->whereIn('support_conversation_id', $threadConvIds)
                ->orderBy('id', 'asc')
                ->get()
            : $conversation->ratings;

        $events = collect();

        foreach ($threadMessages as $msg) {
            $events->push((object)[
                'type'  => ($msg->type === 'system' ? 'system' : 'message'),
                'at'    => $msg->created_at,
                'model' => $msg,
            ]);
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

        $adminTz = auth()->user()->timezone ?? config('app.timezone');

        return view('superadmin.support.show', [
            'conversation'      => $conversation,
            'events'            => $events,
            'threadConvs'       => $threadConvs,
            'threadId'          => $threadId,
            'adminTz'           => $adminTz,
            'otherConversation' => $otherConversation,
        ]);
    }

    /* =========================================================
     * SEARCH (AJAX)
     * ======================================================= */
    public function search(Request $request, SupportConversation $conversation)
    {
        $staff = $this->superOrFail();

        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['ok' => true, 'count' => 0, 'html' => '']);
        }

        $threadId = $conversation->thread_id;
        $convIds = [$conversation->id];

        if ($threadId) {
            $convIds = SupportConversation::where('thread_id', $threadId)
                ->where('user_id', $conversation->user_id)
                ->where('scope_role', $conversation->scope_role)
                ->pluck('id')
                ->all();
        }

        $hits = SupportMessage::query()
            ->whereIn('support_conversation_id', $convIds)
            ->where('type', 'message')
            ->where('body', 'like', "%{$q}%")
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id','body','created_at','support_conversation_id']);

        $count = $hits->count();

        $html = '';
        foreach ($hits as $m) {
            $snippet = mb_substr(strip_tags((string)$m->body), 0, 120);
            $html .= '
              <div class="zv-chat-search-hit" data-jump-id="'.$m->id.'" style="cursor:pointer;padding:10px 12px;border-bottom:1px solid rgba(0,0,0,.08)">
                <div class="small text-muted">'.e($m->created_at?->format('d M Y, H:i')).'</div>
                <div>'.e($snippet).'</div>
              </div>
            ';
        }

        return response()->json(['ok' => true, 'count' => $count, 'html' => $html]);
    }

    /* =========================================================
     * LATEST (poll)
     * ======================================================= */
    public function latest(Request $request)
    {
        $staff = $this->superOrFail();

        app(SupportQueueService::class)->dispatchAgents();
        app(SupportQueueService::class)->dispatchManagers();

        $request->validate([
            'conversation_id' => ['required','integer'],
            'after_id'        => ['nullable','integer'],
            'mark_read'       => ['nullable','boolean'],
        ]);

        $conversation = SupportConversation::findOrFail((int)$request->conversation_id);

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

        $lastId = (int)$msgs->last()->id;

        if ($request->boolean('mark_read') && $lastId > 0) {
            SupportConversationRead::updateOrCreate(
                ['support_conversation_id'=>$conversation->id,'admin_id'=>$staff->id],
                ['last_read_message_id'=>$lastId,'last_read_at'=>now()]
            );
        }

        return response()->json(['ok' => true, 'html' => $html, 'last' => $lastId]);
    }

    /* =========================================================
     * SEND MESSAGE (owner-only)
     * ======================================================= */
    public function storeMessage(Request $request, SupportConversation $conversation)
    {
        $staff = $this->superOrFail();

        abort_unless($conversation->status === 'open', 403);

        // only assigned owner can send
        abort_unless((int)$conversation->assigned_staff_id === (int)$staff->id, 403);

        $data = $request->validate([
            'body' => ['required','string','max:5000'],
        ]);

        $msg = SupportMessage::create([
            'support_conversation_id' => $conversation->id,
            'sender_id'   => $staff->id,
            'sender_type' => 'superadmin',
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
     * UPDATE STATUS
     * ======================================================= */
    public function updateStatus(Request $request, SupportConversation $conversation)
    {
        $staff = $this->superOrFail();

        $data = $request->validate([
            'status' => ['required','string','in:open,waiting_manager,resolved,closed,auto_closed'],
        ]);

        $conversation->update(['status' => $data['status']]);

        return back()->with('ok', 'Status updated.');
    }

    /* =========================================================
     * ASSIGN ME
     * ======================================================= */
    public function assignMe(Request $request, SupportConversation $conversation)
    {
        $staff = $this->superOrFail();

        abort_unless(in_array($conversation->status, ['open','waiting_manager'], true), 403);

        DB::transaction(function () use ($conversation, $staff) {
            $conversation->update([
                'assigned_staff_id'   => $staff->id,
                'assigned_staff_role' => 'superadmin',
                'assigned_admin_id'   => $staff->id,  // compatibility
                'assigned_at'         => now(),
                'status'              => 'open',
                'sla_started_at'      => $conversation->sla_started_at ?: now(),
            ]);

            SupportMessage::create([
                'support_conversation_id' => $conversation->id,
                'sender_id'   => $staff->id,
                'sender_type' => 'system',
                'type'        => 'system',
                'body'        => '',
                'meta'        => [
                    'event'          => 'staff_assigned',
                    'staff_id'       => $staff->id,
                    'staff_role'     => 'superadmin',
                    'staff_username' => $staff->username ?? null,
                    'staff_name'     => ($staff->username ?: ($staff->email ?? 'SuperAdmin')),
                ],
            ]);
        });

        return back()->with('ok', 'Assigned to you.');
    }

    /* =========================================================
     * REQUEST MANAGER (escalation)
     * ======================================================= */
    public function requestManager(Request $request, SupportConversation $conversation)
    {
        $staff = $this->superOrFail();

        abort_unless($conversation->status === 'open', 403);
        abort_unless((int)$conversation->assigned_staff_id === (int)$staff->id, 403);

        if ($conversation->manager_requested_at) {
            return back()->withErrors(['manager' => 'Manager has already been requested.']);
        }

        DB::transaction(function () use ($conversation, $staff) {
            $conversation->update([
                'manager_requested_at' => now(),
                'manager_requested_by' => $staff->id,
                'status'               => 'waiting_manager',

                'manager_id'        => null,
                'manager_joined_at' => null,
                'manager_ended_at'  => null,

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
                    'event'          => 'manager_requested',
                    'agent_id'       => $staff->id,
                    'agent_name'     => ($staff->username ?: ($staff->email ?? 'SuperAdmin')),
                    'agent_username' => ($staff->username ?? null),
                    'agent_role'     => 'superadmin',
                ],
            ]);
        });

        app(SupportQueueService::class)->dispatchManagers();

        return back()->with('ok', 'Escalated to manager. It will be auto-assigned.');
    }

    /* =========================================================
     * MANAGER JOIN (super override)
     * ======================================================= */
    public function managerJoin(Request $request, SupportConversation $conversation)
    {
        $staff = $this->superOrFail();

        abort_unless(!is_null($conversation->manager_requested_at), 403);

        DB::transaction(function () use ($conversation, $staff) {
            $conversation->update([
                'manager_id'        => $staff->id,
                'manager_joined_at' => $conversation->manager_joined_at ?: now(),
                'manager_ended_at'  => null,
                'status'            => 'open',

                'assigned_staff_id'   => $staff->id,
                'assigned_staff_role' => 'manager',
                'assigned_admin_id'   => $staff->id,
                'sla_started_at'      => $conversation->sla_started_at ?: now(),
            ]);

            SupportMessage::create([
                'support_conversation_id' => $conversation->id,
                'sender_id'   => $staff->id,
                'sender_type' => 'system',
                'type'        => 'system',
                'body'        => '',
                'meta'        => [
                    'event'          => 'manager_joined',
                    'staff_id'       => $staff->id,
                    'staff_role'     => 'superadmin',
                    'staff_username' => $staff->username ?? null,
                    'staff_name'     => ($staff->username ?: ($staff->email ?? 'SuperAdmin')),
                ],
            ]);
        });

        return back()->with('ok', 'Manager joined (superadmin override).');
    }

    /* =========================================================
     * MANAGER END (super override)
     * ======================================================= */
    public function managerEnd(Request $request, SupportConversation $conversation)
    {
        $staff = $this->superOrFail();

        abort_unless(!is_null($conversation->manager_requested_at), 403);

        DB::transaction(function () use ($conversation, $staff) {
            $conversation->update([
                'manager_ended_at' => now(),

                'assigned_staff_id'   => null,
                'assigned_staff_role' => null,
                'assigned_admin_id'   => null,

                'status' => in_array($conversation->status, ['closed','resolved','auto_closed'], true)
                    ? $conversation->status
                    : 'open',
            ]);

            SupportMessage::create([
                'support_conversation_id' => $conversation->id,
                'sender_id'   => $staff->id,
                'sender_type' => 'system',
                'type'        => 'system',
                'body'        => '',
                'meta'        => [
                    'event'          => 'manager_ended',
                    'staff_id'       => $staff->id,
                    'staff_role'     => 'superadmin',
                    'staff_username' => $staff->username ?? null,
                    'staff_name'     => ($staff->username ?: ($staff->email ?? 'SuperAdmin')),
                ],
            ]);
        });

        app(SupportQueueService::class)->dispatchAgents();

        return back()->with('ok', 'Manager session ended. Conversation released.');
    }

    /* =========================================================
     * HELPERS
     * ======================================================= */
    private function superOrFail()
    {
        $u = auth()->user();
        abort_unless($u && in_array(($u->role ?? ''), ['superadmin','super_admin'], true), 403);
        return $u;
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