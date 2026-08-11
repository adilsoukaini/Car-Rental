<?php

namespace Tests\Feature;

use App\Core\Events\BookingCancelled;
use App\Core\Events\BookingConfirmed;
use App\Core\Events\VehicleCheckedOut;
use App\Core\Events\VehicleReturned;
use App\Core\Listeners\SendPushNotificationOnBookingEvents;
use App\Models\Booking;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Booking-lifecycle push notifications. Verifies the listener's message
 * content (French, per the storefront default locale), the recipient (only
 * the booking's registered user), and the Expo payload shape the mobile app
 * deep-links from (`type` + `bookingId`).
 */
class SendPushNotificationOnBookingEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Booking::factory() pulls in a Vehicle via its factory, whose Scout
        // Searchable observer would POST to Meilisearch (not running in the
        // automated suite). Pin the offline `database` driver — same rationale
        // as VehicleApiTest::setUp.
        config(['scout.driver' => 'database']);
    }

    private function bookingFor(User $user): Booking
    {
        $location = Location::factory()->create();

        return Booking::factory()->create([
            'user_id' => $user->id,
            'guest_email' => null,
            'pickup_location_id' => $location->id,
            'return_location_id' => $location->id,
        ]);
    }

    private function listen(BookingConfirmed|BookingCancelled|VehicleCheckedOut|VehicleReturned $event): void
    {
        app(SendPushNotificationOnBookingEvents::class)->handle($event);
    }

    public function test_booking_confirmed_sends_a_french_push_with_deeplink_data(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $user->pushNotificationTokens()->create(['token' => 'ExponentPushToken[abc123]', 'platform' => 'expo']);
        $booking = $this->bookingFor($user);

        $this->listen(new BookingConfirmed($booking));

        Http::assertSent(function ($request) use ($booking) {
            $payload = json_decode($request->body(), true);

            return $request->url() === config('services.expo.push_url')
                && $payload[0]['to'] === 'ExponentPushToken[abc123]'
                && $payload[0]['title'] === 'Car Rental'
                && $payload[0]['body'] === 'Votre réservation #'.$booking->booking_number.' est confirmée'
                && $payload[0]['data']['bookingId'] === $booking->id
                && $payload[0]['data']['type'] === 'booking_confirmed';
        });
    }

    public function test_booking_cancelled_sends_a_french_push(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $user->pushNotificationTokens()->create(['token' => 'ExponentPushToken[abc123]', 'platform' => 'expo']);
        $booking = $this->bookingFor($user);

        $this->listen(new BookingCancelled($booking));

        Http::assertSent(function ($request) use ($booking) {
            $payload = json_decode($request->body(), true);

            return $payload[0]['body'] === 'Votre réservation #'.$booking->booking_number.' a été annulée'
                && $payload[0]['data']['type'] === 'booking_cancelled';
        });
    }

    public function test_vehicle_checked_out_sends_a_push(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $user->pushNotificationTokens()->create(['token' => 'ExponentPushToken[abc123]', 'platform' => 'expo']);
        $booking = $this->bookingFor($user);

        $this->listen(new VehicleCheckedOut($booking));

        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);

            return $payload[0]['body'] === 'Vous avez récupéré votre véhicule'
                && $payload[0]['data']['type'] === 'vehicle_checked_out';
        });
    }

    public function test_vehicle_returned_sends_a_push(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $user->pushNotificationTokens()->create(['token' => 'ExponentPushToken[abc123]', 'platform' => 'expo']);
        $booking = $this->bookingFor($user);

        $this->listen(new VehicleReturned($booking));

        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);

            return $payload[0]['body'] === 'Véhicule retourné avec succès'
                && $payload[0]['data']['type'] === 'vehicle_returned';
        });
    }

    public function test_dispatching_booking_confirmed_fires_the_registered_queued_listener(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $user->pushNotificationTokens()->create(['token' => 'ExponentPushToken[abc123]', 'platform' => 'expo']);
        $booking = $this->bookingFor($user);

        // Dispatches through the Event::listen() registration in
        // AppServiceProvider; QUEUE_CONNECTION=sync runs the ShouldQueue
        // listener immediately, so the push HTTP call happens here.
        BookingConfirmed::dispatch($booking);

        Http::assertSent(fn ($request) => json_decode($request->body(), true)[0]['body']
            === 'Votre réservation #'.$booking->booking_number.' est confirmée');
    }

    public function test_guest_booking_sends_no_push(): void
    {
        Http::fake();

        $location = Location::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => 'guest@example.com',
            'pickup_location_id' => $location->id,
            'return_location_id' => $location->id,
        ]);

        $this->listen(new BookingConfirmed($booking));

        Http::assertNothingSent();
    }

    public function test_user_with_no_registered_tokens_sends_no_push(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $booking = $this->bookingFor($user);

        $this->listen(new BookingConfirmed($booking));

        Http::assertNothingSent();
    }
}
