<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $makananUtama = Category::where('name', 'Makanan Utama')->first();
        $minuman = Category::where('name', 'Minuman')->first();
        $snack = Category::where('name', 'Snack')->first();
        $dessert = Category::where('name', 'Dessert')->first();

        $products = [
            // Makanan Utama
            [
                'name' => 'Nasi Ayam Bakar',
                'description' => 'Nasi putih dengan ayam bakar bumbu kecap, lalapan, dan sambal',
                'price' => 25000,
                'category_id' => $makananUtama->id,
                'stock' => 50,
                'is_available' => true,
            ],
            [
                'name' => 'Nasi Ayam Goreng',
                'description' => 'Nasi putih dengan ayam goreng crispy, lalapan, dan sambal',
                'price' => 23000,
                'category_id' => $makananUtama->id,
                'stock' => 45,
                'is_available' => true,
            ],
            [
                'name' => 'Nasi Gudeg',
                'description' => 'Nasi putih dengan gudeg, ayam, telur, dan sambal krecek',
                'price' => 20000,
                'category_id' => $makananUtama->id,
                'stock' => 30,
                'is_available' => true,
            ],
            [
                'name' => 'Mie Ayam',
                'description' => 'Mie dengan topping ayam, pangsit, dan sayuran',
                'price' => 18000,
                'category_id' => $makananUtama->id,
                'stock' => 40,
                'is_available' => true,
            ],

            // Minuman
            [
                'name' => 'Es Teh Manis',
                'description' => 'Teh manis dingin yang menyegarkan',
                'price' => 5000,
                'category_id' => $minuman->id,
                'stock' => 100,
                'is_available' => true,
            ],
            [
                'name' => 'Es Jeruk',
                'description' => 'Jus jeruk segar dengan es batu',
                'price' => 8000,
                'category_id' => $minuman->id,
                'stock' => 80,
                'is_available' => true,
            ],
            [
                'name' => 'Kopi Hitam',
                'description' => 'Kopi hitam hangat tanpa gula',
                'price' => 7000,
                'category_id' => $minuman->id,
                'stock' => 60,
                'is_available' => true,
            ],
            [
                'name' => 'Jus Alpukat',
                'description' => 'Jus alpukat segar dengan susu kental manis',
                'price' => 12000,
                'category_id' => $minuman->id,
                'stock' => 35,
                'is_available' => true,
            ],

            // Snack
            [
                'name' => 'Kerupuk Udang',
                'description' => 'Kerupuk udang crispy',
                'price' => 3000,
                'category_id' => $snack->id,
                'stock' => 200,
                'is_available' => true,
            ],
            [
                'name' => 'Tahu Goreng',
                'description' => 'Tahu goreng dengan bumbu kacang',
                'price' => 8000,
                'category_id' => $snack->id,
                'stock' => 50,
                'is_available' => true,
            ],
            [
                'name' => 'Pisang Goreng',
                'description' => 'Pisang goreng crispy dengan madu',
                'price' => 10000,
                'category_id' => $snack->id,
                'stock' => 30,
                'is_available' => true,
            ],

            // Dessert
            [
                'name' => 'Es Krim Vanilla',
                'description' => 'Es krim vanilla dengan topping coklat',
                'price' => 15000,
                'category_id' => $dessert->id,
                'stock' => 25,
                'is_available' => true,
            ],
            [
                'name' => 'Puding Coklat',
                'description' => 'Puding coklat dengan whipped cream',
                'price' => 12000,
                'category_id' => $dessert->id,
                'stock' => 20,
                'is_available' => true,
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}

