<?php

namespace Tests\Feature\Filament;

use App\Core\Contracts\PaymentGateway;
use App\Core\Events\BookingCancelled;
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

    public function test_release_deposit_action_calls_the_gateway_and_shows_when_an_authorization_exists(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $booking = Booking::factory()->create();
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

        $booking = Booking::factory()->create();
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

    protected function tearDown(): void
    {
        PaymentGatewayRegistry::flush();

        parent::tearDown();
    }
}
