<?php

namespace Tests\Feature\Api;

use App\Core\Contracts\PaymentGateway;
use App\Core\Support\PaymentGatewayRegistry;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Plugins\BookingEngine\BookingEngineServiceProvider;
use Tests\TestCase;

/**
 * JSON booking API endpoints for the mobile app. The full
 * book→hold→confirm→deposit flow needs real Stripe test infrastructure (the
 * same as the web flow — see CLAUDE.md's Phase B verification), so these tests
 * cover the endpoints that don't require a live gateway: ownership rules,
 * lookup, my-bookings, cancellation, and the book/confirm endpoints' error
 * paths (validation + no-hold).
 */
class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // BookingEngineServiceProvider registers the availability + pricing
        // pipes (booking.availabilityCheck / booking.priceCalculation) that
        // BookingCheckoutController::store() relies on — the same manual
        // registration BookingCheckoutTest uses. Without it the filter
        // registry would return raw values and the booking flow would not
        // match a real boot.
        $this->app->register(BookingEngineServiceProvider::class);

        // Booking::factory()->create() pulls in a Vehicle via its factory,
        // whose Searchable observer would POST to Meilisearch (not running in
        // the automated suite). Pin the offline `database` driver — see
        // VehicleApiTest::setUp for the full rationale.
        config(['scout.driver' => 'database']);
    }

    protected function tearDown(): void
    {
        PaymentGatewayRegistry::flush();

        parent::tearDown();
    }

    /**
     * store() creates a pending booking and then a real Stripe deposit hold.
     * A Mockery double of PaymentGateway stands in for Stripe (same pattern
     * as BookingCheckoutTest) so the endpoint completes without a live
     * gateway; the returned Payment carries the client_secret the controller
     * echoes back in its JSON payload.
     */
    private function registerMockGateway(): PaymentGateway
    {
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('id')->andReturn('stripe');
        PaymentGatewayRegistry::register($gateway, 'payments-stripe');

        return $gateway;
    }

    // -- GET /api/bookings/{id} -------------------------------------------------

    public function test_booking_show_requires_a_token(): void
    {
        $booking = Booking::factory()->create();

        $this->getJson("/api/bookings/{$booking->id}")->assertStatus(401);
    }

    public function test_booking_show_returns_the_owning_users_booking(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('id', $booking->id);
    }

    public function test_booking_show_forbids_another_users_booking(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $owner->id]);
        $token = $other->createToken('mobile')->plainTextToken;

        $this->withToken($token)->getJson("/api/bookings/{$booking->id}")->assertStatus(403);
    }

    // -- GET /api/my-bookings ---------------------------------------------------

    public function test_my_bookings_returns_only_the_users_bookings(): void
    {
        $user = User::factory()->create();
        Booking::factory()->create(['user_id' => $user->id]);
        Booking::factory()->create(['user_id' => $user->id]);
        Booking::factory()->create(); // another user's booking
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/my-bookings')
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_my_bookings_requires_a_token(): void
    {
        $this->getJson('/api/my-bookings')->assertStatus(401);
    }

    // -- POST /api/bookings/track ----------------------------------------------

    public function test_track_returns_the_booking_for_a_matching_number_and_email(): void
    {
        $booking = Booking::factory()->create([
            'booking_number' => 'ABCDEFGHIJ',
            'guest_email' => 'guest@example.com',
        ]);

        $this->postJson('/api/bookings/track', [
            'booking_number' => 'ABCDEFGHIJ',
            'email' => 'guest@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('id', $booking->id);
    }

    public function test_track_returns_422_for_an_unknown_booking(): void
    {
        $this->postJson('/api/bookings/track', [
            'booking_number' => 'NOPE',
            'email' => 'guest@example.com',
        ])->assertStatus(422);
    }

    // -- POST /api/bookings/{id}/cancel ----------------------------------------

    public function test_owner_can_cancel_a_confirmed_booking(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'confirmed']);
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
    }

    public function test_cancel_forbids_a_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $owner->id, 'status' => 'confirmed']);
        $token = $other->createToken('mobile')->plainTextToken;

        $this->withToken($token)->postJson("/api/bookings/{$booking->id}/cancel")->assertStatus(403);
    }

    // -- POST /api/vehicles/{id}/book + POST /api/bookings/{id}/confirm --------

    public function test_book_validates_required_dates(): void
    {
        $vehicle = Vehicle::factory()->create(['status' => 'available']);

        $this->postJson("/api/vehicles/{$vehicle->id}/book", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pickup_at', 'return_at']);
    }

    public function test_book_404s_for_an_unavailable_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['status' => 'maintenance']);

        $this->postJson("/api/vehicles/{$vehicle->id}/book", [
            'pickup_at' => now()->addDay()->toDateTimeString(),
            'return_at' => now()->addDays(3)->toDateTimeString(),
        ])->assertNotFound();
    }

    public function test_confirm_without_a_hold_returns_422(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending']);

        $this->postJson("/api/bookings/{$booking->id}/confirm")->assertStatus(422);
    }

    // -- POST /api/vehicles/{id}/book resolves a Bearer-token user ------------

    public function test_book_attaches_the_authenticated_user_when_a_token_is_sent(): void
    {
        $gateway = $this->registerMockGateway();
        $gateway->shouldReceive('authorizeDeposit')
            ->once()
            ->andReturnUsing(fn ($booking, $amount) => Payment::create([
                'booking_id' => $booking->id,
                'type' => 'deposit_authorization',
                'gateway' => 'stripe',
                'status' => 'pending',
                'amount' => $amount,
                'provider_reference' => 'pi_test_api_user',
                'metadata' => ['client_secret' => 'secret'],
            ]));

        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['status' => 'available']);

        // No guest fields — a logged-in user must not be required to provide
        // them. This was the original symptom: the route is public (guests
        // must book too), so $request->user() was always null and the 422
        // said "guest name is required" even with a valid token.
        $this->withToken($user->createToken('mobile')->plainTextToken)
            ->postJson("/api/vehicles/{$vehicle->id}/book", [
                'pickup_at' => now()->addDay()->toDateTimeString(),
                'return_at' => now()->addDays(3)->toDateTimeString(),
                'pickup_location_id' => $vehicle->location_id,
                'return_location_id' => $vehicle->location_id,
            ])
            ->assertOk();

        $booking = Booking::where('user_id', $user->id)->first();

        $this->assertNotNull($booking);
        $this->assertSame('pending', $booking->status);

        // And the booking must show up in the user's own history.
        $this->withToken($user->createToken('mobile')->plainTextToken)
            ->getJson('/api/my-bookings')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $booking->id);
    }

    public function test_book_without_a_token_creates_a_guest_booking(): void
    {
        $gateway = $this->registerMockGateway();
        $gateway->shouldReceive('authorizeDeposit')
            ->once()
            ->andReturnUsing(fn ($booking, $amount) => Payment::create([
                'booking_id' => $booking->id,
                'type' => 'deposit_authorization',
                'gateway' => 'stripe',
                'status' => 'pending',
                'amount' => $amount,
                'provider_reference' => 'pi_test_api_guest',
                'metadata' => ['client_secret' => 'secret'],
            ]));

        $vehicle = Vehicle::factory()->create(['status' => 'available']);

        $this->postJson("/api/vehicles/{$vehicle->id}/book", [
            'pickup_at' => now()->addDay()->toDateTimeString(),
            'return_at' => now()->addDays(3)->toDateTimeString(),
            'pickup_location_id' => $vehicle->location_id,
            'return_location_id' => $vehicle->location_id,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '0600000000',
        ])->assertOk();

        $booking = Booking::where('guest_email', 'guest@example.com')->first();

        $this->assertNotNull($booking);
        $this->assertNull($booking->user_id);
    }
}
