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
        User::create([
            'name' => 'Restaurant Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
        ]);

        // Create staff users
        User::create([
            'name' => 'Kitchen Staff',
            'email' => 'kitchen@example.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
            // restaurant_id will be set after restaurants are created
        ]);

        User::create([
            'name' => 'Manager Staff',
            'email' => 'manager@example.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
            // restaurant_id will be set after restaurants are created
        ]);
    }
}
