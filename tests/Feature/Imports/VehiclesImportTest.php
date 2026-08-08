<?php

declare(strict_types=1);

namespace Tests\Feature\Imports;

use App\Imports\VehiclesImport;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelReader;
use Tests\TestCase;

/**
 * Covers the VehiclesImport CSV importer used by the admin bulk-import page:
 * column mapping, status/mileage defaults, per-row validation (invalid rows
 * skipped, not fatal), and duplicate license-plate handling (both within the
 * same file and against the existing database).
 */
class VehiclesImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = 'make,model,year,category,license_plate,daily_rate,seat_count,transmission_type,fuel_type,mileage,status';

    /**
     * Run an import against a CSV string and return the import instance so
     * tests can inspect created/failure counts and the raw failures.
     */
    private function import(string $csv): VehiclesImport
    {
        Storage::fake('local');
        Storage::disk('local')->put('vehicles.csv', $csv);

        $import = new VehiclesImport;
        $import->import(Storage::disk('local')->path('vehicles.csv'), null, ExcelReader::CSV);

        return $import;
    }

    public function test_imports_valid_rows_with_all_columns_mapped(): void
    {
        $import = $this->import(
            self::HEADERS."\n".
            'Toyota,Corolla,2022,economy,ABC-123,350,5,manual,petrol,45000,available'."\n".
            'Honda,Civic,2023,suv,DEF-456,420.50,5,automatic,hybrid,12000,available'."\n"
        );

        $this->assertSame(2, $import->getCreatedCount());
        $this->assertSame(0, $import->failures()->count());

        $this->assertDatabaseHas('vehicles', [
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2022,
            'category' => 'economy',
            'license_plate' => 'ABC-123',
            'daily_rate' => 350.00,
            'seat_count' => 5,
            'transmission_type' => 'manual',
            'fuel_type' => 'petrol',
            'mileage' => 45000,
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('vehicles', [
            'license_plate' => 'DEF-456',
            'daily_rate' => 420.50,
            'status' => 'available',
        ]);
    }

    public function test_defaults_status_to_available_when_column_blank(): void
    {
        // Blank status (trailing comma) on the first row.
        $import = $this->import(
            self::HEADERS."\n".
            "Toyota,Corolla,2022,economy,ABC-123,350,5,manual,petrol,45000,\n"
        );

        $this->assertSame(1, $import->getCreatedCount());
        $this->assertSame(0, $import->failures()->count());
        $this->assertDatabaseHas('vehicles', ['license_plate' => 'ABC-123', 'status' => 'available']);
    }

    public function test_defaults_status_to_available_when_column_missing(): void
    {
        // The status column is entirely absent from the header row.
        $csv = "make,model,year,category,license_plate,daily_rate,seat_count,transmission_type,fuel_type,mileage\n".
            "Honda,Civic,2022,economy,DEF-456,400,5,automatic,petrol,12000\n";

        $import = $this->import($csv);

        $this->assertSame(1, $import->getCreatedCount());
        $this->assertSame(0, $import->failures()->count());
        $this->assertDatabaseHas('vehicles', ['license_plate' => 'DEF-456', 'status' => 'available']);
    }

    public function test_defaults_mileage_to_zero_when_blank(): void
    {
        $import = $this->import(
            self::HEADERS."\n".
            "Toyota,Corolla,2022,economy,ABC-123,350,5,manual,petrol,,\n"
        );

        $this->assertSame(1, $import->getCreatedCount());
        $this->assertDatabaseHas('vehicles', ['license_plate' => 'ABC-123', 'mileage' => 0]);
    }

    public function test_skips_row_with_invalid_category_and_reports_failure(): void
    {
        $import = $this->import(
            self::HEADERS."\n".
            "Toyota,Corolla,2022,bad-category,ABC-123,350,5,manual,petrol,45000,available\n"
        );

        $this->assertSame(0, $import->getCreatedCount());
        $this->assertSame(1, $import->failures()->count());
        $this->assertDatabaseMissing('vehicles', ['license_plate' => 'ABC-123']);

        $failure = $import->failures()->first();
        $this->assertSame(2, $failure->row());
        $this->assertStringContainsString('category', $failure->errors()[0]);
    }

    public function test_skips_row_with_non_positive_daily_rate(): void
    {
        $import = $this->import(
            self::HEADERS."\n".
            "Toyota,Corolla,2022,economy,ABC-123,0,5,manual,petrol,45000,available\n"
        );

        $this->assertSame(0, $import->getCreatedCount());
        $this->assertSame(1, $import->failures()->count());
    }

    public function test_skips_row_missing_a_required_field(): void
    {
        $import = $this->import(
            self::HEADERS."\n".
            ",Corolla,2022,economy,ABC-123,350,5,manual,petrol,45000,available\n"
        );

        $this->assertSame(0, $import->getCreatedCount());
        $this->assertSame(1, $import->failures()->count());
    }

    public function test_skips_duplicate_license_plate_within_the_same_file(): void
    {
        $import = $this->import(
            self::HEADERS."\n".
            'Toyota,Corolla,2022,economy,ABC-123,350,5,manual,petrol,45000,available'."\n".
            'Honda,Civic,2022,economy,ABC-123,400,5,automatic,petrol,12000,available'."\n"
        );

        $this->assertSame(1, $import->getCreatedCount());
        $this->assertSame(1, $import->failures()->count());
        $this->assertSame(1, Vehicle::where('license_plate', 'ABC-123')->count());

        $failure = $import->failures()->first();
        $this->assertSame(3, $failure->row());
        $this->assertStringContainsString('more than once', $failure->errors()[0]);
    }

    public function test_skips_license_plate_that_already_exists_in_database(): void
    {
        Vehicle::factory()->create(['license_plate' => 'ABC-123']);

        $import = $this->import(
            self::HEADERS."\n".
            'Toyota,Corolla,2022,economy,ABC-123,350,5,manual,petrol,45000,available'."\n"
        );

        $this->assertSame(0, $import->getCreatedCount());
        $this->assertSame(1, $import->failures()->count());
        $this->assertSame(1, Vehicle::where('license_plate', 'ABC-123')->count());

        $failure = $import->failures()->first();
        $this->assertStringContainsString('already exists', $failure->errors()[0]);
    }

    public function test_accepts_case_insensitive_enum_values(): void
    {
        $import = $this->import(
            self::HEADERS."\n".
            "Toyota,Corolla,2022,SUV,ABC-123,350,5,Automatic,Diesel,45000,Available\n"
        );

        $this->assertSame(1, $import->getCreatedCount());
        $this->assertSame(0, $import->failures()->count());

        $this->assertDatabaseHas('vehicles', [
            'license_plate' => 'ABC-123',
            'category' => 'suv',
            'transmission_type' => 'automatic',
            'fuel_type' => 'diesel',
            'status' => 'available',
        ]);
    }

    public function test_skips_invalid_rows_but_keeps_valid_ones(): void
    {
        $import = $this->import(
            self::HEADERS."\n".
            'Toyota,Corolla,2022,economy,ABC-123,350,5,manual,petrol,45000,available'."\n".
            "Bad,Row,2022,bad-category,DEF-456,420,5,manual,petrol,12000,available\n".
            'Honda,Civic,2022,economy,GHI-789,400,5,automatic,hybrid,9000,available'."\n"
        );

        $this->assertSame(2, $import->getCreatedCount());
        $this->assertSame(1, $import->failures()->count());

        $this->assertDatabaseHas('vehicles', ['license_plate' => 'ABC-123']);
        $this->assertDatabaseMissing('vehicles', ['license_plate' => 'DEF-456']);
        $this->assertDatabaseHas('vehicles', ['license_plate' => 'GHI-789']);
    }
}
