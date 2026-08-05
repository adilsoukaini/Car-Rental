<?php

namespace Tests\Feature\Reviews;

use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\Reviews\Services\VerifiedRentalChecker;
use Tests\TestCase;

/**
 * Explicit boundary proof, per the confirmed decision: a genuinely
 * `returned` booking sets verified-rental true; anything short of that
 * (pending/confirmed/checked_out) sets it false — NOT a copy-paste of the
 * source e-commerce project's `payment_status === 'paid'` bar, which would
 * have defaulted to `confirmed` here.
 */
class VerifiedRentalCheckerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->vehicle = Vehicle::factory()->create();
    }

    private function check(): bool
    {
        return app(VerifiedRentalChecker::class)->check($this->user, $this->vehicle);
    }

    public function test_a_returned_booking_is_verified(): void
    {
        Booking::factory()->create([
            'user_id' => $this->user->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'returned',
        ]);

        $this->assertTrue($this->check());
    }

    public function test_a_pending_booking_is_not_verified(): void
    {
        Booking::factory()->create([
            'user_id' => $this->user->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'pending',
        ]);

        $this->assertFalse($this->check());
    }

    public function test_a_confirmed_but_not_yet_picked_up_booking_is_not_verified(): void
    {
        Booking::factory()->create([
            'user_id' => $this->user->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'confirmed',
        ]);

        $this->assertFalse($this->check());
    }

    public function test_a_checked_out_but_not_yet_returned_booking_is_not_verified(): void
    {
        Booking::factory()->create([
            'user_id' => $this->user->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'checked_out',
        ]);

        $this->assertFalse($this->check());
    }

    public function test_a_cancelled_booking_is_not_verified(): void
    {
        Booking::factory()->create([
            'user_id' => $this->user->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'cancelled',
        ]);

        $this->assertFalse($this->check());
    }

    public function test_a_returned_booking_for_a_different_vehicle_does_not_verify_this_one(): void
    {
        $otherVehicle = Vehicle::factory()->create();

        Booking::factory()->create([
            'user_id' => $this->user->id,
            'vehicle_id' => $otherVehicle->id,
            'status' => 'returned',
        ]);

        $this->assertFalse($this->check());
    }

    public function test_a_different_users_returned_booking_does_not_verify_this_user(): void
    {
        $otherUser = User::factory()->create();

        Booking::factory()->create([
            'user_id' => $otherUser->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'returned',
        ]);

        $this->assertFalse($this->check());
    }

    public function test_no_booking_at_all_is_not_verified(): void
    {
        $this->assertFalse($this->check());
    }
}
