<?php

namespace App\Mail;

use App\Models\ReservationSlot;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SlotReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReservationSlot $slot) {}

    public function build()
    {
        return $this->subject('Your session starts soon (15-minute reminder)')
            ->view('emails.slots.reminder15')
            ->with([
                'slot' => $this->slot,
                'res'  => $this->slot->reservation,
            ]);
    }
}
