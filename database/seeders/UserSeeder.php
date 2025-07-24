<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Create Admin
        User::create([
            'name' => 'Administrator',
            'email' => 'admin@restaurant.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Create Cashier
        User::create([
            'name' => 'Kasir 1',
            'email' => 'cashier@restaurant.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
            'is_active' => true,
        ]);

        // Create Sample Users
        User::create([
            'name' => 'Customer 1',
            'email' => 'customer1@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Customer 2',
            'email' => 'customer2@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'is_active' => true,
        ]);
    }
}
