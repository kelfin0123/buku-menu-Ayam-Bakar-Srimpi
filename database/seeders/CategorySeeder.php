<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Makanan',     'slug' => 'makanan',     'icon' => 'drumstick', 'sort_order' => 1],
            ['name' => 'Minuman',     'slug' => 'minuman',     'icon' => 'cup',       'sort_order' => 2],
            ['name' => 'Paket Hemat', 'slug' => 'paket-hemat', 'icon' => 'package',   'sort_order' => 3],
            ['name' => 'Promo',       'slug' => 'promo',       'icon' => 'fire',      'sort_order' => 4],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
