<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

/**
 * Seeds the demo fleet — realistic Moroccan rental cars with real makes/
 * models, MAD daily rates, and a mostly-available status spread so the
 * public fleet listing and homepage featured grid have real data to show.
 *
 * Idempotent: keyed on the unique license_plate, so re-running `db:seed`
 * updates a vehicle's row instead of duplicating it.
 */
class VehicleSeeder extends Seeder
{
    /**
     * [make, model, year, category, daily_rate, seats, transmission, fuel,
     * mileage, status]
     *
     * Daily rates are hand-picked MAD prices typical of the Moroccan market
     * (economy ~200-250, compact ~320-360, SUV ~380-460, luxury ~620-800,
     * van ~520-580). Status is deliberately mostly 'available' so the public
     * site is populated; a couple are 'rented'/'maintenance' so the admin
     * screens and lifecycle actions have non-happy-path rows to show.
     *
     * @var list<array{
     *     make: string, model: string, year: int, category: string,
     *     daily_rate: int, seat_count: int, transmission_type: string,
     *     fuel_type: string, mileage: int, status: string
     * }>
     */
    private const VEHICLES = [
        // Economy
        ['Renault', 'Clio', 2022, 'economy', 250, 5, 'manual', 'petrol', 32000, 'available'],
        ['Dacia', 'Sandero', 2023, 'economy', 220, 5, 'manual', 'petrol', 18000, 'available'],
        ['Peugeot', '208', 2021, 'economy', 240, 5, 'manual', 'petrol', 45000, 'available'],
        ['Kia', 'Picanto', 2022, 'economy', 200, 4, 'manual', 'petrol', 28000, 'available'],
        ['Hyundai', 'i10', 2023, 'economy', 210, 5, 'automatic', 'petrol', 15000, 'available'],

        // Compact / midsize
        ['Toyota', 'Corolla', 2022, 'economy', 350, 5, 'automatic', 'hybrid', 30000, 'available'],
        ['Peugeot', '308', 2021, 'economy', 320, 5, 'manual', 'diesel', 52000, 'available'],
        ['Hyundai', 'Elantra', 2022, 'economy', 330, 5, 'automatic', 'petrol', 24000, 'available'],
        ['Skoda', 'Octavia', 2023, 'economy', 360, 5, 'automatic', 'diesel', 12000, 'available'],
        ['Volkswagen', 'Golf', 2022, 'economy', 340, 5, 'automatic', 'petrol', 36000, 'available'],

        // SUV
        ['Hyundai', 'Tucson', 2023, 'suv', 450, 5, 'automatic', 'diesel', 16000, 'available'],
        ['Kia', 'Sportage', 2022, 'suv', 460, 5, 'automatic', 'diesel', 21000, 'available'],
        ['Dacia', 'Duster', 2022, 'suv', 380, 5, 'manual', 'diesel', 39000, 'available'],
        ['Renault', 'Arkana', 2023, 'suv', 400, 5, 'automatic', 'hybrid', 14000, 'available'],
        ['Nissan', 'Qashqai', 2022, 'suv', 420, 5, 'automatic', 'petrol', 26000, 'rented'],

        // Luxury
        ['BMW', 'Série 3', 2022, 'luxury', 650, 5, 'automatic', 'petrol', 22000, 'available'],
        ['Mercedes', 'Classe C', 2023, 'luxury', 700, 5, 'automatic', 'diesel', 11000, 'available'],
        ['Audi', 'A4', 2022, 'luxury', 620, 5, 'automatic', 'petrol', 27000, 'available'],
        ['Range Rover', 'Evoque', 2023, 'luxury', 800, 5, 'automatic', 'hybrid', 9000, 'rented'],

        // Van
        ['Renault', 'Trafic', 2022, 'van', 550, 8, 'manual', 'diesel', 47000, 'available'],
        ['Peugeot', 'Expert', 2021, 'van', 520, 9, 'manual', 'diesel', 61000, 'maintenance'],
        ['Volkswagen', 'Transporter', 2022, 'van', 580, 9, 'manual', 'diesel', 33000, 'available'],

        // Popular Moroccan utility/work vehicles — Dacia/Fiat/Renault commercial vans.
        // Appended at the END so existing entries keep their index-derived
        // license plates (the seeder is idempotent on license_plate — inserting
        // mid-array would shift every subsequent plate and create duplicates on
        // re-seed against an already-seeded dev DB).
        ['Dacia', 'Dokker', 2021, 'van', 480, 5, 'manual', 'diesel', 55000, 'available'],
        ['Renault', 'Express', 2022, 'van', 460, 5, 'manual', 'diesel', 41000, 'available'],
        ['Fiat', 'Doblo', 2021, 'van', 470, 5, 'manual', 'diesel', 58000, 'available'],
        // Economy sedan — very common as a Moroccan rental/taxi
        ['Dacia', 'Logan', 2022, 'economy', 230, 5, 'manual', 'diesel', 34000, 'available'],
    ];

    public function run(): void
    {
        $locations = Location::query()->get()->keyBy('name');

        foreach (self::VEHICLES as $index => [$make, $model, $year, $category, $dailyRate, $seats, $transmission, $fuel, $mileage, $status]) {
            // Cycle through locations so the fleet is spread across all branches.
            $location = $locations->values()->get($index % $locations->count());

            Vehicle::query()->updateOrCreate(
                [
                    'license_plate' => $this->plateFor($index, $make),
                ],
                [
                    'make' => $make,
                    'model' => $model,
                    'year' => $year,
                    'category' => $category,
                    'daily_rate' => $dailyRate,
                    'seat_count' => $seats,
                    'transmission_type' => $transmission,
                    'fuel_type' => $fuel,
                    'mileage' => $mileage,
                    'status' => $status,
                    'location_id' => $location?->id,
                ],
            );
        }
    }

    /**
     * Generate a realistic, unique Moroccan license plate (old-format
     * `12345-A-6` style) deterministically from the vehicle's index + make,
     * so re-runs produce the same plate for the same row.
     */
    private function plateFor(int $index, string $make): string
    {
        $number = 10000 + ($index * 137);
        $letter = substr(strtoupper($make), 0, 1);
        $region = 1 + ($index % 8);

        return sprintf('%d-%s-%d', $number, $letter, $region);
    }
}
