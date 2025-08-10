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
        
        // Create a QR code for testing if it doesn't exist
        $qrCodeExists = DB::table('qr_codes')->where('code', 'TEST123')->exists();
        
        if (!$qrCodeExists) {
            DB::table('qr_codes')->insert([
                'restaurant_id' => $restaurant->id,
                'table_number' => 'A1',
                'code' => 'TEST123',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete the test menu items
        Menu::where('name', 'Milk Tea')->delete();
        Menu::where('name', 'Pizza')->delete();
        
        // Delete the test QR code
        DB::table('qr_codes')->where('code', 'TEST123')->delete();
    }
};
