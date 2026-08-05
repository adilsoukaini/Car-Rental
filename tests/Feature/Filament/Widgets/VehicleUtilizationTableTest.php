<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\VehicleUtilizationTable;
use App\Models\Booking;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VehicleUtilizationTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pinned so "now" inside the widget's query and this test's
        // booking dates are computed from the exact same instant — small
        // clock drift between setup and widget execution otherwise
        // produces fractional-day noise (e.g. 19.5 vs 20.0).
        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_utilization_percent_is_computed_exactly_for_a_booking_fully_inside_the_window(): void
    {
        $vehicle = Vehicle::factory()->create();

        // Exactly 6 of the last 30 days booked -> 20.0%.
        Booking::factory()->create([
            'vehicle_id' => $vehicle->id,
            'status' => 'confirmed',
            'pickup_at' => now()->subDays(10),
            'return_at' => now()->subDays(4),
        ]);

        $rows = (new VehicleUtilizationTable)->getRows();
        $row = collect($rows)->firstWhere('vehicle.id', $vehicle->id);

        $this->assertNotNull($row);
        $this->assertSame(6.0, $row['bookedDays']);
        $this->assertSame(20.0, $row['utilizationPercent']);
    }

    public function test_a_booking_starting_before_the_window_is_clamped_to_the_window_start(): void
    {
        $vehicle = Vehicle::factory()->create();

        // Pickup 40 days ago (before the 30-day window even starts),
        // return 25 days ago -> only the last 5 days of the booking fall
        // inside the window.
        Booking::factory()->create([
            'vehicle_id' => $vehicle->id,
            'status' => 'confirmed',
            'pickup_at' => now()->subDays(40),
            'return_at' => now()->subDays(25),
        ]);

        $rows = (new VehicleUtilizationTable)->getRows();
        $row = collect($rows)->firstWhere('vehicle.id', $vehicle->id);

        $this->assertSame(5.0, $row['bookedDays']);
    }

    public function test_pending_and_cancelled_bookings_do_not_count_as_utilization(): void
    {
        $vehicle = Vehicle::factory()->create();

        Booking::factory()->create([
            'vehicle_id' => $vehicle->id,
            'status' => 'pending',
            'pickup_at' => now()->subDays(10),
            'return_at' => now()->subDays(4),
        ]);
        Booking::factory()->create([
            'vehicle_id' => $vehicle->id,
            'status' => 'cancelled',
            'pickup_at' => now()->subDays(10),
            'return_at' => now()->subDays(4),
        ]);

        $rows = (new VehicleUtilizationTable)->getRows();
        $row = collect($rows)->firstWhere('vehicle.id', $vehicle->id);

        $this->assertSame(0.0, $row['bookedDays']);
    }

    public function test_a_vehicle_with_no_bookings_shows_zero_utilization(): void
    {
        $vehicle = Vehicle::factory()->create();

        $rows = (new VehicleUtilizationTable)->getRows();
        $row = collect($rows)->firstWhere('vehicle.id', $vehicle->id);

        $this->assertSame(0.0, $row['utilizationPercent']);
    }

    /**
     * Rule 8: never one query per item. Exactly 2 queries regardless of
     * how many vehicles exist — one for all vehicles, one for all
     * overlapping bookings across all of them — with the per-vehicle
     * aggregation done in PHP, not N+1 queries.
     */
    public function test_computing_utilization_for_many_vehicles_takes_a_constant_number_of_queries(): void
    {
        Vehicle::factory()->count(10)->create()->each(function (Vehicle $vehicle) {
            Booking::factory()->create([
                'vehicle_id' => $vehicle->id,
                'status' => 'confirmed',
                'pickup_at' => now()->subDays(5),
                'return_at' => now()->subDays(1),
            ]);
        });

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        (new VehicleUtilizationTable)->getRows();

        $this->assertSame(2, $queryCount, 'Expected exactly 2 queries (all vehicles, all overlapping bookings) regardless of vehicle count.');
    }
}
