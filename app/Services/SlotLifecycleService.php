<?php

namespace App\Services;

use App\Models\ReservationSlot;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SlotLifecycleService
{
    public function tick(): int
    {
        $now = CarbonImmutable::now('UTC');

        // We only care about slots not finalized/cancelled
        $slots = ReservationSlot::query()
            ->with(['reservation' => fn($q) => $q->with(['service','payment'])])
            ->whereNull('finalized_at')
            ->whereNotIn('session_status', ['cancelled','completed','no_show_coach','no_show_client','no_show_both','auto_cancelled'])
            ->whereNotNull('start_utc')
            ->whereNotNull('end_utc')
            ->where('start_utc', '<=', $now->copy()->addHours(2))   // lookahead window
            ->where('end_utc', '>=', $now->copy()->subHours(2))     // and recent
            ->limit(400)
            ->get();

        $processed = 0;

        foreach ($slots as $slot) {
            $processed += $this->processSlot($slot, $now) ? 1 : 0;
        }

        return $processed;
    }

    private function processSlot(ReservationSlot $slot, CarbonImmutable $now): bool
    {
        return DB::transaction(function () use ($slot, $now) {

            /** @var ReservationSlot $slot */
            $slot = ReservationSlot::query()
                ->lockForUpdate()
                ->with(['reservation' => fn($q) => $q->with(['service','payment','slots'])])
                ->find($slot->id);

            if (! $slot || $slot->finalized_at) return false;

            $res = $slot->reservation;
            if (! $res) return false;

            $start = CarbonImmutable::parse($slot->start_utc)->utc();
            $end   = CarbonImmutable::parse($slot->end_utc)->utc();

            // ---------- 15-min reminder ----------
            if (!$slot->reminder_15_sent_at) {
                $at = $start->subMinutes(15);
                if ($now->gte($at) && $now->lt($end)) {
                    // TODO: dispatch email to client + coach
                    // dispatch(new SlotReminder15Job($slot->id));
                    $slot->reminder_15_sent_at = $now;
                }
            }

            // ---------- Determine waiting/live ----------
            $clientIn = !empty($slot->client_checked_in_at);
            $coachIn  = !empty($slot->coach_checked_in_at);

            if ($clientIn && $coachIn) {
                // if both checked in, it's live (until end)
                if (($slot->session_status ?? '') !== 'live' && $now->lt($end)) {
                    $slot->session_status = 'live';
                }
                // if end passed, complete
                if ($now->gte($end)) {
                    $slot->session_status = 'completed';
                    $slot->finalized_at   = $slot->finalized_at ?? $now;
                    $slot->info_json      = $this->mergeInfo($slot->info_json, ['final' => 'completed_by_time']);
                }
                $slot->save();
                return true;
            }

            // If none checked in, do nothing until start+5 (no_show_both rule)
            // BUT we still finalize at deadline if it passes.
            $defaultDeadline = $start->addMinutes(5);
            $deadline = $slot->extended_until_utc
                ? CarbonImmutable::parse($slot->extended_until_utc)->utc()
                : ($slot->wait_deadline_utc
                    ? CarbonImmutable::parse($slot->wait_deadline_utc)->utc()
                    : $defaultDeadline);

            // ensure wait_deadline exists once the slot is in play
            if (!$slot->wait_deadline_utc && $now->gte($start->subMinutes(5)) && $now->lt($end)) {
                $slot->wait_deadline_utc = $defaultDeadline;
            }

            // Waiting statuses
            if ($clientIn && !$coachIn && $now->lt($deadline)) {
                $slot->session_status = 'waiting_for_coach';
                $this->maybeSendNudges($slot, $start, $now, 'coach');
            }

            if ($coachIn && !$clientIn && $now->lt($deadline)) {
                $slot->session_status = 'waiting_for_client';
                $this->maybeSendNudges($slot, $start, $now, 'client');
            }

            // ---------- FINALIZE at deadline ----------
            if ($now->gte($deadline) && !$slot->finalized_at) {

                // case: coach missing
                if ($clientIn && !$coachIn) {
                    $this->finalizeNoShowCoach($slot);
                }
                // case: client missing (treated as completed session)
                elseif ($coachIn && !$clientIn) {
                    $this->finalizeNoShowClient($slot);
                }
                // case: both missing
                else {
                    $this->finalizeNoShowBoth($slot);
                }

                $slot->auto_cancelled_at = $slot->auto_cancelled_at ?? $now;
                $slot->finalized_at      = $slot->finalized_at ?? $now;
            }

            $slot->save();
            return true;
        });
    }

    private function maybeSendNudges(ReservationSlot $slot, CarbonImmutable $start, CarbonImmutable $now, string $target): void
    {
        // popup/email at start+5s
        if (!$slot->nudge1_sent_at && $now->gte($start->addSeconds(5))) {
            $slot->nudge1_sent_at = $now;
            // TODO: email $target
            // dispatch(new SlotNudge1Job($slot->id, $target));
            $slot->info_json = $this->mergeInfo($slot->info_json, ['nudge1_target' => $target]);
        }

        // popup/email at start+2m
        if (!$slot->nudge2_sent_at && $now->gte($start->addMinutes(2))) {
            $slot->nudge2_sent_at = $now;
            // TODO: email $target
            // dispatch(new SlotNudge2Job($slot->id, $target));
            $slot->info_json = $this->mergeInfo($slot->info_json, ['nudge2_target' => $target]);
        }
    }

    private function finalizeNoShowCoach(ReservationSlot $slot): void
    {
        // Rule: refund ALL (service + platform) to client wallet
        // Rule: coach pays 10% of service price to Zaivias
        $res = $slot->reservation;
        $subtotal = (int) $res->subtotal_minor;
        $total    = (int) $res->total_minor;

        $coachPenalty = (int) round($subtotal * 0.10);

        // Wallet moves
        app(WalletService::class)->credit((int)$res->client_id, $total, 'no_show_refund_client', $res->id, $res->payment?->id, [
            'slot_id' => $slot->id, 'case' => 'no_show_coach'
        ], $res->currency ?? 'USD');

        if ($res->coach_id) {
            app(WalletService::class)->debit((int)$res->coach_id, $coachPenalty, 'no_show_penalty_coach', $res->id, $res->payment?->id, [
                'slot_id' => $slot->id, 'case' => 'no_show_coach'
            ], $res->currency ?? 'USD');
        }

        $slot->session_status = 'no_show_coach';
        $slot->info_json = $this->mergeInfo($slot->info_json, [
            'final' => 'no_show_coach',
            'refund_client_minor' => $total,
            'coach_penalty_minor' => $coachPenalty,
        ]);

        // analytics snapshot at reservation-level (optional per-slot accumulation)
        $this->bumpReservationAnalytics($res, [
            'refund' => $total,
            'platform_earned' => $coachPenalty,
            'coach_penalty' => $coachPenalty,
        ]);
    }

    private function finalizeNoShowClient(ReservationSlot $slot): void
    {
        // Rule: slot treated as completed; info says client no-show
        $slot->session_status = 'no_show_client';
        $slot->info_json = $this->mergeInfo($slot->info_json, [
            'final' => 'no_show_client',
            'note'  => 'Client did not join within allowed time.',
        ]);

        // Money handling: typically coach still earns session value.
        // If you split package into per-slot value, release that amount here.
        // TODO: integrate with your EscrowService / settlement release per slot.
    }

    private function finalizeNoShowBoth(ReservationSlot $slot): void
    {
        // Rule: client pays platform fee, coach pays 10% service, service refunded
        $res = $slot->reservation;
        $subtotal = (int) $res->subtotal_minor;
        $fees     = (int) $res->fees_minor;

        $coachPenalty = (int) round($subtotal * 0.10);

        // refund service only
        app(WalletService::class)->credit((int)$res->client_id, $subtotal, 'no_show_both_refund_service', $res->id, $res->payment?->id, [
            'slot_id' => $slot->id, 'case' => 'no_show_both'
        ], $res->currency ?? 'USD');

        if ($res->coach_id) {
            app(WalletService::class)->debit((int)$res->coach_id, $coachPenalty, 'no_show_both_penalty_coach', $res->id, $res->payment?->id, [
                'slot_id' => $slot->id, 'case' => 'no_show_both'
            ], $res->currency ?? 'USD');
        }

        $slot->session_status = 'no_show_both';
        $slot->info_json = $this->mergeInfo($slot->info_json, [
            'final' => 'no_show_both',
            'refund_client_minor' => $subtotal,
            'client_kept_fee_minor' => $fees,
            'coach_penalty_minor' => $coachPenalty,
        ]);

        $this->bumpReservationAnalytics($res, [
            'refund' => $subtotal,
            'platform_earned' => ($fees + $coachPenalty),
            'coach_penalty' => $coachPenalty,
            'client_penalty' => $fees,
        ]);
    }

    private function bumpReservationAnalytics(Reservation $res, array $delta): void
    {
        // If you already have these columns, we increment safely.
        $res->refresh(); // ensure current values
        $res->refund_total_minor    = (int)($res->refund_total_minor ?? 0)    + (int)($delta['refund'] ?? 0);
        $res->platform_earned_minor = (int)($res->platform_earned_minor ?? 0) + (int)($delta['platform_earned'] ?? 0);
        $res->coach_penalty_minor   = (int)($res->coach_penalty_minor ?? 0)   + (int)($delta['coach_penalty'] ?? 0);
        $res->client_penalty_minor  = (int)($res->client_penalty_minor ?? 0)  + (int)($delta['client_penalty'] ?? 0);
        $res->save();
    }

    private function mergeInfo($infoJson, array $extra): array
    {
        $base = [];
        if (is_array($infoJson)) $base = $infoJson;
        elseif (is_string($infoJson) && $infoJson !== '') {
            $decoded = json_decode($infoJson, true);
            if (is_array($decoded)) $base = $decoded;
        }
        return array_merge($base, $extra);
    }
}
