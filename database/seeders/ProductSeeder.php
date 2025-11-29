<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::create([
            'name' => 'Flash Sale Product',
            'price' => 99.99,
            'stock' => 100,
        ]);

        Product::create([
            'name' => 'Limited Edition Item',
            'price' => 199.99,
            'stock' => 50,
        ]);

        Product::create([
            'name' => 'Premium Product',
            'price' => 299.99,
            'stock' => 25,
        ]);
    }
}

