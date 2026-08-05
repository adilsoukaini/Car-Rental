<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'type' => 'deposit_authorization',
            'gateway' => 'stripe',
            'status' => 'pending',
            'amount' => fake()->randomFloat(2, 100, 2000),
            'provider_reference' => fake()->uuid(),
        ];
    }
}
