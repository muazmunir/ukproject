<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingConfirmedCoachMail extends Mailable
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

        return $this->subject("New booking: {$serviceTitle}")
            // ✅ match file: resources/views/emails/booking-confirmed-coach.blade.php
            ->view('emails.booking-confirmed-coach', [
                'reservation' => $this->reservation,
                'slots'       => $this->slotsLocal,
                'tz'          => $this->tzLabel,
            ]);
    }
}
