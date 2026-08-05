<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function show(Request $request, Booking $booking): Response
    {
        $isOwner = $request->user() && $booking->user_id === $request->user()->id;
        $hasValidSignature = $request->hasValidSignature();

        abort_unless($isOwner || $hasValidSignature, 403);

        $booking->load(['vehicle', 'pickupLocation', 'returnLocation']);

        return Inertia::render('Bookings/Show', [
            'booking' => $booking,
        ]);
    }
}
