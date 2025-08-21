<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Run all seeders in the correct order
        $this->call([
            // Original seeder
            EventSeeder::class,

            // Eattinee seeders
            UserSeeder::class,
            RestaurantSeeder::class,
            MenuSeeder::class,
            QrCodeSeeder::class,
            OrderSeeder::class,
            BillSeeder::class,
            PaymentSeeder::class,
        ]);
    }
}
