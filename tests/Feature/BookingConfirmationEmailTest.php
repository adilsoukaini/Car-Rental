<?php

namespace Tests\Feature;

use App\Core\Events\BookingConfirmed;
use App\Core\Listeners\SendBookingConfirmationEmail;
use App\Mail\BookingConfirmation;
use App\Models\Booking;
use App\Models\Location;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Plugins\BookingEngine\BookingEngineServiceProvider;
use Plugins\BookingEngine\Support\BookingCreator;
use Tests\TestCase;

class BookingConfirmationEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(BookingEngineServiceProvider::class);
    }

    public function test_booking_creator_dispatches_booking_confirmed(): void
    {
        Event::fake([BookingConfirmed::class]);

        $location = Location::factory()->create();
        $vehicle = Vehicle::factory()->create(['location_id' => $location->id]);

        $booking = app(BookingCreator::class)->create([
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

        Event::assertDispatched(BookingConfirmed::class, fn (BookingConfirmed $event) => $event->booking->is($booking));
    }

    public function test_listener_sends_confirmation_to_guest_email_when_present(): void
    {
        Mail::fake();

        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => 'guest@example.com',
        ]);

        (new SendBookingConfirmationEmail)->handle(new BookingConfirmed($booking));

        Mail::assertQueued(BookingConfirmation::class, function (BookingConfirmation $mail) use ($booking) {
            return $mail->hasTo('guest@example.com') && $mail->booking->is($booking);
        });
    }

    public function test_guest_confirmation_email_links_to_a_signed_url(): void
    {
        Mail::fake();

        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => 'guest@example.com',
        ]);

        (new SendBookingConfirmationEmail)->handle(new BookingConfirmed($booking));

        Mail::assertQueued(BookingConfirmation::class, function (BookingConfirmation $mail) use ($booking) {
            $html = $mail->render();

            return str_contains($html, 'bookings/'.$booking->id)
                && str_contains($html, 'signature=');
        });
    }

    public function test_listener_falls_back_to_user_email_when_no_guest_email(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'user@example.com']);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'guest_email' => null,
        ]);

        (new SendBookingConfirmationEmail)->handle(new BookingConfirmed($booking));

        Mail::assertQueued(BookingConfirmation::class, fn (BookingConfirmation $mail) => $mail->hasTo('user@example.com'));
    }

    public function test_registered_user_confirmation_email_links_to_a_plain_route_without_a_signature(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'user@example.com']);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'guest_email' => null,
        ]);

        (new SendBookingConfirmationEmail)->handle(new BookingConfirmed($booking));

        Mail::assertQueued(BookingConfirmation::class, function (BookingConfirmation $mail) use ($booking) {
            $html = $mail->render();

            return str_contains($html, 'bookings/'.$booking->id)
                && ! str_contains($html, 'signature=');
        });
    }

    public function test_listener_sends_nothing_when_no_recipient_can_be_resolved(): void
    {
        Mail::fake();

        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => null,
        ]);

        (new SendBookingConfirmationEmail)->handle(new BookingConfirmed($booking));

        Mail::assertNothingSent();
    }

    public function test_mailable_has_the_expected_subject(): void
    {
        $booking = Booking::factory()->create();

        $mail = new BookingConfirmation($booking);

        $this->assertSame('Booking confirmed — #'.$booking->booking_number, $mail->envelope()->subject);
    }
}
