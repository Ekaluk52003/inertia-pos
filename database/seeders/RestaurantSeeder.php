<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Seeder;

class RestaurantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner = User::where('email', 'owner@example.com')->first();

        // Create restaurants for the owner
        $restaurant1 = Restaurant::create([
            'name' => 'Thai Delight',
            'description' => 'Authentic Thai cuisine with a modern twist',
            'owner_id' => $owner->id,
            'pay_before' => false,
            'prompt_pay_id' => '0891234567',
            'billing' => json_encode([
                'address' => '123 Bangkok Street',
                'tax_id' => 'TH123456789',
                'contact_number' => '0891234567',
            ]),
        ]);

        $restaurant2 = Restaurant::create([
            'name' => 'Sushi Express',
            'description' => 'Fresh Japanese sushi and sashimi',
            'owner_id' => $owner->id,
            'pay_before' => true,
            'prompt_pay_id' => '0897654321',
            'billing' => json_encode([
                'address' => '456 Tokyo Avenue',
                'tax_id' => 'TH987654321',
                'contact_number' => '0897654321',
            ]),
        ]);

        // Update staff users with restaurant_id
        $kitchenStaff = User::where('email', 'kitchen@example.com')->first();
        $kitchenStaff->restaurant_id = $restaurant1->id;
        $kitchenStaff->save();

        $managerStaff = User::where('email', 'manager@example.com')->first();
        $managerStaff->restaurant_id = $restaurant2->id;
        $managerStaff->save();
    }
}
