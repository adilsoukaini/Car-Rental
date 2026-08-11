<?php

declare(strict_types=1);

namespace Plugins\BookingEngine\Http\Controllers;

use App\Core\Support\FilterRegistry;
use App\Core\Support\PaymentGatewayRegistry;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
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

        // Every active location the customer may pick as pickup/return
        // destination — the two location pickers on the checkout form render
        // from this list. One-way rentals (pickup != return) have been
        // supported by the service layer since Phase 5; this exposes the
        // choice to the customer for the first time.
        $locations = Location::query()
            ->where('is_active', true)
            ->orderBy('city')
            ->orderBy('name')
            ->get(['id', 'name', 'address_line', 'city', 'country']);

        // The pickers default to the vehicle's current location; the query
        // params are only present after the customer changes a picker and the
        // form re-fetches the price preview (applyPromo).
        $pickupLocationId = (int) $request->input('pickup_location_id', $vehicle->location_id);
        $returnLocationId = (int) $request->input('return_location_id', $vehicle->location_id);

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
                'locations' => $locations,
                'pickupLocationId' => $pickupLocationId,
                'returnLocationId' => $returnLocationId,
                // Info-only age disclosure — see minimumAgeForCategory().
                'minAgeForCategory' => $this->minimumAgeForCategory($vehicle->category),
                'driverDateOfBirth' => $this->driverDateOfBirth($this->authenticatedUser($request)),
            ]);
        }

        $validated = $validator->validated();

        $pickupAt = Carbon::parse($validated['pickup_at']);
        $returnAt = Carbon::parse($validated['return_at']);

        $availabilityRequest = new AvailabilityCheckRequest(
            vehicleId: $vehicle->id,
            pickupAt: $pickupAt,
            returnAt: $returnAt,
            pickupLocationId: $pickupLocationId,
        );

        $available = FilterRegistry::apply('booking.availabilityCheck', $availabilityRequest) !== false;

        $priceRequest = new PriceCalculationRequest(
            vehicleId: $vehicle->id,
            pickupAt: $pickupAt,
            returnAt: $returnAt,
            userId: $this->authenticatedUser($request)?->id,
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
            'locations' => $locations,
            'pickupLocationId' => $pickupLocationId,
            'returnLocationId' => $returnLocationId,
            // Info-only age disclosure — see minimumAgeForCategory().
            'minAgeForCategory' => $this->minimumAgeForCategory($vehicle->category),
            'driverDateOfBirth' => $this->driverDateOfBirth($this->authenticatedUser($request)),
        ]);
    }

    /**
     * Creates a pending, time-limited hold and a real Stripe deposit
     * authorization against it, then hands the client_secret to the
     * frontend to collect payment via Stripe Elements. Does NOT confirm
     * the booking — see confirm().
     */
    public function store(Request $request, Vehicle $vehicle): Response|JsonResponse
    {
        abort_if($vehicle->status !== 'available', 404);

        // H6: idempotency. A client that never received the first response
        // retries the exact same request with an Idempotency-Key header. If a
        // booking was already created under that key, return it (status 200,
        // not 201) instead of creating a duplicate and charging the deposit
        // twice.
        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = Booking::where('idempotency_key', $idempotencyKey)
                ->where('vehicle_id', $vehicle->id)
                ->latest('id')
                ->first();

            if ($existing !== null) {
                $authorization = Payment::where('booking_id', $existing->id)
                    ->where('type', 'deposit_authorization')
                    ->latest('id')
                    ->first();

                if ($authorization !== null) {
                    $payload = $this->bookingPayload($existing, $vehicle, $authorization);

                    if ($request->is('api/*')) {
                        return response()->json($payload);
                    }

                    return Inertia::render('Bookings/Payment', $payload);
                }

                // The previous attempt created the booking but crashed before
                // a deposit hold was authorized — an orphaned pending booking
                // with no payment in flight. Delete it and let this request
                // create a fresh one under the same idempotency key.
                if ($existing->status === 'pending') {
                    $existing->delete();
                }
            }
        }

        $rules = $this->dateRules();

        // Location ids are optional on the wire only so that callers who
        // don't care (tests, the pre-picker checkout flow) still default to
        // the vehicle's current location — a real checkout form always sends
        // them. When present they must reference a real location row; a bogus
        // id would otherwise be persisted as a broken FK on bookings.
        $rules['pickup_location_id'] = ['sometimes', 'integer', 'exists:locations,id'];
        $rules['return_location_id'] = ['sometimes', 'integer', 'exists:locations,id'];

        if ($this->authenticatedUser($request) === null) {
            $rules['guest_name'] = ['required', 'string', 'max:255'];
            $rules['guest_email'] = ['required', 'email', 'max:255'];
            $rules['guest_phone'] = ['required', 'string', 'max:50'];
        }

        $validated = $request->validate($rules);

        try {
            $booking = app(BookingCreator::class)->createPending([
                'vehicle_id' => $vehicle->id,
                'user_id' => $this->authenticatedUser($request)?->id,
                'guest_name' => $validated['guest_name'] ?? null,
                'guest_email' => $validated['guest_email'] ?? null,
                'guest_phone' => $validated['guest_phone'] ?? null,
                'pickup_location_id' => (int) ($validated['pickup_location_id'] ?? $vehicle->location_id),
                'return_location_id' => (int) ($validated['return_location_id'] ?? $vehicle->location_id),
                'pickup_at' => $validated['pickup_at'],
                'return_at' => $validated['return_at'],
                'promo_code' => $validated['promo_code'] ?? null,
                'idempotency_key' => $idempotencyKey !== null && $idempotencyKey !== '' ? $idempotencyKey : null,
            ]);
        } catch (VehicleNotAvailableException) {
            throw ValidationException::withMessages([
                'pickup_at' => 'This vehicle is no longer available for the selected dates.',
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

        $payload = $this->bookingPayload($booking, $vehicle, $authorization);

        // The mobile app is a pure JSON consumer — for /api/* requests return
        // the exact same payload the web Payment page receives, as JSON (the
        // shape the mobile lib/api.ts BookingCreateResponse documents).
        if ($request->is('api/*')) {
            return response()->json($payload);
        }

        return Inertia::render('Bookings/Payment', $payload);
    }

    /**
     * The second step: called once the customer has completed payment
     * client-side (no redirect needed) or Stripe has redirected back here
     * after a step like 3D Secure. Verifies the hold genuinely succeeded —
     * via a direct, synchronous status check against Stripe, not by
     * trusting the client — then confirms the booking for real.
     */
    public function confirm(Request $request, Booking $booking): RedirectResponse|JsonResponse
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

        // Mobile app: return the confirmed booking (with its eager-loaded
        // relations) as JSON — the shape the mobile lib/api.ts confirm()
        // documents. Web: redirect to the booking detail page as before.
        if ($request->is('api/*')) {
            return response()->json(
                $confirmed->load(['vehicle', 'pickupLocation', 'returnLocation']),
            );
        }

        return redirect()->to($this->bookingShowUrl($confirmed));
    }

    /**
     * The Stripe 3D-Secure redirect-back landing (return_url) is always a
     * GET — Stripe navigates the browser here after the customer completes
     * the challenge. The actual confirmation (confirm()) is strictly POST
     * since it is state-changing, so this method renders a minimal,
     * deliberately non-mutating interstitial that auto-submits a CSRF-
     * protected POST to bookings.confirm. This preserves the 3DS flow
     * without ever performing the state change on a GET request.
     */
    public function confirmReturn(Booking $booking): View
    {
        return view('booking-confirm-return', ['booking' => $booking]);
    }

    private function clientSecretFor(Payment $authorization): string
    {
        $metadata = $authorization->metadata ?? [];

        return (string) ($metadata['client_secret'] ?? '');
    }

    /**
     * The JSON payload both the web Payment page and the mobile /api/*
     * consumer receive after store() creates a booking + deposit hold. Shared
     * by the normal creation path and the H6 idempotent-retry path (which
     * returns the already-created booking's payload verbatim).
     */
    private function bookingPayload(Booking $booking, Vehicle $vehicle, Payment $authorization): array
    {
        return [
            'bookingId' => $booking->id,
            'vehicleId' => $vehicle->id,
            'vehicle' => $vehicle->only(['make', 'model', 'year']),
            'pickupAt' => $booking->pickup_at->toDateTimeString(),
            'returnAt' => $booking->return_at->toDateTimeString(),
            'totalPrice' => (float) $booking->total_price,
            'depositAmount' => (float) $booking->security_deposit_amount,
            'holdExpiresAt' => $booking->hold_expires_at?->toIso8601String(),
            'clientSecret' => $this->clientSecretFor($authorization),
            'stripePublishableKey' => (string) config('payments-stripe.key'),
        ];
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

    /**
     * Resolve the authenticated user for this request, if any.
     *
     * The public book/confirm endpoints are intentionally NOT behind
     * auth:sanctum (guests must be able to book), so $request->user() — which
     * resolves the session-based `web` guard — is always null for an API
     * request that carries only a Bearer token. A valid Sanctum token is
     * still honored when present: fall back to the `sanctum` guard, which
     * authenticates via the Bearer token (and, for web requests, already
     * falls back to the session guard itself — so the union below is correct
     * for both channels). Returns null for a genuine guest.
     */
    private function authenticatedUser(Request $request): ?User
    {
        return $request->user() ?? Auth::guard('sanctum')->user();
    }

    /**
     * The minimum driver age for a vehicle category, shown as an info-only
     * disclosure (never a booking gate). Lives in the driver-verification
     * plugin's config (`minimum_age_by_category`), which is merged whenever
     * that plugin is loaded; the local fallback map keeps the disclosure
     * working even if the plugin is ever disabled — the age rule is a real
     * business requirement, not tied to the optional online verification.
     */
    private function minimumAgeForCategory(string $category): ?int
    {
        $map = config('driver-verification.minimum_age_by_category') ?: [
            'economy' => 21,
            'suv' => 21,
            'van' => 21,
            'luxury' => 25,
        ];

        return isset($map[$category]) ? (int) $map[$category] : null;
    }

    /**
     * The logged-in user's date of birth, taken from their latest driver
     * verification (the only place the system stores it) so the checkout can
     * calculate their age at pickup for the info-only warning. null for
     * guests, users with no verification, or when the plugin-owned table
     * doesn't exist (same "core must not hard-crash over one optional
     * feature" guard as HandleInertiaRequests).
     */
    private function driverDateOfBirth(?User $user): ?string
    {
        if ($user === null || ! Schema::hasTable('driver_verifications')) {
            return null;
        }

        $latest = $user->driverVerifications()->latest('id')->first();

        return $latest?->date_of_birth?->toDateString();
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
