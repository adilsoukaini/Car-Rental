<?php

declare(strict_types=1);

namespace Plugins\BookingEngine\Http\Controllers;

use App\Core\Support\FilterRegistry;
use App\Core\Support\PaymentGatewayRegistry;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Plugins\BookingEngine\Exceptions\DriverNotEligibleException;
use Plugins\BookingEngine\Exceptions\VehicleNotAvailableException;
use Plugins\BookingEngine\Support\AvailabilityCheckRequest;
use Plugins\BookingEngine\Support\BookingCreator;
use Plugins\BookingEngine\Support\PriceBreakdown;
use Plugins\BookingEngine\Support\PriceCalculationRequest;
use Throwable;

/**
 * The first — and, as of 2026-08-04, only — real caller of BookingCreator
 * anywhere in this application. Every booking before this controller
 * existed was created via tinker or automated tests; see CLAUDE.md's
 * "real booking-creation flow" section for the full finding.
 *
 * Lives in booking-engine (not core), even though it renders a page for a
 * core-owned Vehicle, because it must call BookingCreator — a plugin
 * class core can never reference (Hard Rule 1/2). Same split as
 * fleet-management owning VehicleController for the same core-owned model.
 *
 * Two-step real payment flow, added 2026-08-04 ("Phase B"): store() no
 * longer confirms a booking directly — it creates a time-limited pending
 * hold (BookingCreator::createPending()) and authorizes a real Stripe
 * deposit hold against it, returning a client_secret for Stripe Elements.
 * confirm() is the second step, called once the customer completes payment
 * client-side (or Stripe redirects back after 3D Secure) — it verifies the
 * hold genuinely succeeded and calls BookingCreator::confirmPending().
 * Hardcodes the 'stripe' gateway id rather than offering a gateway choice —
 * this project has exactly one real deposit-hold-capable gateway (CMI was
 * deliberately not built in Phase 7, see docs/event-registry.md); building
 * gateway selection now would be speculative, not needed.
 */
class BookingCheckoutController extends Controller
{
    public function show(Request $request, Vehicle $vehicle): Response
    {
        abort_if($vehicle->status !== 'available', 404);

        $vehicle->loadMissing('location');

        // Validate dates manually instead of letting $request->validate()
        // throw: a failed validation redirects back to the referer (usually
        // the fleet page), silently bouncing the customer with no explanation
        // for the bogus dates (QA finding). Render the checkout page with an
        // explicit error banner instead.
        $validator = Validator::make($request->all(), $this->dateRules(), [
            'pickup_at.required' => 'Veuillez choisir une date de prise en charge.',
            'pickup_at.date' => 'La date de prise en charge est invalide.',
            'pickup_at.after' => 'La date de prise en charge doit être dans le futur.',
            'return_at.required' => 'Veuillez choisir une date de retour.',
            'return_at.date' => 'La date de retour est invalide.',
            'return_at.after' => 'La date de retour doit être postérieure à la date de prise en charge.',
        ]);

        if ($validator->fails()) {
            return Inertia::render('Bookings/Checkout', [
                'vehicle' => $vehicle,
                'pickupAt' => (string) $request->input('pickup_at', ''),
                'returnAt' => (string) $request->input('return_at', ''),
                'available' => false,
                'priceBreakdown' => [
                    'days' => 0,
                    'dailyRate' => 0,
                    'discountPercent' => 0,
                    'totalPrice' => 0,
                    'depositAmount' => 0,
                    'promoDiscount' => 0,
                ],
                'promoError' => null,
                'dateError' => $validator->errors()->first(),
            ]);
        }

        $validated = $validator->validated();

        $pickupAt = Carbon::parse($validated['pickup_at']);
        $returnAt = Carbon::parse($validated['return_at']);

        $availabilityRequest = new AvailabilityCheckRequest(
            vehicleId: $vehicle->id,
            pickupAt: $pickupAt,
            returnAt: $returnAt,
            pickupLocationId: $vehicle->location_id,
        );

        $available = FilterRegistry::apply('booking.availabilityCheck', $availabilityRequest) !== false;

        $priceRequest = new PriceCalculationRequest(
            vehicleId: $vehicle->id,
            pickupAt: $pickupAt,
            returnAt: $returnAt,
            userId: $request->user()?->id,
            promoCode: $request->input('promo_code'),
        );

        /** @var PriceBreakdown $breakdown */
        $breakdown = FilterRegistry::apply('booking.priceCalculation', new PriceBreakdown($priceRequest));

        return Inertia::render('Bookings/Checkout', [
            'vehicle' => $vehicle,
            'pickupAt' => $pickupAt->toDateTimeString(),
            'returnAt' => $returnAt->toDateTimeString(),
            'available' => $available,
            'priceBreakdown' => [
                'days' => $breakdown->days,
                'dailyRate' => $breakdown->dailyRate,
                'discountPercent' => $breakdown->discountPercent,
                'totalPrice' => $breakdown->totalPrice(),
                'depositAmount' => $breakdown->depositAmount,
                'promoDiscount' => $breakdown->promoDiscount,
            ],
            'promoError' => $breakdown->promoError,
        ]);
    }

