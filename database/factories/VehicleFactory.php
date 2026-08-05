<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'make' => fake()->randomElement(['Dacia', 'Renault', 'Peugeot', 'Toyota']),
            'model' => fake()->word(),
            'year' => fake()->numberBetween(2018, 2026),
            'category' => fake()->randomElement(['economy', 'suv', 'luxury', 'van']),
            'license_plate' => fake()->unique()->bothify('#####-?-#'),
            'daily_rate' => fake()->randomFloat(2, 200, 1500),
            'seat_count' => fake()->numberBetween(2, 9),
            'transmission_type' => fake()->randomElement(['manual', 'automatic']),
            'fuel_type' => fake()->randomElement(['petrol', 'diesel', 'electric', 'hybrid']),
            'mileage' => fake()->numberBetween(0, 150000),
            'status' => 'available',
            'location_id' => Location::factory(),
        ];
    }
}
