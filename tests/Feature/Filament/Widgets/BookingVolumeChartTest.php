<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\BookingVolumeChart;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Deliberately different status filter from BookingStatsOverview: this
 * widget counts `cancelled` bookings (they happened, even if later
 * reversed) but still excludes `pending`/`expired` (never completed
 * checkout at all) — see the widget's own docblock for the full
 * reasoning. Tested directly against getData() rather than through
 * Livewire rendering, since the data shape (not the chart rendering) is
 * what actually encodes the status-filter decision.
 */
class BookingVolumeChartTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{datasets: array<int, array{label: string, data: array<int, int>}>, labels: array<int, string>} */
    private function getChartData(): array
    {
        $widget = new BookingVolumeChart;

        $method = new ReflectionMethod($widget, 'getData');
        $method->setAccessible(true);

        return $method->invoke($widget);
    }

    public function test_cancelled_bookings_are_counted(): void
    {
        Booking::factory()->create(['status' => 'cancelled', 'created_at' => now()]);

        $data = $this->getChartData();

        $this->assertSame(1, array_sum($data['datasets'][0]['data']));
    }

    public function test_pending_bookings_are_not_counted(): void
    {
        Booking::factory()->create(['status' => 'pending', 'created_at' => now()]);

        $data = $this->getChartData();

        $this->assertSame(0, array_sum($data['datasets'][0]['data']));
    }

    public function test_expired_bookings_are_not_counted(): void
    {
        Booking::factory()->create(['status' => 'expired', 'created_at' => now()]);

        $data = $this->getChartData();

        $this->assertSame(0, array_sum($data['datasets'][0]['data']));
    }

    public function test_confirmed_bookings_are_counted_on_the_correct_day(): void
    {
        Booking::factory()->create(['status' => 'confirmed', 'created_at' => now()->subDays(5)]);

        $data = $this->getChartData();

        $this->assertSame(1, array_sum($data['datasets'][0]['data']));
        $dayIndex = array_search(now()->subDays(5)->format('M j'), $data['labels'], true);
        $this->assertNotFalse($dayIndex);
        $this->assertSame(1, $data['datasets'][0]['data'][$dayIndex]);
    }

    public function test_bookings_older_than_the_window_are_excluded(): void
    {
        Booking::factory()->create(['status' => 'confirmed', 'created_at' => now()->subDays(45)]);

        $data = $this->getChartData();

        $this->assertSame(0, array_sum($data['datasets'][0]['data']));
    }
}
