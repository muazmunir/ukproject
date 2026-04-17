<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;

class BookingShowController extends Controller
{
    public function show(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->client_id === $request->user()->id, 403);

        $reservation->load([
            'service.coach',
            'package',
            'slots',
            'payment',
            'clientDispute', // if you have relation
        ]);

        return view('client.bookings.show', compact('reservation'));
    }
}
