<?php

namespace Database\Seeders;

use App\Models\QrCode;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class QrCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $thaiRestaurant = Restaurant::where('name', 'Thai Delight')->first();
        $sushiRestaurant = Restaurant::where('name', 'Sushi Express')->first();

        // Create QR codes for Thai restaurant
        for ($i = 1; $i <= 10; $i++) {
            QrCode::create([
                'restaurant_id' => $thaiRestaurant->id,
                'table_number' => $i,
                'code' => Str::random(10),
                'is_active' => true,
            ]);
        }

        // Create QR codes for Sushi restaurant
        for ($i = 1; $i <= 8; $i++) {
            QrCode::create([
                'restaurant_id' => $sushiRestaurant->id,
                'table_number' => $i,
                'code' => Str::random(10),
                'is_active' => true,
            ]);
        }

        // Add a few inactive QR codes
        QrCode::create([
            'restaurant_id' => $thaiRestaurant->id,
            'table_number' => 11,
            'code' => Str::random(10),
            'is_active' => false,
        ]);

        QrCode::create([
            'restaurant_id' => $sushiRestaurant->id,
            'table_number' => 9,
            'code' => Str::random(10),
            'is_active' => false,
        ]);
    }
}
