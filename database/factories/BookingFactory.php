<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pickupAt = fake()->dateTimeBetween('+1 day', '+10 days');
        $returnAt = (clone $pickupAt)->modify('+'.fake()->numberBetween(1, 14).' days');

        return [
            'vehicle_id' => Vehicle::factory(),
            'user_id' => null,
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'guest_phone' => fake()->phoneNumber(),
            'pickup_location_id' => Location::factory(),
            'return_location_id' => Location::factory(),
            'pickup_at' => $pickupAt,
            'return_at' => $returnAt,
            'status' => 'confirmed',
            'total_price' => fake()->randomFloat(2, 200, 5000),
        ];
    }
}
