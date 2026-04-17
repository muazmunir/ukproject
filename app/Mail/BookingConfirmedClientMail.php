<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingConfirmedClientMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public array $slotsLocal,
        public string $tzLabel
    ) {}

    public function build()
    {
        $serviceTitle = $this->reservation->service_title_snapshot
            ?: ($this->reservation->service?->title ?? 'Booking');

        return $this->subject("Booking confirmed: {$serviceTitle}")
            // ✅ make sure this file exists:
            // resources/views/emails/booking-confirmed-client.blade.php
            ->view('emails.booking-confirmed-client', [
                'reservation' => $this->reservation,
                'slots'       => $this->slotsLocal,
                'tz'          => $this->tzLabel,
            ]);
    }
}
