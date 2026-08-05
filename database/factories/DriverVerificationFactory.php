<?php

namespace Database\Factories;

use App\Models\DriverVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverVerification>
 */
class DriverVerificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'license_number' => fake()->bothify('??######'),
            'license_country' => 'Morocco',
            'date_of_birth' => fake()->dateTimeBetween('-40 years', '-21 years')->format('Y-m-d'),
            'license_document_path' => 'driver-licenses/'.fake()->uuid().'.jpg',
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'reviewed_by' => User::factory()->create(['role' => 'staff'])->id,
            'reviewed_at' => now(),
        ]);
    }
}
