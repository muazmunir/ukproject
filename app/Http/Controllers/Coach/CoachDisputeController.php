<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\DisputeAttachment;
use App\Models\DisputeMessage;
use App\Models\Reservation;
use App\Services\DisputeQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CoachDisputeController extends Controller
{
    public function index()
    {
        $uid = (int) auth()->id();

        $disputes = Dispute::with([
                'reservation.service',
                'reservation.package',

                // ✅ handled by agent on listing
                'assignedStaff:id,username,email',
            ])
            ->where('coach_id', $uid)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('coach.disputes.index', compact('disputes'));
    }

    public function create(Reservation $reservation)
    {
        $uid = (int) auth()->id();

        $reservation->loadMissing('service');

        $coachId = (int) ($reservation->service?->coach_id ?? $reservation->coach_id);
        abort_unless($coachId === $uid, 403);

        // ✅ if ANY dispute exists (client OR coach), redirect to it
        $reservation->loadMissing('dispute');
        if ($reservation->dispute) {
            return redirect()->route('coach.disputes.show', $reservation->dispute);
        }

        $titles = config('disputes.titles.coach', []);
        return view('coach.disputes.create', compact('reservation', 'titles'));
    }

    public function store(Request $r, Reservation $reservation)
    {
        $uid = (int) auth()->id();

        $reservation->loadMissing('service');

        $coachId = (int) ($reservation->service?->coach_id ?? $reservation->coach_id);
        abort_unless($coachId === $uid, 403);

        $reservation->loadMissing('dispute');
        if ($reservation->dispute) {
            return redirect()->route('coach.disputes.show', $reservation->dispute)
                ->with('info', 'This booking is already in dispute.');
        }

        $titles = config('disputes.titles.coach', []);
        $allowedKeys = array_keys($titles);

        $data = $r->validate([
            'title_key'    => ['required', Rule::in($allowedKeys)],
            'description'  => ['required', 'string', 'max:5000'],
            'files.*'      => ['nullable', 'file', 'max:20480',
                'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime'
            ],
        ]);

        return DB::transaction(function () use ($reservation, $uid, $data, $r, $titles) {

            $res = Reservation::lockForUpdate()
                ->with(['service', 'dispute'])
                ->findOrFail((int) $reservation->id);

            $coachId = (int) ($res->service?->coach_id ?? $res->coach_id);
            abort_unless($coachId === $uid, 403);

            if ($res->dispute) {
                return redirect()->route('coach.disputes.show', $res->dispute)
                    ->with('info', 'This booking is already in dispute.');
            }

            $now = now();

            $dispute = Dispute::create([
                'reservation_id'      => (int) $res->id,
                'opened_by_role'      => 'coach',
                'opened_by_user_id'   => (int) $uid,

                'client_id'           => (int) $res->client_id,
                'coach_id'            => (int) ($res->service?->coach_id ?? $res->coach_id),

                'title_key'           => $data['title_key'],
                'title_label'         => $titles[$data['title_key']] ?? $data['title_key'],
                'description'         => $data['description'],

                // ✅ starts in queue
                'status'               => 'open',
                'last_message_at'      => $now,
                'last_party_message_at'=> $now,

                // ✅ review timer starts empty
                'in_review_started_at' => null,

                // ✅ assignment starts empty
                'assigned_staff_id'    => null,
                'assigned_staff_role'  => null,
                'assigned_at'          => null,

                // ✅ SLA starts empty until staff assignment
                'sla_started_at'       => null,
            ]);

            $msg = DisputeMessage::create([
                'dispute_id'      => (int) $dispute->id,
                'sender_user_id'  => (int) $uid,
                'sender_role'     => 'coach',
                'channel'         => 'coach',
                'target_role'     => 'coach',
                'message'         => $data['description'],
            ]);

            if ($r->hasFile('files')) {
                foreach ($r->file('files') as $file) {
                    if (!$file) continue;

                    $path = $file->store("disputes/{$dispute->id}", 'public');

                    DisputeAttachment::create([
                        'dispute_id' => (int) $dispute->id,
                        'message_id' => (int) $msg->id,
                        'disk'       => 'public',
                        'path'       => $path,
                        'filename'   => $file->getClientOriginalName(),
                        'mime'       => $file->getMimeType(),
                        'size'       => $file->getSize(),
                    ]);
                }
            }

            $res->forceFill([
                'disputed_by_coach_at' => $now,
                'settlement_status'    => 'in_dispute',
            ])->save();

            DB::afterCommit(function () {
                app(DisputeQueueService::class)->dispatchAgents();
            });

            return redirect()
                ->route('coach.disputes.show', $dispute)
                ->with('success', 'Dispute submitted. Staff will review.');
        });
    }

    public function show(Dispute $dispute)
    {
        $uid = (int) auth()->id();
        abort_unless((int) $dispute->coach_id === $uid, 403);

        $dispute->load([
            'reservation.service.coach',
            'reservation.package',

            // ✅ handled by + decision display + latest summary optional
            'assignedStaff:id,username,email',
            'decidedBy:id,username,email',
            'resolvedBy:id,username,email',
            'latestSummaryBy:id,username,email',

            // coach channel messages (simple + consistent)
            'messages' => fn ($q) => $q->where('channel', 'coach')->orderBy('id'),
            'messages.sender',
            'messages.attachments',
        ]);

        return view('coach.disputes.show', compact('dispute'));
    }

    public function message(Request $r, Dispute $dispute)
    {
        $uid = (int) auth()->id();
        abort_unless((int) $dispute->coach_id === $uid, 403);

        $st = strtolower((string)($dispute->status ?? 'open'));

        // ✅ HARD LOCK if finalized
        if (!empty($dispute->resolved_at) || in_array($st, ['resolved', 'rejected'], true)) {
            abort(403, 'This dispute is finalized. You cannot send messages.');
        }

        // ✅ coach can message in open/opened/in_review
        abort_unless(in_array($st, ['open', 'opened', 'in_review'], true), 403);

        $data = $r->validate([
            'message' => ['nullable', 'string', 'max:5000'],
            'files.*' => ['nullable', 'file', 'max:20480',
                'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime'
            ],
        ]);

        if (empty(trim((string) ($data['message'] ?? ''))) && !$r->hasFile('files')) {
            return back()->withErrors(['message' => 'Please type a message or attach a file.']);
        }

        return DB::transaction(function () use ($dispute, $uid, $data, $r) {

            $d = Dispute::lockForUpdate()->findOrFail((int) $dispute->id);

            $st = strtolower((string)($d->status ?? 'open'));

            if (!empty($d->resolved_at) || in_array($st, ['resolved', 'rejected'], true)) {
                abort(403, 'This dispute is finalized. You cannot send messages.');
            }

            $msg = DisputeMessage::create([
                'dispute_id'      => (int) $d->id,
                'sender_user_id'  => (int) $uid,
                'sender_role'     => 'coach',
                'channel'         => 'coach',
                'target_role'     => 'coach',
                'message'         => $data['message'] ?? null,
            ]);

            if ($r->hasFile('files')) {
                foreach ($r->file('files') as $file) {
                    if (!$file) continue;

                    $path = $file->store("disputes/{$d->id}", 'public');

                    DisputeAttachment::create([
                        'dispute_id' => (int) $d->id,
                        'message_id' => (int) $msg->id,
                        'disk'       => 'public',
                        'path'       => $path,
                        'filename'   => $file->getClientOriginalName(),
                        'mime'       => $file->getMimeType(),
                        'size'       => $file->getSize(),
                    ]);
                }
            }

            $now = now();

            // ✅ Party activity always updates
            $baseUpdate = [
                'last_party_message_at' => $now,
                'last_message_at'       => $now,
                'updated_at'            => $now,
            ];

            // ✅ If it was in_review -> re-open and return to queue (unassigned, reset review timer)
            if ($st === 'in_review') {
                $d->forceFill($baseUpdate + [
                    'status'               => 'open',
                    'in_review_started_at' => null,

                    // return to queue
                    'assigned_staff_id'    => null,
                    'assigned_staff_role'  => null,
                    'assigned_at'          => null,
                ])->save();

                DB::afterCommit(function () {
                    app(DisputeQueueService::class)->dispatchAgents();
                });
            } else {
                // ✅ keep status, do NOT touch assignment
                $d->forceFill($baseUpdate)->save();

                DB::afterCommit(function () use ($d) {
                    if (empty($d->assigned_staff_id)) {
                        app(DisputeQueueService::class)->dispatchAgents();
                    }
                });
            }

            return back()->with('success', 'Message sent.');
        });
    }
}