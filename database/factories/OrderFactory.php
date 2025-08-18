<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'table_number' => $this->faker->numberBetween(1, 50),
            'code' => Str::random(10),
            'customer_notes' => $this->faker->optional()->sentence(),
            'total_amount' => $this->faker->randomFloat(2, 10, 500),
            'status' => 'active',
            'is_paid' => false,
        ];
    }
}
