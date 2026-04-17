<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\DisputeMessage;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ReservationSettlementService;
use App\Services\DisputeQueueService;

class AdminDisputeController extends Controller
{
    /**
     * ✅ GET /admin/disputes
     * - auto-dispatch queue on every inbox load
     */
    public function index(Request $request)
    {
        app(DisputeQueueService::class)->dispatchAgents();

        $status = $request->query('status'); // open|opened|in_review|resolved|rejected
        $q      = trim((string) $request->query('q', ''));

        $rows = Dispute::query()
            ->with(['reservation.service', 'reservation.client', 'reservation.service.coach','assignedStaff',])
            ->when($status, fn ($qq) => $qq->where('status', $status))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    if (ctype_digit($q)) {
                        $w->orWhere('id', (int) $q)
                            ->orWhere('reservation_id', (int) $q)
                            ->orWhere('opened_by_user_id', (int) $q)
                            ->orWhere('assigned_staff_id', (int) $q);
                    }

                    $w->orWhere('title_label', 'like', "%{$q}%")
                        ->orWhere('status', 'like', "%{$q}%")
                        ->orWhere('opened_by_role', 'like', "%{$q}%")
                        ->orWhereHas('reservation.service', fn ($s) => $s->where('title', 'like', "%{$q}%"))
                        ->orWhereHas('reservation.client', fn ($c) => $c->where('username', 'like', "%{$q}%"))
                        ->orWhereHas('reservation.service.coach', fn ($c) => $c->where('username', 'like', "%{$q}%"));
                });
            })
            ->orderByRaw("FIELD(status,'open','opened','in_review','resolved','rejected')")
            ->orderByDesc('last_message_at')
            ->paginate(15)
            ->withQueryString();

        // ✅ add computed "days_in_review" for UI (optional)
        $rows->getCollection()->transform(function ($d) {
            $d->days_in_review = null;
            if (($d->status === 'in_review') && !empty($d->in_review_started_at)) {
                $start = $d->in_review_started_at instanceof \Carbon\CarbonInterface
                    ? $d->in_review_started_at
                    : \Carbon\Carbon::parse($d->in_review_started_at);

                $d->days_in_review = max(0, $start->startOfDay()->diffInDays(now()->startOfDay()));
            }
            return $d;
        });

        return view('admin.disputes.index', compact('rows'));
    }

    /**
     * ✅ GET /admin/disputes/{dispute}
     * - manager: can read any
     * - admin: can read ONLY if assigned
     */
   public function show(Dispute $dispute)
{
    app(DisputeQueueService::class)->dispatchAgents();

    $staff      = $this->staffOrFail();
    $isManager  = ((string) $staff->role) === 'manager';
    $isAssigned = (int)($dispute->assigned_staff_id ?? 0) === (int)$staff->id;

    // ✅ NEW RULE:
    // Admin can read ANY dispute (no abort here).
    // Manager can read ANY dispute (already).
    // Only actions (message/finalize/close) will be restricted.

    $dispute->load([
        'reservation.service',
        'reservation.client',
        'reservation.service.coach',
        'messages.sender',
        'messages.attachments',
        'latestSummaryBy',
        'summaries.staff' => fn ($q) => $q->latest()->limit(20),
    ]);

    $summaries = $dispute->summaries()->with('staff')->latest()->limit(20)->get();

    $clientMessages = $dispute->messages
        ->filter(fn ($m) => ($m->channel === 'client') || ($m->target_role === 'client'))
        ->values();

    $coachMessages = $dispute->messages
        ->filter(fn ($m) => ($m->channel === 'coach') || ($m->target_role === 'coach'))
        ->values();

    $takenByName = null;
    if (!empty($dispute->assigned_staff_id)) {
        $takenByName = \App\Models\Users::find((int) $dispute->assigned_staff_id)?->username
            ?? ('#' . (int) $dispute->assigned_staff_id);
    }

    $finalized   = $this->isDisputeFinalized($dispute);
    $isTakenByMe = $isAssigned;
    $isTaken     = !empty($dispute->assigned_staff_id);

    $daysInReview = null;
    if (($dispute->status === 'in_review') && !empty($dispute->in_review_started_at)) {
        $start = $dispute->in_review_started_at instanceof \Carbon\CarbonInterface
            ? $dispute->in_review_started_at
            : \Carbon\Carbon::parse($dispute->in_review_started_at);

        $daysInReview = max(0, $start->startOfDay()->diffInDays(now()->startOfDay()));
    }

    // ✅ pass permissions to view so UI can disable controls
    $canAct = $isManager || $isAssigned;

    return view('admin.disputes.show', compact(
        'dispute',
        'clientMessages',
        'coachMessages',
        'isTakenByMe',
        'isTaken',
        'takenByName',
        'finalized',
        'summaries',
        'daysInReview',
        'canAct'
    ));
}

    /**
     * ✅ POST /admin/disputes/{dispute}/message
     * - manager can message ANY dispute anytime
     * - admin can message ONLY if assigned to them
     * - block if finalized
     */
    public function message(Request $request, Dispute $dispute)
    {
        $staff     = $this->staffOrFail();
        $isManager = ((string) $staff->role) === 'manager';

        if ($this->isDisputeFinalized($dispute)) {
            return back()->with('error', 'This dispute is finalized. You cannot send messages.');
        }

        // ✅ Admin must be assigned; Manager can message anywhere
        if (!$isManager) {
            if ($resp = $this->assertAssignedToMeOrBack($dispute, $staff)) {
                return $resp;
            }
        }

        $data = $request->validate([
            'message'     => ['required', 'string', 'max:4000'],
            'target_role' => ['required', 'in:client,coach'],
        ]);

        DB::transaction(function () use ($dispute, $staff, $data) {
            DisputeMessage::create([
                'dispute_id'     => (int) $dispute->id,
                'sender_user_id' => (int) $staff->id,
                'sender_role'    => (string) $staff->role, // admin|manager
                'target_role'    => $data['target_role'],
                'channel'        => $data['target_role'],
                'message'        => $data['message'],
            ]);

            // ✅ If dispute is in_review, keep it in_review (manager reminders must not reopen it)
            $nextStatus = ($dispute->status === 'in_review') ? 'in_review' : 'opened';

            $dispute->forceFill([
                'status'          => $nextStatus,
                'last_message_at' => now(),
                'updated_at'      => now(),
            ])->save();
        });

        return back()->with('ok', 'Message sent.');
    }

    /**
     * ✅ POST /admin/disputes/{dispute}/finalize
     * - manager can finalize ANY dispute anytime
     * - admin can finalize ONLY if assigned to them
     * - uses decided_by_staff_id/resolved_by_staff_id (renamed cols)
     */
    public function finalize(
        Request $request,
        Dispute $dispute,
        ReservationSettlementService $settlement
    ) {
        $staff     = $this->staffOrFail();
        $isManager = ((string) $staff->role) === 'manager';

        $data = $request->validate([
            'action' => ['required', 'in:reject_dispute,refund_full_amount,refund_service_only,pay_coach'],
            'note'   => ['nullable', 'string', 'max:5000'],
        ]);

        return DB::transaction(function () use ($data, $staff, $isManager, $dispute, $settlement) {

            $dispute = Dispute::lockForUpdate()->findOrFail($dispute->id);

            if ($this->isDisputeFinalized($dispute)) {
                return back()->with('error', 'This dispute is already finalized.');
            }

            // ✅ Admin must be assigned; Manager can finalize any
            if (!$isManager) {
                if ($resp = $this->assertAssignedToMeOrBack($dispute, $staff)) {
                    return $resp;
                }
            }

            $res = Reservation::lockForUpdate()
                ->with(['payment', 'service', 'slots'])
                ->findOrFail((int) $dispute->reservation_id);

            $now = now();

            $actionMap = [
                'reject_dispute'      => 'reject',
                'refund_full_amount'  => 'refund_full',
                'refund_service_only' => 'refund_service',
                'pay_coach'           => 'pay_coach',
            ];

            $dbAction = $actionMap[$data['action']] ?? $data['action'];

            $applyDecision = function () use ($dispute, $data, $staff, $now, $dbAction) {
                $dispute->forceFill([
                    'decision_action'     => $dbAction,
                    'decision_note'       => $data['note'] ?? null,
                    'decided_by_staff_id' => (int) $staff->id,  // ✅ renamed
                    'decided_at'          => $now,
                    'updated_at'          => $now,
                ])->save();
            };

            $closeDispute = function (string $status) use ($dispute, $staff, $now, $applyDecision) {
                $applyDecision();

                $dispute->forceFill([
                    'status'              => $status, // resolved|rejected
                    'resolved_by_staff_id'=> (int) $staff->id,   // ✅ renamed
                    'resolved_at'         => $now,

                    // clear assignment + SLA
                    'assigned_staff_id'   => null,
                    'assigned_staff_role' => null,
                    'assigned_at'         => null,
                    'sla_started_at'      => null,

                    // if finalized, review timer no longer matters
                    'in_review_started_at'=> null,

                    'updated_at'          => $now,
                ])->save();
            };

            switch ($data['action']) {

                case 'reject_dispute':
                    $closeDispute('rejected');
                    $settlement->recompute((int) $res->id);
                    return back()->with('ok', 'Dispute rejected.');

                case 'refund_full_amount': {
                    $result = $settlement->adminRefundFullAmountDecision($res, (int) $staff->id);
                    $closeDispute('resolved');
                    return back()->with('ok', $result['message'] ?? 'Decision saved.');
                }

                case 'refund_service_only': {
                    $result = $settlement->adminRefundServiceOnlyDecision($res, (int) $staff->id);
                    $closeDispute('resolved');
                    return back()->with('ok', $result['message'] ?? 'Decision saved.');
                }

                case 'pay_coach':
                 $settlement->adminPayCoachNow($res);

// refresh after settlement call
$res->refresh();

// ✅ do not force 'paid' here. Settlement service will set it only if payout happened.
// ✅ optional: clear refund totals only if paid
if (strtolower((string)$res->settlement_status) === 'paid') {
    $res->forceFill([
        'refund_total_minor' => 0,
    ])->save();
}

                    $closeDispute('resolved');
                    return back()->with('ok', 'Coach Has Been Paid (Staff Decision).');
            }

            return back()->with('error', 'Unknown action.');
        });
    }

    /**
     * ✅ POST /admin/disputes/{dispute}/close
     * - ONLY assigned staff can close conversation to in_review
     * - manager is NOT special here unless assigned (your rule: "put into in_review by assigned agent")
     */
    public function closeConversation(Request $request, Dispute $dispute)
    {
        $staff = $this->staffOrFail();

        if ($this->isDisputeFinalized($dispute)) {
            return back()->with('error', 'This dispute is finalized. You cannot send it back to queue.');
        }

        // ✅ must be assigned (admin or manager)
        if ($resp = $this->assertAssignedToMeOrBack($dispute, $staff)) {
            return $resp;
        }

        $data = $request->validate([
            'summary' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $ok = app(DisputeQueueService::class)
            ->closeToInReview((int) $dispute->id, (int) $staff->id, $data['summary']);

        return $ok
            ? back()->with('ok', 'Conversation closed and summary saved.')
            : back()->with('error', 'Unable to close this dispute.');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function staffOrFail()
    {
        $u = auth()->user();
        abort_unless($u && in_array($u->role, ['admin', 'manager'], true), 403);
        return $u;
    }

    private function isDisputeFinalized(Dispute $dispute): bool
    {
        $st = strtolower((string) ($dispute->status ?? 'open'));
        return !empty($dispute->resolved_at) || in_array($st, ['resolved', 'rejected'], true);
    }

    /**
     * Returns redirect response if not allowed; otherwise null.
     */
    private function assertAssignedToMeOrBack(Dispute $dispute, $staff)
    {
        if (empty($dispute->assigned_staff_id)) {
            return back()->with('error', 'This dispute is not assigned yet. Please refresh.');
        }

        if ((int) $dispute->assigned_staff_id !== (int) $staff->id) {
            return back()->with('error', 'This dispute is assigned to another staff member.');
        }

        return null;
    }
}