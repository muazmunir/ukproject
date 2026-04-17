<?php

namespace App\Mail;

use App\Models\ReservationSlot;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SlotNudgeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ReservationSlot $slot,
        public int $attempt,            // 1 or 2
        public string $missingParty     // 'coach' or 'client'
    ) {}

    public function build()
    {
        $subject = $this->attempt === 1
            ? 'Session starting now — please join (Nudge 1/2)'
            : 'Final reminder — please join now (Nudge 2/2)';

        return $this->subject($subject)
            ->view('emails.slots.nudge')
            ->with([
                'slot' => $this->slot,
                'res'  => $this->slot->reservation,
                'attempt' => $this->attempt,
                'missingParty' => $this->missingParty,
            ]);
    }
}
