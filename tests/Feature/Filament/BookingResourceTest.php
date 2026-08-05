<?php

namespace Tests\Feature\Filament;

use App\Core\Contracts\PaymentGateway;
use App\Core\Events\BookingCancelled;
use App\Core\Events\VehicleCheckedOut;
use App\Core\Events\VehicleReturned;
use App\Core\Support\PaymentGatewayRegistry;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Mockery;
use Plugins\BookingEngine\BookingEngineServiceProvider;
use Plugins\BookingEngine\Support\BookingCreator;
use Tests\TestCase;

class BookingResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_the_booking_list(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        Booking::factory()->create();

        $response = $this->get('/admin/bookings');

        $response->assertOk();
    }

    public function test_customer_cannot_access_the_admin_panel(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer);

        $response = $this->get('/admin/bookings');

        $response->assertRedirect(route('home'));
    }

    public function test_deposit_actions_are_hidden_when_there_is_no_active_authorization(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $booking = Booking::factory()->create();

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHidden('releaseDeposit')
            ->assertActionHidden('captureDeposit');
    }

    public function test_deposit_actions_are_hidden_for_a_confirmed_booking_even_with_an_active_authorization(): void
    {
        // A just-confirmed booking with a live hold (the real post-Phase-B
        // checkout state) must NOT show these yet — only a real 'returned'
        // status does, now that the checkout/return lifecycle is real
        // (this replaced the pickup_at->isPast() interim proxy).
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $booking = Booking::factory()->create(['status' => 'confirmed']);
        Payment::factory()->create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'status' => 'authorized',
            'gateway' => 'stripe',
        ]);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHidden('releaseDeposit')
            ->assertActionHidden('captureDeposit');
    }

    public function test_deposit_actions_are_hidden_for_a_checked_out_booking_even_with_an_active_authorization(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $booking = Booking::factory()->create(['status' => 'checked_out']);
        Payment::factory()->create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'status' => 'authorized',
            'gateway' => 'stripe',
        ]);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHidden('releaseDeposit')
            ->assertActionHidden('captureDeposit');
    }

    public function test_deposit_actions_are_visible_once_the_booking_is_returned(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $booking = Booking::factory()->create(['status' => 'returned']);
        Payment::factory()->create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'status' => 'authorized',
            'gateway' => 'stripe',
        ]);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('releaseDeposit')
            ->assertActionVisible('captureDeposit');
    }

    public function test_release_deposit_action_calls_the_gateway_and_shows_when_an_authorization_exists(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $booking = Booking::factory()->create(['status' => 'returned']);
        $authorization = Payment::factory()->create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'status' => 'authorized',
            'gateway' => 'stripe',
            'amount' => 900.00,
            'provider_reference' => 'pi_test_view',
        ]);

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('id')->andReturn('stripe');
        $gateway->shouldReceive('releaseDeposit')
            ->once()
            ->with(Mockery::on(fn (Payment $p) => $p->is($authorization)))
            ->andReturn(Payment::factory()->make(['type' => 'deposit_release', 'status' => 'succeeded']));
        PaymentGatewayRegistry::register($gateway, 'payments-stripe');

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('releaseDeposit')
            ->callAction('releaseDeposit');
    }

    public function test_capture_deposit_action_calls_the_gateway_with_the_entered_amount(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $booking = Booking::factory()->create(['status' => 'returned']);
        $authorization = Payment::factory()->create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'status' => 'authorized',
            'gateway' => 'stripe',
            'amount' => 900.00,
            'provider_reference' => 'pi_test_view_2',
        ]);

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('id')->andReturn('stripe');
        $gateway->shouldReceive('captureDeposit')
            ->once()
            ->with(
                Mockery::on(fn (Payment $p) => $p->is($authorization)),
                200.0,
            )
            ->andReturn(Payment::factory()->make(['type' => 'deposit_capture', 'status' => 'succeeded']));
        PaymentGatewayRegistry::register($gateway, 'payments-stripe');

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('captureDeposit', data: ['amount' => 200.0]);
    }

    public function test_check_out_action_is_visible_only_for_a_confirmed_booking(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $confirmed = Booking::factory()->create(['status' => 'confirmed']);
        Livewire::test(ViewBooking::class, ['record' => $confirmed->getRouteKey()])
            ->assertActionVisible('checkOut');

        $pending = Booking::factory()->create(['status' => 'pending']);
        Livewire::test(ViewBooking::class, ['record' => $pending->getRouteKey()])
            ->assertActionHidden('checkOut');
    }

    public function test_check_out_action_sets_status_dispatches_and_marks_the_vehicle_rented(): void
    {
        Event::fake([VehicleCheckedOut::class]);

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $vehicle = Vehicle::factory()->create(['status' => 'available']);
        $booking = Booking::factory()->create(['status' => 'confirmed', 'vehicle_id' => $vehicle->id]);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('checkOut');

        $this->assertSame('checked_out', $booking->fresh()->status);
        $this->assertSame('rented', $vehicle->fresh()->status);

        Event::assertDispatched(VehicleCheckedOut::class, fn (VehicleCheckedOut $event) => $event->booking->is($booking));
    }

    public function test_mark_returned_action_is_visible_only_for_a_checked_out_booking(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $checkedOut = Booking::factory()->create(['status' => 'checked_out']);
        Livewire::test(ViewBooking::class, ['record' => $checkedOut->getRouteKey()])
            ->assertActionVisible('markReturned');

        $confirmed = Booking::factory()->create(['status' => 'confirmed']);
        Livewire::test(ViewBooking::class, ['record' => $confirmed->getRouteKey()])
            ->assertActionHidden('markReturned');
    }

    public function test_mark_returned_action_sets_status_dispatches_and_frees_the_vehicle(): void
    {
        Event::fake([VehicleReturned::class]);

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $vehicle = Vehicle::factory()->create(['status' => 'rented']);
        $booking = Booking::factory()->create(['status' => 'checked_out', 'vehicle_id' => $vehicle->id]);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('markReturned');

        $this->assertSame('returned', $booking->fresh()->status);
        $this->assertSame('available', $vehicle->fresh()->status);

        Event::assertDispatched(VehicleReturned::class, fn (VehicleReturned $event) => $event->booking->is($booking));
    }

    public function test_mark_returned_relocates_the_vehicle_for_a_real_one_way_booking(): void
    {
        $this->app->register(BookingEngineServiceProvider::class);

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $pickupLocation = Location::factory()->create();
        $returnLocation = Location::factory()->create();
        $vehicle = Vehicle::factory()->create(['status' => 'rented', 'location_id' => $pickupLocation->id]);
        $booking = Booking::factory()->create([
            'status' => 'checked_out',
            'vehicle_id' => $vehicle->id,
            'pickup_location_id' => $pickupLocation->id,
            'return_location_id' => $returnLocation->id,
        ]);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('markReturned');

        $this->assertSame($returnLocation->id, $vehicle->fresh()->location_id);
    }

    public function test_cancel_action_is_visible_for_a_confirmed_booking(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $booking = Booking::factory()->create(['status' => 'confirmed']);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('cancelBooking');
    }

    public function test_cancel_action_is_hidden_once_already_cancelled(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $booking = Booking::factory()->create(['status' => 'cancelled']);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHidden('cancelBooking');
    }

    public function test_cancel_action_sets_status_and_dispatches_booking_cancelled(): void
    {
        Event::fake([BookingCancelled::class]);

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $booking = Booking::factory()->create(['status' => 'confirmed']);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('cancelBooking');

        $this->assertSame('cancelled', $booking->fresh()->status);

        Event::assertDispatched(BookingCancelled::class, fn (BookingCancelled $event) => $event->booking->is($booking) && $event->booking->status === 'cancelled');
    }

    public function test_a_cancelled_bookings_vehicle_becomes_bookable_again_for_the_same_dates(): void
    {
        $this->app->register(BookingEngineServiceProvider::class);

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $location = Location::factory()->create();
        $vehicle = Vehicle::factory()->create(['location_id' => $location->id]);

        $original = app(BookingCreator::class)->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => null,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '0600000000',
            'pickup_location_id' => $location->id,
            'return_location_id' => $location->id,
            'pickup_at' => now()->addDay(),
            'return_at' => now()->addDays(3),
        ]);

        Livewire::test(ViewBooking::class, ['record' => $original->getRouteKey()])
            ->callAction('cancelBooking');

        $rebooking = app(BookingCreator::class)->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => null,
            'guest_name' => 'Second Guest',
            'guest_email' => 'second-guest@example.com',
            'guest_phone' => '0600000001',
            'pickup_location_id' => $location->id,
            'return_location_id' => $location->id,
            'pickup_at' => $original->pickup_at,
            'return_at' => $original->return_at,
        ]);

        $this->assertSame('confirmed', $rebooking->status);
    }

    public function test_cancelling_a_booking_with_no_authorization_does_not_touch_any_gateway(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('id')->andReturn('stripe');
        $gateway->shouldNotReceive('releaseDeposit');
        $gateway->shouldNotReceive('captureDeposit');
        PaymentGatewayRegistry::register($gateway, 'payments-stripe');

        $booking = Booking::factory()->create(['status' => 'confirmed', 'pickup_at' => now()->addDay()]);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('cancelBooking');

        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_cancelling_well_before_pickup_releases_the_full_deposit(): void
    {
        $this->app->register(BookingEngineServiceProvider::class);

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        // 7+ days out -> 100% refund tier -> full release, no capture.
        $booking = Booking::factory()->create(['status' => 'confirmed', 'pickup_at' => now()->addDays(10)]);
        $authorization = Payment::factory()->create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'status' => 'authorized',
            'gateway' => 'stripe',
            'amount' => 900.00,
        ]);

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('id')->andReturn('stripe');
        $gateway->shouldReceive('releaseDeposit')
            ->once()
            ->with(Mockery::on(fn (Payment $p) => $p->is($authorization)));
        $gateway->shouldNotReceive('captureDeposit');
        PaymentGatewayRegistry::register($gateway, 'payments-stripe');

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('cancelBooking');

        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_cancelling_shortly_before_pickup_captures_a_partial_forfeit_amount(): void
    {
        $this->app->register(BookingEngineServiceProvider::class);

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        // 3 days out -> the 2-day/50% tier -> half forfeited, half implicitly
        // released by Stripe's own partial-capture behavior (confirmed
        // separately, not this test's concern).
        $booking = Booking::factory()->create(['status' => 'confirmed', 'pickup_at' => now()->addDays(3)]);
        $authorization = Payment::factory()->create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'status' => 'authorized',
            'gateway' => 'stripe',
            'amount' => 900.00,
        ]);

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('id')->andReturn('stripe');
        $gateway->shouldReceive('captureDeposit')
            ->once()
            ->with(Mockery::on(fn (Payment $p) => $p->is($authorization)), 450.0);
        $gateway->shouldNotReceive('releaseDeposit');
        PaymentGatewayRegistry::register($gateway, 'payments-stripe');

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('cancelBooking');
    }

    public function test_cancelling_right_before_pickup_forfeits_the_entire_deposit(): void
    {
        $this->app->register(BookingEngineServiceProvider::class);

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        // Same day as pickup -> below every tier -> 0% refund.
        $booking = Booking::factory()->create(['status' => 'confirmed', 'pickup_at' => now()->addHours(2)]);
        $authorization = Payment::factory()->create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'status' => 'authorized',
            'gateway' => 'stripe',
            'amount' => 900.00,
        ]);

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('id')->andReturn('stripe');
        $gateway->shouldReceive('captureDeposit')
            ->once()
            ->with(Mockery::on(fn (Payment $p) => $p->is($authorization)), 900.0);
        $gateway->shouldNotReceive('releaseDeposit');
        PaymentGatewayRegistry::register($gateway, 'payments-stripe');

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('cancelBooking');
    }

    protected function tearDown(): void
    {
        PaymentGatewayRegistry::flush();

        parent::tearDown();
    }
}
