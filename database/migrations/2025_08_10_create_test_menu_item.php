<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Restaurant;
use App\Models\Menu;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get the first restaurant or create a test one if none exists
        $restaurant = Restaurant::first();
        
        if (!$restaurant) {
            // No restaurants found, create a test one
            $restaurant = Restaurant::create([
                'name' => 'Test Restaurant',
                'description' => 'Test restaurant for menu options',
                'owner_id' => 1, // Assuming user ID 1 exists
                'pay_before' => false,
            ]);
        }
        
        // Create a test menu item with the correct options structure
        Menu::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Milk Tea',
            'price' => 50,
            'category' => 'Drinks',
            'description' => 'Delicious milk tea with customizable sweetness',
            'is_available' => true,
            'options' => [
                [
                    'name' => 'sweetness',
                    'values' => [
                        ['name' => 'less sweet', 'price' => 0],
                        ['name' => 'super sweet', 'price' => 0]
                    ],
                    'multiple' => false,
                    'required' => true
                ]
            ]
        ]);
        
        // Create another test menu item with multiple selection options
        Menu::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Pizza',
            'price' => 200,
            'category' => 'Food',
            'description' => 'Delicious pizza with customizable toppings',
            'is_available' => true,
            'options' => [
                [
                    'name' => 'toppings',
                    'values' => [
                        ['name' => 'cheese', 'price' => 20],
                        ['name' => 'bacon', 'price' => 30],
                        ['name' => 'mushroom', 'price' => 15]
                    ],
                    'multiple' => true,
                    'required' => false
                ]
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete the test menu items
        Menu::where('name', 'Milk Tea')->delete();
        Menu::where('name', 'Pizza')->delete();
    }
};
