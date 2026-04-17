<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\DisputeMessage;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ReservationSettlementService;
use App\Services\DisputeQueueService;

class SuperDisputeController extends Controller
{
    /**
     * ✅ GET /superadmin/disputes
     * - Full access inbox
     * - Still auto-dispatch queue on inbox load (optional but consistent with your system)
     */
    public function index(Request $request)
    {
        app(DisputeQueueService::class)->dispatchAgents();

        $status = $request->query('status'); // open|opened|in_review|resolved|rejected
        $q      = trim((string) $request->query('q', ''));

        $rows = Dispute::query()
            ->with([
                'reservation.service',
                'reservation.client',
                'reservation.service.coach',
                'assignedStaff',
            ])
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

        return view('superadmin.disputes.index', compact('rows'));
    }

    /**
     * ✅ GET /superadmin/disputes/{dispute}
     * - Full access read
     * - Full access actions too (view decides UI)
     */
    public function show(Dispute $dispute)
    {
        app(DisputeQueueService::class)->dispatchAgents();

        $staff = $this->superStaffOrFail();

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

        // For UI (superadmin always can act unless finalized)
        $canAct = true;

        $daysInReview = null;
        if (($dispute->status === 'in_review') && !empty($dispute->in_review_started_at)) {
            $start = $dispute->in_review_started_at instanceof \Carbon\CarbonInterface
                ? $dispute->in_review_started_at
                : \Carbon\Carbon::parse($dispute->in_review_started_at);

            $daysInReview = max(0, $start->startOfDay()->diffInDays(now()->startOfDay()));
        }

        return view('superadmin.disputes.show', compact(
            'dispute',
            'clientMessages',
            'coachMessages',
            'takenByName',
            'finalized',
            'summaries',
            'daysInReview',
            'canAct'
        ));
    }

    /**
     * ✅ POST /superadmin/disputes/{dispute}/message
     * - Full access (no assignment restriction)
     * - Block if finalized
     */
    public function message(Request $request, Dispute $dispute)
    {
        $staff = $this->superStaffOrFail();

        if ($this->isDisputeFinalized($dispute)) {
            return back()->with('error', 'This dispute is finalized. You cannot send messages.');
        }

        $data = $request->validate([
            'message'     => ['required', 'string', 'max:4000'],
            'target_role' => ['required', 'in:client,coach'],
        ]);

        DB::transaction(function () use ($dispute, $staff, $data) {
            DisputeMessage::create([
                'dispute_id'     => (int) $dispute->id,
                'sender_user_id' => (int) $staff->id,
                'sender_role'    => (string) $staff->role, // superadmin
                'target_role'    => $data['target_role'],
                'channel'        => $data['target_role'],
                'message'        => $data['message'],
            ]);

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
     * ✅ POST /superadmin/disputes/{dispute}/finalize
     * - Full access finalize (no assignment restriction)
     */
    public function finalize(
        Request $request,
        Dispute $dispute,
        ReservationSettlementService $settlement
    ) {
        $staff = $this->superStaffOrFail();

        $data = $request->validate([
            'action' => ['required', 'in:reject_dispute,refund_full_amount,refund_service_only,pay_coach'],
            'note'   => ['nullable', 'string', 'max:5000'],
        ]);

        return DB::transaction(function () use ($data, $staff, $dispute, $settlement) {

            $dispute = Dispute::lockForUpdate()->findOrFail($dispute->id);

            if ($this->isDisputeFinalized($dispute)) {
                return back()->with('error', 'This dispute is already finalized.');
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
                    'decided_by_staff_id' => (int) $staff->id,
                    'decided_at'          => $now,
                    'updated_at'          => $now,
                ])->save();
            };

            $closeDispute = function (string $status) use ($dispute, $staff, $now, $applyDecision) {
                $applyDecision();

                $dispute->forceFill([
                    'status'               => $status, // resolved|rejected
                    'resolved_by_staff_id' => (int) $staff->id,
                    'resolved_at'          => $now,

                    // clear assignment + SLA
                    'assigned_staff_id'    => null,
                    'assigned_staff_role'  => null,
                    'assigned_at'          => null,
                    'sla_started_at'       => null,

                    'in_review_started_at' => null,
                    'updated_at'           => $now,
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

                    $res->refresh();

                    if (strtolower((string)$res->settlement_status) === 'paid') {
                        $res->forceFill(['refund_total_minor' => 0])->save();
                    }

                    $closeDispute('resolved');
                    return back()->with('ok', 'Coach Has Been Paid (Superadmin Decision).');
            }

            return back()->with('error', 'Unknown action.');
        });
    }

    /**
     * ✅ POST /superadmin/disputes/{dispute}/close
     * - Full access close to in_review (no assignment restriction)
     */
    public function closeConversation(Request $request, Dispute $dispute)
    {
        $staff = $this->superStaffOrFail();

        if ($this->isDisputeFinalized($dispute)) {
            return back()->with('error', 'This dispute is finalized. You cannot send it back to queue.');
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

    private function superStaffOrFail()
    {
        $u = auth()->user();
        abort_unless($u && ((string)$u->role === 'superadmin'), 403);
        return $u;
    }

    private function isDisputeFinalized(Dispute $dispute): bool
    {
        $st = strtolower((string) ($dispute->status ?? 'open'));
        return !empty($dispute->resolved_at) || in_array($st, ['resolved', 'rejected'], true);
    }
}