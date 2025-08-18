<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create owner user
        User::firstOrCreate(
            ['email' => 'owner@example.com'],
            [
                'name' => 'Restaurant Owner',
                'password' => Hash::make('password'),
                'role' => 'owner',
            ]
        );

        // Create staff users
        User::firstOrCreate(
            ['email' => 'kitchen@example.com'],
            [
                'name' => 'Kitchen Staff',
                'password' => Hash::make('password'),
                'role' => 'staff',
                // restaurant_id will be set after restaurants are created
            ]
        );

        User::firstOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Manager Staff',
                'password' => Hash::make('password'),
                'role' => 'staff',
                // restaurant_id will be set after restaurants are created
            ]
        );
    }
}
