<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            [
                'name' => 'Makanan Utama',
                'description' => 'Menu makanan utama seperti nasi, ayam, dll',
                'is_active' => true,
            ],
            [
                'name' => 'Minuman',
                'description' => 'Menu minuman segar dan hangat',
                'is_active' => true,
            ],
            [
                'name' => 'Snack',
                'description' => 'Menu cemilan dan makanan ringan',
                'is_active' => true,
            ],
            [
                'name' => 'Dessert',
                'description' => 'Menu penutup dan makanan manis',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
