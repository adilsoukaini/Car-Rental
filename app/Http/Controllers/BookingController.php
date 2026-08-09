<?php

namespace App\Http\Controllers;

use App\Core\Support\BookingLookupService;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
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

    /**
     * Public booking lookup form — lets a guest (or owner) find a booking by
     * its random booking_number + the email used at booking time. No
     * authentication required: the booking_number is high-entropy (10 random
     * uppercase chars) and the email must match, so the pair acts as the
     * credential, same model as the e-commerce project's order_number lookup.
     */
    public function track(): Response
    {
        return Inertia::render('Bookings/Track');
    }

    /**
     * Resolves the booking_number + email pair to a booking and redirects to
     * bookings.show — a signed URL for guests (or any non-owner), a plain
     * route for the authenticated owner, matching SendBookingConfirmationEmail.
     */
    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'booking_number' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email'],
        ]);

        // Resolution lives in the shared BookingLookupService — the same
        // service the mobile JSON API's Api\BookingController::lookup uses,
        // so "what counts as a successful lookup" never drifts.
        $booking = app(BookingLookupService::class)->resolve(
            $validated['booking_number'],
            $validated['email'],
        );

        if ($booking === null) {
            return Redirect::route('bookings.track')
                ->withErrors(['booking_number' => 'No booking found with those details.'])
                ->withInput($validated);
        }

        if ($booking->user_id !== null && $booking->user_id === $request->user()?->id) {
            return Redirect::route('bookings.show', ['booking' => $booking->id]);
        }

        return Redirect::to(URL::temporarySignedRoute('bookings.show', now()->addHours(48), ['booking' => $booking->id]));
    }
}
