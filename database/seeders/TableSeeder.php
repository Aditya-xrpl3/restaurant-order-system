<?php

namespace Database\Seeders;

use App\Models\Table;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    public function run()
    {
        // Create 20 tables
        for ($i = 1; $i <= 20; $i++) {
            Table::create([
                'table_number' => sprintf('T%02d', $i),
                'capacity' => rand(2, 6),
                'status' => 'available',
            ]);
        }
    }
}

