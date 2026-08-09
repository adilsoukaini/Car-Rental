<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        // Booking::factory()->create() pulls in a Vehicle via its factory,
        // whose Searchable observer would POST to Meilisearch (not running in
        // the automated suite). Pin the offline `database` driver — see
        // VehicleApiTest::setUp for the full rationale.
        config(['scout.driver' => 'database']);
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
            ->assertJsonCount(2);
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
}
