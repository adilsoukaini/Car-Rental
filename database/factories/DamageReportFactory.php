<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\DamageReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DamageReport>
 */
class DamageReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'stage' => fake()->randomElement(['pickup', 'return']),
            'description' => fake()->sentence(10),
            'photo_paths' => [],
            'reported_by' => User::factory(),
        ];
    }
}
