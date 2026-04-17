<?php
// app/Services/SlotInitService.php
namespace App\Services;

use App\Models\Reservation;
use Carbon\CarbonImmutable;

class SlotInitService
{
  public function initReservationSlots(Reservation $reservation): void
  {
    $reservation->loadMissing('slots');

    $first = $reservation->slots->min('start_utc');
    $last  = $reservation->slots->max('end_utc');

    foreach ($reservation->slots as $slot) {
      $start = CarbonImmutable::parse($slot->start_utc)->utc();

      if (!$slot->wait_deadline_utc) {
        $slot->wait_deadline_utc = $start->addMinutes(5);
      }

      // keep your existing statuses but standardize default:
      if (empty($slot->session_status)) {
        $slot->session_status = 'pending';
      }

      $slot->save();
    }

    $reservation->forceFill([
      'first_slot_start_utc' => $first ? CarbonImmutable::parse($first)->utc() : null,
      'last_slot_end_utc'    => $last  ? CarbonImmutable::parse($last)->utc()  : null,
      'settlement_status'    => $reservation->settlement_status === 'none' ? 'pending' : $reservation->settlement_status,
    ])->save();
  }
}
