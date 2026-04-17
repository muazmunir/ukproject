<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReservationSlot extends Model
{
    // app/Models/ReservationSlot.php
protected $fillable = [
  'reservation_id','slot_date','start_utc','end_utc',
  'client_code','coach_code',
  'client_checked_in_at','coach_checked_in_at',
  'client_lat','client_lng','coach_lat','coach_lng',
  'session_status',

  // NEW
  'reminder_15_sent_at',
  'nudge1_sent_at','nudge2_sent_at',
  'wait_deadline_utc','extended_until_utc',
  'auto_cancelled_at','finalized_at',
  'info_json',
];

protected $casts = [
  'slot_date' => 'date',
  'start_utc' => 'datetime',
  'end_utc'   => 'datetime',

  'client_checked_in_at' => 'datetime',
  'coach_checked_in_at'  => 'datetime',

  'client_lat' => 'float',
  'client_lng' => 'float',
  'coach_lat'  => 'float',
  'coach_lng'  => 'float',

  // NEW
  'reminder_15_sent_at'  => 'datetime',
  'nudge1_sent_at'       => 'datetime',
  'nudge2_sent_at'       => 'datetime',
  'wait_deadline_utc'    => 'datetime',
  'extended_until_utc'   => 'datetime',
  'auto_cancelled_at'    => 'datetime',
  'finalized_at'         => 'datetime',
  'info_json'            => 'array',
];


    /**
     * Auto-generate codes per slot when it's created.
     */
    protected static function booted()
    {
        static::creating(function (ReservationSlot $slot) {
            if (empty($slot->client_code)) {
                $slot->client_code = strtoupper(Str::random(6));
            }
            if (empty($slot->coach_code)) {
                $slot->coach_code = strtoupper(Str::random(6));
            }

            // default session status
            if (empty($slot->session_status)) {
                $slot->session_status = 'pending';
            }
        });
    }

    /**
     * Each slot belongs to a reservation.
     */
    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
