<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\BookingCalendarWidget;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class BookingCalendarWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pinned so "now" inside the widget (current-day highlight, default
        // month) and this test's booking dates come from the exact same
        // instant.
        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function widgetFor(int $month, int $year): BookingCalendarWidget
    {
        $widget = new BookingCalendarWidget;
        $widget->month = $month;
        $widget->year = $year;

        return $widget;
    }

    /** @param  array<int, array<int, array<string, mixed>|null>>  $rows */
    private function cellFor(array $rows, int $day): array
    {
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($cell !== null && $cell['day'] === $day) {
                    return $cell;
                }
            }
        }

        $this->fail("Day {$day} not found in the calendar rows.");
    }

    public function test_pickup_active_and_return_days_are_classified_correctly(): void
    {
        $vehicle = Vehicle::factory()->create();

        // A 3-day rental inside the displayed month (Sep 2026): pickup the
        // 10th, return the 12th — the 11th is the active period between them.
        Booking::factory()->create([
            'vehicle_id' => $vehicle->id,
            'status' => 'confirmed',
            'pickup_at' => '2026-09-10 10:00:00',
            'return_at' => '2026-09-12 10:00:00',
        ]);

        $data = $this->widgetFor(9, 2026)->getCalendarData();

        $this->assertSame('pickup', $this->cellFor($data['rows'], 10)['markers'][0]['color']);
        $this->assertSame('active', $this->cellFor($data['rows'], 11)['markers'][0]['color']);
        $this->assertSame('return', $this->cellFor($data['rows'], 12)['markers'][0]['color']);
    }

    public function test_a_booking_spanning_from_the_previous_month_is_clamped_to_the_displayed_month(): void
    {
        $vehicle = Vehicle::factory()->create();

        // Rental from Aug 30 -> Sep 3. In September's view, Sep 1-2 are the
        // active period and Sep 3 is the return; the pickup (Aug 30) is
        // outside the displayed month and must not leak in.
        Booking::factory()->create([
            'vehicle_id' => $vehicle->id,
            'status' => 'confirmed',
            'pickup_at' => '2026-08-30 10:00:00',
            'return_at' => '2026-09-03 10:00:00',
        ]);

        $data = $this->widgetFor(9, 2026)->getCalendarData();

        $this->assertSame('active', $this->cellFor($data['rows'], 1)['markers'][0]['color']);
        $this->assertSame('active', $this->cellFor($data['rows'], 2)['markers'][0]['color']);
        $this->assertSame('return', $this->cellFor($data['rows'], 3)['markers'][0]['color']);
        $this->assertEmpty($this->cellFor($data['rows'], 4)['markers']);
    }

    public function test_pending_cancelled_and_expired_bookings_do_not_appear(): void
    {
        $vehicle = Vehicle::factory()->create();

        foreach (['pending', 'cancelled', 'expired'] as $status) {
            Booking::factory()->create([
                'vehicle_id' => $vehicle->id,
                'status' => $status,
                'pickup_at' => '2026-09-10 10:00:00',
                'return_at' => '2026-09-12 10:00:00',
            ]);
        }

        $data = $this->widgetFor(9, 2026)->getCalendarData();

        $this->assertEmpty($this->cellFor($data['rows'], 10)['markers']);
        $this->assertEmpty($this->cellFor($data['rows'], 12)['markers']);
    }

    public function test_a_single_day_rental_is_both_pickup_and_return_on_the_same_day(): void
    {
        $vehicle = Vehicle::factory()->create();

        Booking::factory()->create([
            'vehicle_id' => $vehicle->id,
            'status' => 'confirmed',
            'pickup_at' => '2026-09-10 08:00:00',
            'return_at' => '2026-09-10 18:00:00',
        ]);

        $data = $this->widgetFor(9, 2026)->getCalendarData();
        $markers = $this->cellFor($data['rows'], 10)['markers'];

        $this->assertCount(1, $markers);
        // Pickup wins the classification on a same-day rental — there is no
        // "active" day between pickup and return.
        $this->assertSame('pickup', $markers[0]['color']);
    }

    public function test_month_navigation_moves_forward_back_and_across_year_boundaries(): void
    {
        $widget = $this->widgetFor(9, 2026);

        $widget->goToNextMonth();
        $this->assertSame([10, 2026], [$widget->month, $widget->year]);

        $widget->goToNextMonth();
        $widget->goToNextMonth();
        $widget->goToNextMonth();
        $this->assertSame([1, 2027], [$widget->month, $widget->year]);

        $widget->goToPreviousMonth();
        $this->assertSame([12, 2026], [$widget->month, $widget->year]);

        $widget->goToCurrentMonth();
        $this->assertSame([9, 2026], [$widget->month, $widget->year]);
    }

    public function test_grid_is_built_in_rows_of_seven_with_leading_blanks_for_the_first_weekday(): void
    {
        $data = $this->widgetFor(9, 2026)->getCalendarData();

        foreach ($data['rows'] as $row) {
            $this->assertCount(7, $row);
        }

        // September 2026 starts on a Tuesday (Carbon dayOfWeek = 2, Sunday 0)
        // -> exactly 2 leading blanks, then the 1st.
        $this->assertNull($data['rows'][0][0]);
        $this->assertNull($data['rows'][0][1]);
        $this->assertNotNull($data['rows'][0][2]);
        $this->assertSame(1, $data['rows'][0][2]['day']);
    }

    public function test_current_day_is_marked_as_today(): void
    {
        $data = $this->widgetFor(9, 2026)->getCalendarData();

        $this->assertSame('2026-09-15', $data['today']);
        $this->assertTrue($this->cellFor($data['rows'], 15)['isToday']);
        $this->assertFalse($this->cellFor($data['rows'], 16)['isToday']);
    }

    /**
     * Rule 8: never one query per item. Loading the calendar is exactly 2
     * queries regardless of how many bookings overlap the month — one for
     * all bookings, one for all eager-loaded vehicles — with the per-day
     * pickup/return/active classification done in PHP.
     */
    public function test_loading_the_calendar_takes_a_constant_number_of_queries(): void
    {
        $vehicle = Vehicle::factory()->create();
        Booking::factory()->count(10)->create([
            'vehicle_id' => $vehicle->id,
            'status' => 'confirmed',
            'pickup_at' => '2026-09-05 10:00:00',
            'return_at' => '2026-09-08 10:00:00',
        ]);

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $this->widgetFor(9, 2026)->getCalendarData();

        $this->assertSame(2, $queryCount, 'Expected exactly 2 queries (all bookings, all vehicles) regardless of booking count.');
    }

    public function test_widget_renders_the_month_name_and_booking_details_in_livewire(): void
    {
        $vehicle = Vehicle::factory()->create(['make' => 'Ford', 'model' => 'Focus']);
        Booking::factory()->create([
            'vehicle_id' => $vehicle->id,
            'status' => 'confirmed',
            'pickup_at' => '2026-09-10 10:00:00',
            'return_at' => '2026-09-12 10:00:00',
        ]);

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        Livewire::test(BookingCalendarWidget::class)
            ->assertSee('September 2026')
            ->assertSee('Ford Focus');
    }
}
