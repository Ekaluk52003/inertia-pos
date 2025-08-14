<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Menu;
use App\Models\Restaurant;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all menu items
        $menuItems = Menu::all();
        
        // For each menu item, update with proper options structure if options are empty
        foreach ($menuItems as $menuItem) {
            // Skip if options are already set
            if (!empty($menuItem->options)) {
                continue;
            }
            
            // Set default options based on menu item name/category
            if (strtolower($menuItem->name) === 'milk tea' || strtolower($menuItem->category) === 'drinks') {
                $menuItem->options = [
                    [
                        'name' => 'sweetness',
                        'values' => [
                            ['name' => 'less sweet', 'price' => 0],
                            ['name' => 'normal sweet', 'price' => 0],
                            ['name' => 'super sweet', 'price' => 0]
                        ],
                        'multiple' => false,
                        'required' => true
                    ]
                ];
            } elseif (strtolower($menuItem->name) === 'pizza') {
                $menuItem->options = [
                    [
                        'name' => 'toppings',
                        'values' => [
                            ['name' => 'cheese', 'price' => 20],
                            ['name' => 'pepperoni', 'price' => 30],
                            ['name' => 'mushroom', 'price' => 15],
                            ['name' => 'bacon', 'price' => 25]
                        ],
                        'multiple' => true,
                        'required' => false
                    ],
                    [
                        'name' => 'crust',
                        'values' => [
                            ['name' => 'thin', 'price' => 0],
                            ['name' => 'thick', 'price' => 10]
                        ],
                        'multiple' => false,
                        'required' => true
                    ]
                ];
            } elseif (strtolower($menuItem->category) === 'main') {
                // Default options for main dishes
                $menuItem->options = [
                    [
                        'name' => 'spice level',
                        'values' => [
                            ['name' => 'mild', 'price' => 0],
                            ['name' => 'medium', 'price' => 0],
                            ['name' => 'spicy', 'price' => 0]
                        ],
                        'multiple' => false,
                        'required' => true
                    ]
                ];
            }
            
            // Save the updated menu item
            $menuItem->save();
        }
        
        // If no menu items with options exist, create sample ones
        if (Menu::whereNotNull('options')->count() === 0) {
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
            
            // Create a milk tea menu item with sweetness options
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
                            ['name' => 'normal sweet', 'price' => 0],
                            ['name' => 'super sweet', 'price' => 0]
                        ],
                        'multiple' => false,
                        'required' => true
                    ]
                ],
            ]);
            
            // Create a pizza menu item with multiple toppings options
            Menu::create([
                'restaurant_id' => $restaurant->id,
                'name' => 'Pizza',
                'price' => 200,
                'category' => 'Main',
                'description' => 'Delicious pizza with customizable toppings',
                'is_available' => true,
                'options' => [
                    [
                        'name' => 'toppings',
                        'values' => [
                            ['name' => 'cheese', 'price' => 20],
                            ['name' => 'pepperoni', 'price' => 30],
                            ['name' => 'mushroom', 'price' => 15],
                            ['name' => 'bacon', 'price' => 25]
                        ],
                        'multiple' => true,
                        'required' => false
                    ],
                    [
                        'name' => 'crust',
                        'values' => [
                            ['name' => 'thin', 'price' => 0],
                            ['name' => 'thick', 'price' => 10]
                        ],
                        'multiple' => false,
                        'required' => true
                    ]
                ],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this migration as it only restores data
    }
};
