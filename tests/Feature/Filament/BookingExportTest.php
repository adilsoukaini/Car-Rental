<?php

namespace Tests\Feature\Filament;

use App\Models\Booking;
use App\Models\Location;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_download_the_csv_with_correct_headers(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        Booking::factory()->create(['status' => 'confirmed']);

        $response = $this->get('/admin/bookings/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $rows = array_map('str_getcsv', explode("\n", trim($response->streamedContent())));

        $this->assertSame([
            'Booking #',
            'Vehicle',
            'Customer',
            'Pickup Date',
            'Return Date',
            'Pickup Location',
            'Return Location',
            'Status',
            'Total (MAD)',
            'Deposit (MAD)',
            'Created At',
        ], $rows[0]);
    }

    public function test_export_respects_the_status_filter(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $confirmed = Booking::factory()->create(['status' => 'confirmed']);
        $cancelled = Booking::factory()->create(['status' => 'cancelled']);

        $response = $this->get('/admin/bookings/export?status=confirmed');

        $content = $response->streamedContent();

        $this->assertStringContainsString($confirmed->booking_number, $content);
        $this->assertStringNotContainsString($cancelled->booking_number, $content);
    }

    public function test_export_respects_the_search_filter(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $vehicle = Vehicle::factory()->create(['license_plate' => 'TEST-123']);
        $otherVehicle = Vehicle::factory()->create(['license_plate' => 'OTHER-456']);

        $matched = Booking::factory()->create(['vehicle_id' => $vehicle->id, 'status' => 'confirmed']);
        $unmatched = Booking::factory()->create(['vehicle_id' => $otherVehicle->id, 'status' => 'confirmed']);

        $response = $this->get('/admin/bookings/export?search=TEST-123');

        $content = $response->streamedContent();

        $this->assertStringContainsString($matched->booking_number, $content);
        $this->assertStringNotContainsString($unmatched->booking_number, $content);
    }

    public function test_export_includes_the_expected_row_data(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $vehicle = Vehicle::factory()->create(['license_plate' => 'PLATE-99']);
        $pickup = Location::factory()->create(['name' => 'Casablanca Airport']);
        $return = Location::factory()->create(['name' => 'Marrakech Station']);

        $booking = Booking::factory()->create([
            'vehicle_id' => $vehicle->id,
            'pickup_location_id' => $pickup->id,
            'return_location_id' => $return->id,
            'guest_name' => 'John Doe',
            'status' => 'confirmed',
            'total_price' => 750.00,
            'security_deposit_amount' => 150.00,
        ]);

        $response = $this->get('/admin/bookings/export');

        $content = $response->streamedContent();

        $this->assertStringContainsString($booking->booking_number, $content);
        $this->assertStringContainsString('PLATE-99', $content);
        $this->assertStringContainsString('John Doe', $content);
        $this->assertStringContainsString('Casablanca Airport', $content);
        $this->assertStringContainsString('Marrakech Station', $content);
        $this->assertStringContainsString('confirmed', $content);
        $this->assertStringContainsString('750.00', $content);
        $this->assertStringContainsString('150.00', $content);
    }

    public function test_customer_cannot_export(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer);

        $response = $this->get('/admin/bookings/export');

        $response->assertRedirect(route('home'));
    }

    public function test_guest_cannot_export(): void
    {
        $response = $this->get('/admin/bookings/export');

        $response->assertRedirect();
    }
}
