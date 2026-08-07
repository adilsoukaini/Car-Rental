<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\BulkVehicleImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the admin BulkVehicleImport page: the Admin-only access gate, the
 * CSV template download, the upload->preview flow, and the import action
 * reporting success/failure counts. The underlying CSV parsing/validation
 * logic itself is covered in depth by VehiclesImportTest.
 */
class BulkVehicleImportTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_CSV = "make,model,year,category,license_plate,daily_rate,seat_count,transmission_type,fuel_type,mileage,status\n".
        "Toyota,Corolla,2022,economy,ABC-123,350,5,manual,petrol,45000,available\n".
        "Honda,Civic,2023,suv,DEF-456,420,5,automatic,hybrid,12000,available\n";

    private const MIXED_CSV = "make,model,year,category,license_plate,daily_rate,seat_count,transmission_type,fuel_type,mileage,status\n".
        "Toyota,Corolla,2022,economy,ABC-123,350,5,manual,petrol,45000,available\n".
        "Bad,Row,2022,bad-category,DEF-456,420,5,manual,petrol,12000,available\n";

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_access_the_page(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/bulk-vehicle-import')->assertOk();
    }

    public function test_staff_cannot_access_the_page(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $this->get('/admin/bulk-vehicle-import')->assertForbidden();
    }

    public function test_customer_is_redirected_away_from_the_panel(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer);

        $this->get('/admin/bulk-vehicle-import')->assertRedirect(route('home'));
    }

    public function test_download_template_streams_a_csv_with_headers_and_sample_row(): void
    {
        $this->actingAs($this->admin());

        $response = $this->get('/admin/vehicle-import-template');

        $response->assertOk();
        $response->assertDownload('vehicle-import-template.csv');
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('make,model,year,category,license_plate,daily_rate', $content);
        $this->assertStringContainsString('Toyota,Corolla', $content);
        $this->assertStringContainsString('transmission_type,fuel_type,mileage,status', $content);
    }

    public function test_template_download_is_admin_only(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $this->get('/admin/vehicle-import-template')->assertForbidden();
    }

    public function test_uploading_a_csv_populates_the_preview_with_the_first_rows(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(BulkVehicleImport::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('vehicles.csv', self::VALID_CSV))
            ->assertSet('previewError', null)
            ->assertSet('preview.0.make', 'Toyota')
            ->assertSet('preview.0.license_plate', 'ABC-123')
            ->assertSet('preview.1.make', 'Honda');
    }

    public function test_import_creates_vehicles_and_reports_success_and_failure_counts(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(BulkVehicleImport::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('vehicles.csv', self::MIXED_CSV))
            ->call('import')
            ->assertSet('importedCount', 1)
            ->assertSet('failedCount', 1)
            ->assertSet('failureRows.0.row', 3);

        $this->assertDatabaseHas('vehicles', ['license_plate' => 'ABC-123']);
        $this->assertDatabaseMissing('vehicles', ['license_plate' => 'DEF-456']);
    }

    public function test_import_with_all_valid_rows_reports_zero_failures(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(BulkVehicleImport::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('vehicles.csv', self::VALID_CSV))
            ->call('import')
            ->assertSet('importedCount', 2)
            ->assertSet('failedCount', 0);

        $this->assertDatabaseHas('vehicles', ['license_plate' => 'ABC-123']);
        $this->assertDatabaseHas('vehicles', ['license_plate' => 'DEF-456']);
    }
}
