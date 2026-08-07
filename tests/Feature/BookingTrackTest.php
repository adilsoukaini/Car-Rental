<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTrackTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tracking_page_renders_the_lookup_form(): void
    {
        $response = $this->get('/bookings/track');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Bookings/Track'));
    }

    public function test_a_guest_can_lookup_a_booking_and_is_redirected_to_a_signed_url(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => 'guest@example.com',
        ]);

        $response = $this->post('/bookings/track', [
            'booking_number' => $booking->booking_number,
            'email' => 'guest@example.com',
        ]);

        $response->assertRedirect();

        $redirectLocation = $response->headers->get('Location');
        $this->assertNotNull($redirectLocation);
        $this->assertStringContainsString('bookings/'.$booking->id, $redirectLocation);
        $this->assertStringContainsString('signature=', $redirectLocation);

        // The signed URL genuinely loads the booking.
        $this->get($redirectLocation)->assertOk();
    }

    public function test_the_owner_can_lookup_their_booking_and_is_redirected_to_a_plain_route(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'guest_email' => null,
        ]);

        $response = $this->actingAs($user)->post('/bookings/track', [
            'booking_number' => $booking->booking_number,
            'email' => $user->email,
        ]);

        $response->assertRedirect(route('bookings.show', ['booking' => $booking->id]));

        $redirectLocation = $response->headers->get('Location');
        $this->assertNotNull($redirectLocation);
        $this->assertStringNotContainsString('signature=', $redirectLocation);
    }

    public function test_a_registered_owner_can_be_looked_up_through_their_account_email_as_a_guest(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'guest_email' => null,
        ]);

        // No authentication — anyone who knows the booking_number + the user's
        // email gets a signed URL, same credential model as the email link.
        $response = $this->post('/bookings/track', [
            'booking_number' => $booking->booking_number,
            'email' => $user->email,
        ]);

        $response->assertRedirect();

        $redirectLocation = $response->headers->get('Location');
        $this->assertNotNull($redirectLocation);
        $this->assertStringContainsString('signature=', $redirectLocation);
        $this->get($redirectLocation)->assertOk();
    }

    public function test_lookup_with_a_wrong_email_is_rejected(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => 'guest@example.com',
        ]);

        $response = $this->post('/bookings/track', [
            'booking_number' => $booking->booking_number,
            'email' => 'wrong@example.com',
        ]);

        $response->assertSessionHasErrors('booking_number');
        $response->assertRedirect('/bookings/track');
    }

    public function test_lookup_with_an_unknown_booking_number_is_rejected(): void
    {
        $response = $this->post('/bookings/track', [
            'booking_number' => 'UNKNOWN1234',
            'email' => 'guest@example.com',
        ]);

        $response->assertSessionHasErrors('booking_number');
        $response->assertRedirect('/bookings/track');
    }

    public function test_lookup_requires_both_fields(): void
    {
        $response = $this->post('/bookings/track', []);

        $response->assertSessionHasErrors(['booking_number', 'email']);
    }
}
