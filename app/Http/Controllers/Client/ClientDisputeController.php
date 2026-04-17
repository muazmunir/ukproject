<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\DisputeAttachment;
use App\Models\DisputeMessage;
use App\Models\Reservation;
use App\Services\ReservationUiService;
use App\Services\DisputeQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientDisputeController extends Controller
{
    public function index()
    {
        $uid = (int) auth()->id();

        $disputes = Dispute::with([
                'reservation.service',
                'reservation.package',

                // ✅ show "Handled by @agent" in listing
                'assignedStaff:id,username,email',
            ])
            ->where('client_id', $uid)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('client.disputes.index', compact('disputes'));
    }

    public function create(Reservation $reservation, ReservationUiService $ui)
    {
        $uid = (int) auth()->id();
        abort_unless((int) $reservation->client_id === $uid, 403);

        $reservation->loadMissing(['dispute', 'slots']);

        $existing = $reservation->dispute;
        if ($existing) {
            return redirect()->route('client.disputes.show', $existing);
        }

        $flags = $ui->postSessionFlags($reservation);
        abort_unless(!empty($flags['canDisputeClient']), 403);

        $titles = config('disputes.titles.client', []);
        return view('client.disputes.create', compact('reservation', 'titles'));
    }

    public function store(Request $r, Reservation $reservation, ReservationUiService $ui)
    {
        $uid = (int) auth()->id();
        abort_unless((int) $reservation->client_id === $uid, 403);

        $reservation->loadMissing(['slots', 'dispute', 'service']);

        if ($reservation->dispute) {
            return redirect()->route('client.disputes.show', $reservation->dispute)
                ->with('info', 'This booking is already in dispute.');
        }

        $flags = $ui->postSessionFlags($reservation);
        if (empty($flags['canDisputeClient'])) {
            abort(403, 'Dispute is not allowed for this booking.');
        }

        $titles = config('disputes.titles.client', []);
        $allowedKeys = array_keys($titles);

        $data = $r->validate([
            'title_key'    => ['required', Rule::in($allowedKeys)],
            'description'  => ['required', 'string', 'max:5000'],
            'files.*'      => ['nullable', 'file', 'max:20480', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime'],
        ]);

        return DB::transaction(function () use ($reservation, $uid, $data, $r, $titles, $ui) {

            $res = Reservation::lockForUpdate()
                ->with(['service', 'slots', 'dispute'])
                ->findOrFail((int) $reservation->id);

            if ((int) $res->client_id !== $uid) abort(403);

            if ($res->dispute) {
                return redirect()->route('client.disputes.show', $res->dispute)
                    ->with('info', 'This booking is already in dispute.');
            }

            $flags = $ui->postSessionFlags($res);
            if (empty($flags['canDisputeClient'])) {
                abort(403, 'Dispute is not allowed for this booking.');
            }

            $now = now();

            $dispute = Dispute::create([
                'reservation_id'     => (int) $res->id,
                'opened_by_role'     => 'client',
                'opened_by_user_id'  => (int) $uid,
                'client_id'          => (int) $res->client_id,
                'coach_id'           => (int) ($res->service?->coach_id ?? $res->coach_id),

                'title_key'          => $data['title_key'],
                'title_label'        => $titles[$data['title_key']] ?? $data['title_key'],
                'description'        => $data['description'],

                // ✅ starts in queue
                'status'              => 'open',
                'last_message_at'     => $now,
                'last_party_message_at'=> $now,

                // ✅ review timer starts empty
                'in_review_started_at'=> null,

                // ✅ assignment starts empty
                'assigned_staff_id'   => null,
                'assigned_staff_role' => null,
                'assigned_at'         => null,

                // ✅ SLA starts empty until staff assignment
                'sla_started_at'      => null,
            ]);

            $msg = DisputeMessage::create([
                'dispute_id'      => (int) $dispute->id,
                'sender_user_id'  => (int) $uid,
                'sender_role'     => 'client',
                'channel'         => 'client',
                'target_role'     => 'client',
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
                'disputed_by_client_at' => $now,
                'settlement_status'     => 'in_dispute',
            ])->save();

            DB::afterCommit(function () {
                app(DisputeQueueService::class)->dispatchAgents();
            });

            return redirect()
                ->route('client.disputes.show', $dispute)
                ->with('success', 'Dispute submitted. Staff will review.');
        });
    }

    public function show(Dispute $dispute)
    {
        $uid = (int) auth()->id();
        abort_unless((int) $dispute->client_id === $uid, 403);

        $dispute->load([
            'reservation.service.coach',
            'reservation.package',

            // ✅ agent + decision + latest summary (optional to show)
            'assignedStaff:id,username,email',
            'resolvedBy:id,username,email',
            'latestSummaryBy:id,username,email',

            // client channel messages
            'messages' => fn ($q) => $q->where('channel', 'client')->orderBy('id'),
            'messages.sender',
            'messages.attachments',
        ]);

        return view('client.disputes.show', compact('dispute'));
    }

    public function message(Request $r, Dispute $dispute)
    {
        $uid = (int) auth()->id();
        abort_unless((int) $dispute->client_id === $uid, 403);

        $st = strtolower((string)($dispute->status ?? 'open'));

        // ✅ HARD LOCK (final decisions only)
        if (!empty($dispute->resolved_at) || in_array($st, ['resolved','rejected'], true)) {
            abort(403, 'This dispute is finalized. You cannot send messages.');
        }

        // ✅ client can message in open/opened/in_review
        abort_unless(in_array($st, ['open','opened','in_review'], true), 403);

        $data = $r->validate([
            'message'  => ['nullable', 'string', 'max:5000'],
            'files.*'  => ['nullable', 'file', 'max:20480', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime'],
        ]);

        if (empty(trim((string) ($data['message'] ?? ''))) && !$r->hasFile('files')) {
            return back()->withErrors(['message' => 'Please type a message or attach a file.']);
        }

        return DB::transaction(function () use ($dispute, $uid, $data, $r) {

            $d = Dispute::lockForUpdate()->findOrFail((int) $dispute->id);

            $st = strtolower((string)($d->status ?? 'open'));

            if (!empty($d->resolved_at) || in_array($st, ['resolved','rejected'], true)) {
                abort(403, 'This dispute is finalized. You cannot send messages.');
            }

            $msg = DisputeMessage::create([
                'dispute_id'      => (int) $d->id,
                'sender_user_id'  => (int) $uid,
                'sender_role'     => 'client',
                'channel'         => 'client',
                'target_role'     => 'client',
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

            // ✅ If dispute is in_review -> reopen to queue (open, unassigned, reset review timer)
            if ($st === 'in_review') {
                $d->forceFill($baseUpdate + [
                    'status'              => 'open',
                    'in_review_started_at'=> null,

                    // unassign (back to queue)
                    'assigned_staff_id'   => null,
                    'assigned_staff_role' => null,
                    'assigned_at'         => null,
                ])->save();

                DB::afterCommit(function () {
                    app(DisputeQueueService::class)->dispatchAgents();
                });
            } else {
                // ✅ If open/opened: keep status as-is. Do NOT mess with assignment.
                $d->forceFill($baseUpdate)->save();

                DB::afterCommit(function () use ($d) {
                    // Dispatch only if it is unassigned (waiting in queue)
                    if (empty($d->assigned_staff_id)) {
                        app(DisputeQueueService::class)->dispatchAgents();
                    }
                });
            }

            return back()->with('success', 'Message sent.');
        });
    }
}