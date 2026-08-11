<?php

namespace Tests\Feature\BookingEngine;

use App\Core\Contracts\PaymentGateway;
use App\Core\Events\BookingConfirmed;
use App\Core\Support\PaymentGatewayRegistry;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Plugins\BookingEngine\BookingEngineServiceProvider;
use Tests\TestCase;

/**
 * BookingCheckoutController is the first — and only — real caller of
 * BookingCreator anywhere in this application (see CLAUDE.md's "real
 * booking-creation flow" section). Every other test that exercises
 * BookingCreator calls it directly; these tests go through the actual
 * public HTTP entry point instead, the same path a real customer uses.
 *
 * Since 2026-08-04 ("Phase B"), store() no longer confirms directly — it
 * creates a pending hold + a real deposit authorization, and confirm()
 * (called after client-side payment) finalizes it. A Mockery double of
 * PaymentGateway stands in for Stripe, same pattern as BookingResourceTest.
 */
class BookingCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(BookingEngineServiceProvider::class);

        $this->location = Location::factory()->create();
        $this->vehicle = Vehicle::factory()->create([
            'location_id' => $this->location->id,
            'status' => 'available',
            'daily_rate' => 300,
            'category' => 'no-age-restriction-category',
        ]);
    }

    private function registerMockGateway(): PaymentGateway
    {
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('id')->andReturn('stripe');
        PaymentGatewayRegistry::register($gateway, 'payments-stripe');

        return $gateway;
    }

    public function test_checkout_page_shows_a_correct_price_preview(): void
    {
        $response = $this->get("/vehicles/{$this->vehicle->id}/book?pickup_at=".now()->addDay()->toDateTimeString().'&return_at='.now()->addDays(3)->toDateTimeString());

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Bookings/Checkout')
            ->where('available', true)
            ->where('priceBreakdown.days', 2)
            ->where('priceBreakdown.totalPrice', 600)
        );
    }

    public function test_checkout_page_shows_unavailable_when_dates_are_already_booked(): void
    {
        Booking::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'status' => 'confirmed',
            'pickup_at' => now()->addDay(),
            'return_at' => now()->addDays(3),
        ]);

        $response = $this->get("/vehicles/{$this->vehicle->id}/book?pickup_at=".now()->addDay()->toDateTimeString().'&return_at='.now()->addDays(3)->toDateTimeString());

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('available', false));
    }

    public function test_invalid_dates_render_the_checkout_page_with_an_error_instead_of_redirecting(): void
    {
        // QA finding: a GET with return-before-pickup used to let
        // $request->validate() throw, which redirected the customer back to
        // the fleet page with no explanation. The checkout page must render
        // with an explicit dateError instead.
        $response = $this->get("/vehicles/{$this->vehicle->id}/book?pickup_at=".now()->addDays(3)->toDateTimeString().'&return_at='.now()->addDay()->toDateTimeString());

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Bookings/Checkout')
            ->where('available', false)
            ->where('dateError', 'La date de retour doit être postérieure à la date de prise en charge.')
        );
    }

    public function test_past_pickup_date_renders_the_checkout_page_with_an_error(): void
    {
        $response = $this->get("/vehicles/{$this->vehicle->id}/book?pickup_at=".now()->subDay()->toDateTimeString().'&return_at='.now()->addDays(3)->toDateTimeString());

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Bookings/Checkout')
            ->where('available', false)
            ->where('dateError', 'La date de prise en charge doit être dans le futur.')
        );
    }

    public function test_a_non_available_vehicle_returns_404_on_the_checkout_page(): void
    {
        $this->vehicle->update(['status' => 'maintenance']);

        $response = $this->get("/vehicles/{$this->vehicle->id}/book?pickup_at=".now()->addDay()->toDateTimeString().'&return_at='.now()->addDays(3)->toDateTimeString());

        $response->assertNotFound();
    }

    public function test_store_creates_a_pending_booking_and_a_real_deposit_authorization(): void
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
                'provider_reference' => 'pi_test_checkout',
                'metadata' => ['client_secret' => 'pi_test_checkout_secret_abc'],
            ]));

        $response = $this->post("/vehicles/{$this->vehicle->id}/book", [
            'pickup_at' => now()->addDay()->toDateTimeString(),
            'return_at' => now()->addDays(3)->toDateTimeString(),
            'guest_name' => 'Real Guest',
            'guest_email' => 'real-guest@example.com',
            'guest_phone' => '0600000000',
        ]);

        $booking = Booking::where('guest_email', 'real-guest@example.com')->first();

        $this->assertNotNull($booking);
        $this->assertSame('pending', $booking->status);
        $this->assertNotNull($booking->hold_expires_at);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Bookings/Payment')
            ->where('bookingId', $booking->id)
            ->where('clientSecret', 'pi_test_checkout_secret_abc')
        );
    }

    public function test_a_guest_must_provide_contact_details(): void
    {
        $response = $this->post("/vehicles/{$this->vehicle->id}/book", [
            'pickup_at' => now()->addDay()->toDateTimeString(),
            'return_at' => now()->addDays(3)->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors(['guest_name', 'guest_email', 'guest_phone']);
        $this->assertSame(0, Booking::count());
    }

    public function test_a_logged_in_user_does_not_need_to_provide_guest_details(): void
    {
        $gateway = $this->registerMockGateway();
        $gateway->shouldReceive('authorizeDeposit')->once()->andReturnUsing(fn ($booking, $amount) => Payment::create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'gateway' => 'stripe',
            'status' => 'pending',
            'amount' => $amount,
            'provider_reference' => 'pi_test_user',
            'metadata' => ['client_secret' => 'secret'],
        ]));

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post("/vehicles/{$this->vehicle->id}/book", [
            'pickup_at' => now()->addDay()->toDateTimeString(),
            'return_at' => now()->addDays(3)->toDateTimeString(),
        ]);

        $booking = Booking::where('user_id', $user->id)->first();

        $this->assertNotNull($booking);
        $this->assertNull($booking->guest_email);
        $this->assertSame('pending', $booking->status);
        $response->assertOk();
    }

    public function test_store_deletes_the_pending_booking_if_authorization_fails(): void
    {
        $gateway = $this->registerMockGateway();
        $gateway->shouldReceive('authorizeDeposit')->once()->andThrow(new \RuntimeException('Stripe is down'));

        $response = $this->post("/vehicles/{$this->vehicle->id}/book", [
            'pickup_at' => now()->addDay()->toDateTimeString(),
            'return_at' => now()->addDays(3)->toDateTimeString(),
            'guest_name' => 'Real Guest',
            'guest_email' => 'real-guest@example.com',
            'guest_phone' => '0600000000',
        ]);

        $response->assertSessionHasErrors(['pickup_at']);
        $this->assertSame(0, Booking::count());
    }

    public function test_a_second_checkout_attempt_for_the_same_vehicle_and_dates_is_rejected_before_ever_reaching_stripe(): void
    {
        // This is the structural proof for the 2026-08-04 "pending blocks"
        // revision: the second attempt must fail at the availability check
        // inside createPending(), before PaymentGatewayRegistry::get()
        // or authorizeDeposit() are ever reached — so the mock below
        // expects authorizeDeposit() exactly ONCE, not twice. If the
        // second attempt reached Stripe, this test would fail on the
        // Mockery expectation, not just the row count.
        $gateway = $this->registerMockGateway();
        $gateway->shouldReceive('authorizeDeposit')->once()->andReturnUsing(fn ($booking, $amount) => Payment::create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'gateway' => 'stripe',
            'status' => 'pending',
            'amount' => $amount,
            'provider_reference' => 'pi_test_first',
            'metadata' => ['client_secret' => 'secret'],
        ]));

        $payload = [
            'pickup_at' => now()->addDay()->toDateTimeString(),
            'return_at' => now()->addDays(3)->toDateTimeString(),
            'guest_name' => 'First Guest',
            'guest_email' => 'first@example.com',
            'guest_phone' => '0600000000',
        ];

        $this->post("/vehicles/{$this->vehicle->id}/book", $payload)->assertOk();

        $response = $this->post("/vehicles/{$this->vehicle->id}/book", [
            ...$payload,
            'guest_name' => 'Second Guest',
            'guest_email' => 'second@example.com',
        ]);

        $response->assertSessionHasErrors(['pickup_at']);
        $this->assertSame(1, Booking::count());
    }

    public function test_confirm_finalizes_the_booking_once_the_gateway_confirms_authorization(): void
    {
        Event::fake([BookingConfirmed::class]);

        $gateway = $this->registerMockGateway();
        $gateway->shouldReceive('authorizeDeposit')->once()->andReturnUsing(fn ($booking, $amount) => Payment::create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'gateway' => 'stripe',
            'status' => 'pending',
            'amount' => $amount,
            'provider_reference' => 'pi_test_confirm',
            'metadata' => ['client_secret' => 'secret'],
        ]));

        $this->post("/vehicles/{$this->vehicle->id}/book", [
            'pickup_at' => now()->addDay()->toDateTimeString(),
            'return_at' => now()->addDays(3)->toDateTimeString(),
            'guest_name' => 'Real Guest',
            'guest_email' => 'confirm-guest@example.com',
            'guest_phone' => '0600000000',
        ]);

        $booking = Booking::where('guest_email', 'confirm-guest@example.com')->first();

        $gateway->shouldReceive('syncAuthorizationStatus')
            ->once()
            ->andReturnUsing(function (Payment $authorization) {
                $authorization->update(['status' => 'authorized']);

                return $authorization->fresh();
            });

        $response = $this->post("/bookings/{$booking->id}/confirm");

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertNull($booking->hold_expires_at);

        $redirectLocation = $response->headers->get('Location');
        $this->assertNotNull($redirectLocation);
        $this->assertStringContainsString('signature=', $redirectLocation);
        $this->get($redirectLocation)->assertOk();

        Event::assertDispatched(BookingConfirmed::class);
    }

    public function test_confirm_rejects_when_the_gateway_says_not_yet_authorized(): void
    {
        $gateway = $this->registerMockGateway();
        $gateway->shouldReceive('authorizeDeposit')->once()->andReturnUsing(fn ($booking, $amount) => Payment::create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'gateway' => 'stripe',
            'status' => 'pending',
            'amount' => $amount,
            'provider_reference' => 'pi_test_not_yet',
            'metadata' => ['client_secret' => 'secret'],
        ]));

        $this->post("/vehicles/{$this->vehicle->id}/book", [
            'pickup_at' => now()->addDay()->toDateTimeString(),
            'return_at' => now()->addDays(3)->toDateTimeString(),
            'guest_name' => 'Real Guest',
            'guest_email' => 'not-yet@example.com',
            'guest_phone' => '0600000000',
        ]);

        $booking = Booking::where('guest_email', 'not-yet@example.com')->first();

        $gateway->shouldReceive('syncAuthorizationStatus')
            ->once()
            ->andReturnUsing(fn (Payment $authorization) => $authorization);

        $response = $this->post("/bookings/{$booking->id}/confirm");

        $response->assertSessionHasErrors(['pickup_at']);
        $booking->refresh();
        $this->assertSame('pending', $booking->status);
    }

    public function test_checkout_page_receives_active_locations_for_the_pickers(): void
    {
        $other = Location::factory()->create(['is_active' => true]);
        Location::factory()->create(['is_active' => false]);

        $response = $this->get("/vehicles/{$this->vehicle->id}/book?pickup_at=".now()->addDay()->toDateTimeString().'&return_at='.now()->addDays(3)->toDateTimeString());

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Bookings/Checkout')
            ->has('locations', 2)
            ->where('pickupLocationId', $this->location->id)
            ->where('returnLocationId', $this->location->id)
        );

        $page = $response->viewData('page');
        $locationIds = array_map(fn ($location) => $location['id'], $page['props']['locations']);
        sort($locationIds);
        $this->assertSame([$this->location->id, $other->id], $locationIds);
    }

    public function test_store_persists_distinct_pickup_and_return_locations_for_a_one_way_rental(): void
    {
        $gateway = $this->registerMockGateway();
        $gateway->shouldReceive('authorizeDeposit')->once()->andReturnUsing(fn ($booking, $amount) => Payment::create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'gateway' => 'stripe',
            'status' => 'pending',
            'amount' => $amount,
            'provider_reference' => 'pi_test_one_way',
            'metadata' => ['client_secret' => 'secret'],
        ]));

        $returnLocation = Location::factory()->create(['is_active' => true]);

        $response = $this->post("/vehicles/{$this->vehicle->id}/book", [
            'pickup_at' => now()->addDay()->toDateTimeString(),
            'return_at' => now()->addDays(3)->toDateTimeString(),
            'guest_name' => 'One Way Guest',
            'guest_email' => 'one-way@example.com',
            'guest_phone' => '0600000000',
            'pickup_location_id' => $this->location->id,
            'return_location_id' => $returnLocation->id,
        ]);

        $booking = Booking::where('guest_email', 'one-way@example.com')->first();

        $this->assertNotNull($booking);
        $this->assertSame($this->location->id, $booking->pickup_location_id);
        $this->assertSame($returnLocation->id, $booking->return_location_id);
        $response->assertOk();
    }

    public function test_store_defaults_both_locations_to_the_vehicles_location_when_omitted(): void
    {
        $gateway = $this->registerMockGateway();
        $gateway->shouldReceive('authorizeDeposit')->once()->andReturnUsing(fn ($booking, $amount) => Payment::create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'gateway' => 'stripe',
            'status' => 'pending',
            'amount' => $amount,
            'provider_reference' => 'pi_test_default_loc',
            'metadata' => ['client_secret' => 'secret'],
        ]));

        $this->post("/vehicles/{$this->vehicle->id}/book", [
            'pickup_at' => now()->addDay()->toDateTimeString(),
            'return_at' => now()->addDays(3)->toDateTimeString(),
            'guest_name' => 'Default Loc Guest',
            'guest_email' => 'default-loc@example.com',
            'guest_phone' => '0600000000',
        ])->assertOk();

        $booking = Booking::where('guest_email', 'default-loc@example.com')->first();

        $this->assertNotNull($booking);
        $this->assertSame($this->location->id, $booking->pickup_location_id);
        $this->assertSame($this->location->id, $booking->return_location_id);
    }

    public function test_store_returns_the_existing_booking_when_the_same_idempotency_key_is_sent_again(): void
    {
        $gateway = $this->registerMockGateway();
        $gateway->shouldReceive('authorizeDeposit')
            ->once() // must NOT be called again for the retry
            ->andReturnUsing(fn ($booking, $amount) => Payment::create([
                'booking_id' => $booking->id,
                'type' => 'deposit_authorization',
                'gateway' => 'stripe',
                'status' => 'pending',
                'amount' => $amount,
                'provider_reference' => 'pi_test_idem',
                'metadata' => ['client_secret' => 'secret_idem'],
            ]));

        $payload = [
            'pickup_at' => now()->addDay()->toDateTimeString(),
            'return_at' => now()->addDays(3)->toDateTimeString(),
            'guest_name' => 'Idem Guest',
            'guest_email' => 'idem@example.com',
            'guest_phone' => '0600000000',
        ];

        $this->post("/vehicles/{$this->vehicle->id}/book", $payload, ['Idempotency-Key' => 'idem-key-1'])
            ->assertOk();

        $booking = Booking::where('guest_email', 'idem@example.com')->first();

        $this->assertNotNull($booking);
        $this->assertSame('idem-key-1', $booking->idempotency_key);

        // Same Idempotency-Key retried — resolved from the existing booking
        // before the gateway is ever consulted (200, not 201; no duplicate).
        $this->post("/vehicles/{$this->vehicle->id}/book", $payload, ['Idempotency-Key' => 'idem-key-1'])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Bookings/Payment')
                ->where('bookingId', $booking->id)
                ->where('clientSecret', 'secret_idem')
            );

        $this->assertSame(1, Booking::count());
    }

    protected function tearDown(): void
    {
        PaymentGatewayRegistry::flush();

        parent::tearDown();
    }
}
