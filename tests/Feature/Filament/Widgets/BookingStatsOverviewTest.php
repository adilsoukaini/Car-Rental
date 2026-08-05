<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\BookingStatsOverview;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Total Booking Value" deliberately excludes pending/expired/cancelled —
 * only confirmed/checked_out/returned bookings represent committed value.
 * Exact-number boundary proof, same standard as PriceCalculationTest.
 */
class BookingStatsOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function render(): mixed
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        return Livewire::test(BookingStatsOverview::class);
    }

    public function test_total_booking_value_only_counts_confirmed_and_beyond(): void
    {
        Booking::factory()->create(['status' => 'confirmed', 'total_price' => 100]);
        Booking::factory()->create(['status' => 'checked_out', 'total_price' => 200]);
        Booking::factory()->create(['status' => 'returned', 'total_price' => 300]);
        Booking::factory()->create(['status' => 'pending', 'total_price' => 9999]);
        Booking::factory()->create(['status' => 'expired', 'total_price' => 9999]);
        Booking::factory()->create(['status' => 'cancelled', 'total_price' => 9999]);

        $this->render()->assertSee('600.00 MAD');
    }

    public function test_avg_booking_value_is_computed_over_the_same_real_bookings_only(): void
    {
        Booking::factory()->create(['status' => 'confirmed', 'total_price' => 100]);
        Booking::factory()->create(['status' => 'confirmed', 'total_price' => 300]);
        Booking::factory()->create(['status' => 'cancelled', 'total_price' => 1]);

        $this->render()->assertSee('200.00 MAD');
    }

    public function test_distinct_customers_counts_registered_users_and_guests_separately(): void
    {
        $user = User::factory()->create();
        Booking::factory()->create(['status' => 'confirmed', 'user_id' => $user->id, 'guest_email' => null]);
        Booking::factory()->create(['status' => 'confirmed', 'user_id' => $user->id, 'guest_email' => null]);
        Booking::factory()->create(['status' => 'confirmed', 'user_id' => null, 'guest_email' => 'a@example.com']);
        Booking::factory()->create(['status' => 'confirmed', 'user_id' => null, 'guest_email' => 'b@example.com']);

        $this->render()->assertSee('3');
    }

    public function test_no_real_bookings_shows_zeroed_defaults(): void
    {
        Booking::factory()->create(['status' => 'pending']);

        $this->render()->assertSee('0.00 MAD');
    }
}
