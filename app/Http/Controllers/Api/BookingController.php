<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Support\BookingCancellationService;
use App\Core\Support\BookingLookupService;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * JSON API booking endpoints for the mobile app. Auth + cancellation + lookup
 * all delegate to core services shared with the web side (BookingLookupService,
 * BookingCancellationService) — no business logic is re-implemented here.
 *
 * Ownership rules mirror the web BookingController::show: a booking is visible
 * via its own id only to the authenticated user who made it (guest bookings are
 * looked up through POST /api/bookings/track with the booking_number + email
 * pair, the same credential the web guest flow uses). No signed URLs exist for
 * the native app — the token IS the credential.
 */
class BookingController extends Controller
{
    public function __construct(
        private readonly BookingCancellationService $cancellation,
        private readonly BookingLookupService $lookup,
    ) {}

    public function show(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($request->user() && $booking->user_id === $request->user()->id, 403);

        $booking->load(['vehicle', 'pickupLocation', 'returnLocation']);

        return response()->json($booking);
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        // The deposit resolution (release / partial capture) is a backend side
        // effect; the mobile contract (lib/api.ts cancel: request<Booking>)
        // expects the cancelled booking itself, so that's what we return.
        $this->cancellation->cancel($booking);

        return response()->json(
            $booking->fresh()->load(['vehicle', 'pickupLocation', 'returnLocation']),
        );
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_number' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email'],
        ]);

        $booking = $this->lookup->resolve($validated['booking_number'], $validated['email']);

        if ($booking === null) {
            throw ValidationException::withMessages([
                'booking_number' => 'No booking found with those details.',
            ]);
        }

        return response()->json($booking->load(['vehicle', 'pickupLocation', 'returnLocation']));
    }

    public function myBookings(Request $request): JsonResponse
    {
        $bookings = Booking::where('user_id', $request->user()->id)
            ->with(['vehicle:id,make,model,year', 'pickupLocation:id,name,city', 'returnLocation:id,name,city'])
            ->latest()
            ->get();

        return response()->json($bookings);
    }
}
