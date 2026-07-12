<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $makanan = Category::where('slug', 'makanan')->first();
        $minuman = Category::where('slug', 'minuman')->first();

        $products = [
            [
                'category_id' => $makanan->id,
                'name' => 'Ayam Bakar Srimpi',
                'slug' => 'ayam-bakar-srimpi',
                'description' => 'Ayam bakar bumbu khas Srimpi, sambal, lalapan.',
                'price' => 28000,
                'image' => 'ayam-bakar-srimpi.jpg',
                'sort_order' => 1,
            ],
            [
                'category_id' => $makanan->id,
                'name' => 'Ayam Penyet',
                'slug' => 'ayam-penyet',
                'description' => 'Ayam goreng penyet dengan sambal pedas dan lalapan.',
                'price' => 26000,
                'image' => 'ayam-penyet.jpg',
                'sort_order' => 2,
            ],
            [
                'category_id' => $makanan->id,
                'name' => 'Ayam Goreng',
                'slug' => 'ayam-goreng',
                'description' => 'Ayam goreng renyah dengan bumbu pilihan.',
                'price' => 24000,
                'image' => 'ayam-goreng.jpg',
                'sort_order' => 3,
            ],
            [
                'category_id' => $makanan->id,
                'name' => 'Nasi Goreng Srimpi',
                'slug' => 'nasi-goreng-srimpi',
                'description' => 'Nasi goreng spesial dengan ayam dan telur.',
                'price' => 20000,
                'image' => 'nasi-goreng-srimpi.jpg',
                'sort_order' => 4,
            ],
            [
                'category_id' => $minuman->id,
                'name' => 'Es Teh Manis',
                'slug' => 'es-teh-manis',
                'description' => 'Teh manis segar dingin.',
                'price' => 6000,
                'image' => 'es-teh-manis.jpg',
                'sort_order' => 5,
            ],
            [
                'category_id' => $minuman->id,
                'name' => 'Es Jeruk',
                'slug' => 'es-jeruk',
                'description' => 'Jeruk segar pilihan yang menyegarkan.',
                'price' => 10000,
                'image' => 'es-jeruk.jpg',
                'sort_order' => 6,
            ],
            [
                'category_id' => $minuman->id,
                'name' => 'Es Lemon Tea',
                'slug' => 'es-lemon-tea',
                'description' => 'Perpaduan teh dan lemon segar.',
                'price' => 8000,
                'image' => 'es-lemon-tea.jpg',
                'sort_order' => 7,
            ],
            [
                'category_id' => $minuman->id,
                'name' => 'Air Mineral',
                'slug' => 'air-mineral',
                'description' => 'Air mineral kemasan.',
                'price' => 5000,
                'image' => 'air-mineral.jpg',
                'sort_order' => 8,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(['slug' => $product['slug']], $product);
        }
    }
}
