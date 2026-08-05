<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BookingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owning_user_can_view_their_booking(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/bookings/{$booking->id}");

        $response->assertOk();
    }

    public function test_a_different_authenticated_user_cannot_view_someone_elses_booking_without_a_signature(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->get("/bookings/{$booking->id}");

        $response->assertForbidden();
    }

    public function test_a_guest_cannot_view_a_booking_without_a_valid_signature(): void
    {
        $booking = Booking::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.com']);

        $response = $this->get("/bookings/{$booking->id}");

        $response->assertForbidden();
    }

    public function test_a_guest_with_a_valid_signed_url_can_view_the_booking(): void
    {
        $booking = Booking::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.com']);

        $signedUrl = URL::temporarySignedRoute('bookings.show', now()->addHours(48), ['booking' => $booking->id]);

        $response = $this->get($signedUrl);

        $response->assertOk();
    }

    public function test_a_guest_with_an_expired_signature_cannot_view_the_booking(): void
    {
        $booking = Booking::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.com']);

        $expiredUrl = URL::temporarySignedRoute('bookings.show', now()->subHour(), ['booking' => $booking->id]);

        $response = $this->get($expiredUrl);

        $response->assertForbidden();
    }
}
