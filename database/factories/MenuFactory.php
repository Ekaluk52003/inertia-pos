<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Menu>
 */
class MenuFactory extends Factory
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
            'name' => $this->faker->words(3, true),
            'price' => $this->faker->randomFloat(2, 5, 100),
            'category' => $this->faker->randomElement(['Appetizer', 'Main', 'Dessert', 'Drink']),
            'is_available' => true,
            'image_path' => null,
            'description' => $this->faker->optional()->sentence(),
            'options' => [],
        ];
    }
}
