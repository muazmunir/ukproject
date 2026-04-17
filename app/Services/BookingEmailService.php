<?php
namespace App\Services;

use App\Mail\BookingConfirmedClientMail;
use App\Mail\BookingConfirmedCoachMail;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

class BookingEmailService
{
    public function sendBookingConfirmedIfNeeded(Reservation $reservation): void
    {
        $reservation->loadMissing(['slots','service','client','coach']);

        if ($reservation->status !== 'booked' || $reservation->payment_status !== 'paid') {
            return;
        }

        // ---- CLIENT EMAIL (uses reservation->client_tz) ----
        if (is_null($reservation->client_booking_emailed_at) && $reservation->client?->email) {
            $clientTz = $reservation->client_tz ?: config('app.timezone','UTC');

            $slotsClient = $this->formatSlots($reservation, $clientTz);

            Mail::to($reservation->client->email)->send(
                new BookingConfirmedClientMail($reservation, $slotsClient, $clientTz)
            );

            $reservation->forceFill(['client_booking_emailed_at' => now()])->save();
        }

        // ---- COACH EMAIL (uses coach->timezone) ----
        if (is_null($reservation->coach_booking_emailed_at) && $reservation->coach?->email) {
            $coachTz = $reservation->coach->timezone ?: config('app.timezone','UTC');

            $slotsCoach = $this->formatSlots($reservation, $coachTz);

            Mail::to($reservation->coach->email)->send(
                new BookingConfirmedCoachMail($reservation, $slotsCoach, $coachTz)
            );

            $reservation->forceFill(['coach_booking_emailed_at' => now()])->save();
        }
    }

    private function formatSlots(Reservation $reservation, string $tz): array
    {
        return $reservation->slots
            ->sortBy('start_utc')
            ->map(function ($slot) use ($tz) {
                $startLocal = CarbonImmutable::parse($slot->start_utc)->setTimezone($tz);
                $endLocal   = CarbonImmutable::parse($slot->end_utc)->setTimezone($tz);

                return [
                    'date'  => $startLocal->format('D, d M Y'),
                    'start' => $startLocal->format('h:i A'),
                    'end'   => $endLocal->format('h:i A'),
                ];
            })
            ->values()
            ->all();
    }
}
