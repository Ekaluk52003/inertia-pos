<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $thaiRestaurant = Restaurant::where('name', 'Thai Delight')->first();
        $sushiRestaurant = Restaurant::where('name', 'Sushi Express')->first();

        // Thai restaurant menu items
        $thaiMenuItems = [
            [
                'name' => 'Pad Thai',
                'price' => 120.00,
                'category' => 'Main',
                'is_available' => true,
                'options' => json_encode([
                    ['name' => 'Regular', 'price' => 0],
                    ['name' => 'Extra Noodles', 'price' => 30],
                    ['name' => 'With Fried Egg', 'price' => 15],
                    ['name' => 'Extra Spicy', 'price' => 0],
                ]),
            ],
            [
                'name' => 'Green Curry',
                'price' => 150.00,
                'category' => 'Main',
                'is_available' => true,
                'options' => json_encode([
                    ['name' => 'Regular', 'price' => 0],
                    ['name' => 'Extra Chicken', 'price' => 40],
                    ['name' => 'Less Spicy', 'price' => 0],
                ]),
            ],
            [
                'name' => 'Tom Yum Soup',
                'price' => 100.00,
                'category' => 'Soup',
                'is_available' => true,
                'options' => json_encode([
                    ['name' => 'Regular', 'price' => 0],
                    ['name' => 'Extra Seafood', 'price' => 50],
                    ['name' => 'Extra Spicy', 'price' => 0],
                ]),
            ],
            [
                'name' => 'Mango Sticky Rice',
                'price' => 80.00,
                'category' => 'Dessert',
                'is_available' => true,
                'options' => json_encode([
                    ['name' => 'Regular', 'price' => 0],
                    ['name' => 'Extra Mango', 'price' => 20],
                    ['name' => 'Coconut Cream', 'price' => 15],
                ]),
            ],
            [
                'name' => 'Thai Iced Tea',
                'price' => 50.00,
                'category' => 'Beverage',
                'is_available' => true,
            ],
            [
                'name' => 'Papaya Salad',
                'price' => 90.00,
                'category' => 'Appetizer',
                'is_available' => false,
            ],
        ];

        foreach ($thaiMenuItems as $item) {
            Menu::create([
                'restaurant_id' => $thaiRestaurant->id,
                'name' => $item['name'],
                'price' => $item['price'],
                'category' => $item['category'],
                'is_available' => $item['is_available'],
            ]);
        }

        // Sushi restaurant menu items
        $sushiMenuItems = [
            [
                'name' => 'Salmon Nigiri (2 pcs)',
                'price' => 80.00,
                'category' => 'Nigiri',
                'is_available' => true,
                'options' => json_encode([
                    ['name' => 'Regular', 'price' => 0],
                    ['name' => 'Extra Wasabi', 'price' => 5],
                    ['name' => 'Seared', 'price' => 20],
                ]),
            ],
            [
                'name' => 'Tuna Nigiri (2 pcs)',
                'price' => 90.00,
                'category' => 'Nigiri',
                'is_available' => true,
            ],
            [
                'name' => 'California Roll',
                'price' => 150.00,
                'category' => 'Maki',
                'is_available' => true,
                'options' => json_encode([
                    ['name' => 'Regular', 'price' => 0],
                    ['name' => 'Extra Avocado', 'price' => 25],
                    ['name' => 'Spicy Mayo', 'price' => 10],
                    ['name' => 'Tempura Style', 'price' => 30],
                ]),
            ],
            [
                'name' => 'Dragon Roll',
                'price' => 220.00,
                'category' => 'Maki',
                'is_available' => true,
            ],
            [
                'name' => 'Miso Soup',
                'price' => 60.00,
                'category' => 'Soup',
                'is_available' => true,
            ],
            [
                'name' => 'Green Tea Ice Cream',
                'price' => 70.00,
                'category' => 'Dessert',
                'is_available' => false,
            ],
            [
                'name' => 'Japanese Beer',
                'price' => 120.00,
                'category' => 'Beverage',
                'is_available' => true,
            ],
        ];

        foreach ($sushiMenuItems as $item) {
            Menu::create([
                'restaurant_id' => $sushiRestaurant->id,
                'name' => $item['name'],
                'price' => $item['price'],
                'category' => $item['category'],
                'is_available' => $item['is_available'],
            ]);
        }
    }
}