    /**
     * Creates a pending, time-limited hold and a real Stripe deposit
     * authorization against it, then hands the client_secret to the
     * frontend to collect payment via Stripe Elements. Does NOT confirm
     * the booking — see confirm().
     */
    public function store(Request $request, Vehicle $vehicle): Response
    {
        abort_if($vehicle->status !== 'available', 404);

        $rules = $this->dateRules();

        if (! $request->user()) {
            $rules['guest_name'] = ['required', 'string', 'max:255'];
            $rules['guest_email'] = ['required', 'email', 'max:255'];
            $rules['guest_phone'] = ['required', 'string', 'max:50'];
        }

        $validated = $request->validate($rules);

        try {
            $booking = app(BookingCreator::class)->createPending([
                'vehicle_id' => $vehicle->id,
                'user_id' => $request->user()?->id,
                'guest_name' => $validated['guest_name'] ?? null,
                'guest_email' => $validated['guest_email'] ?? null,
                'guest_phone' => $validated['guest_phone'] ?? null,
                'pickup_location_id' => $vehicle->location_id,
                'return_location_id' => $vehicle->location_id,
                'pickup_at' => $validated['pickup_at'],
                'return_at' => $validated['return_at'],
                'promo_code' => $validated['promo_code'] ?? null,
            ]);
        } catch (VehicleNotAvailableException) {
            throw ValidationException::withMessages([
                'pickup_at' => 'This vehicle is no longer available for the selected dates.',
            ]);
        } catch (DriverNotEligibleException $e) {
            throw ValidationException::withMessages([
                'pickup_at' => $e->getMessage(),
            ]);
        }

        $gateway = PaymentGatewayRegistry::get('stripe');

        if ($gateway === null) {
            $booking->delete();

            throw ValidationException::withMessages([
                'pickup_at' => 'Payment is currently unavailable. Please try again shortly.',
            ]);
        }

        try {
            $authorization = $gateway->authorizeDeposit($booking, (float) $booking->security_deposit_amount);
        } catch (Throwable) {
            $booking->delete();

            throw ValidationException::withMessages([
                'pickup_at' => 'We could not start payment for this booking. Please try again.',
            ]);
        }

        return Inertia::render('Bookings/Payment', [
            'bookingId' => $booking->id,
            'vehicle' => $vehicle->only(['make', 'model', 'year']),
            'pickupAt' => $booking->pickup_at->toDateTimeString(),
            'returnAt' => $booking->return_at->toDateTimeString(),
            'totalPrice' => (float) $booking->total_price,
            'depositAmount' => (float) $booking->security_deposit_amount,
            'holdExpiresAt' => $booking->hold_expires_at?->toIso8601String(),
            'clientSecret' => $this->clientSecretFor($authorization),
            'stripePublishableKey' => (string) config('payments-stripe.key'),
        ]);
    }

    /**
     * The second step: called once the customer has completed payment
     * client-side (no redirect needed) or Stripe has redirected back here
     * after a step like 3D Secure. Verifies the hold genuinely succeeded —
     * via a direct, synchronous status check against Stripe, not by
     * trusting the client — then confirms the booking for real.
     */
    public function confirm(Request $request, Booking $booking): RedirectResponse
    {
        $authorization = Payment::where('booking_id', $booking->id)
            ->where('type', 'deposit_authorization')
            ->latest('id')
            ->first();

        if ($authorization === null) {
            throw ValidationException::withMessages([
                'pickup_at' => 'No payment was found for this booking.',
            ]);
        }

        $gateway = PaymentGatewayRegistry::get($authorization->gateway);

        if ($gateway === null) {
            throw ValidationException::withMessages([
                'pickup_at' => 'Payment is currently unavailable. Please try again shortly.',
            ]);
        }

        $authorization = $gateway->syncAuthorizationStatus($authorization);

        if ($authorization->status !== 'authorized') {
            throw ValidationException::withMessages([
                'pickup_at' => 'Your payment has not been confirmed yet. Please try again.',
            ]);
        }

        try {
            $confirmed = app(BookingCreator::class)->confirmPending($booking);
        } catch (VehicleNotAvailableException) {
            $gateway->releaseDeposit($authorization);

            throw ValidationException::withMessages([
                'pickup_at' => 'This vehicle is no longer available for the selected dates. Your payment hold has been released.',
            ]);
        }

        return redirect()->to($this->bookingShowUrl($confirmed));
    }

    private function clientSecretFor(Payment $authorization): string
    {
        $metadata = $authorization->metadata ?? [];

        return (string) ($metadata['client_secret'] ?? '');
    }

    /**
     * A guest who just booked has no session ownership of the booking and
     * no signature in a plain route() URL — redirecting them there without
     * a signature is a real 403, not a hypothetical. Mirrors the source
     * e-commerce project's own CheckoutController::confirmationUrl(), and
     * the same signed-vs-plain split SendBookingConfirmationEmail already
     * uses for the confirmation email's link.
     */
    private function bookingShowUrl(Booking $booking): string
    {
        if ($booking->user_id === null) {
            return URL::temporarySignedRoute(
                'bookings.show',
                now()->addHours(48),
                ['booking' => $booking->id],
            );
        }

        return route('bookings.show', ['booking' => $booking->id]);
    }

    /** @return array<string, array<int, string>> */
    private function dateRules(): array
    {
        return [
            'pickup_at' => ['required', 'date', 'after:now'],
            'return_at' => ['required', 'date', 'after:pickup_at'],
            'promo_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}
