<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // pricing_mode:
        //   manual  → selling_price > 0, markup_percentage = 0
        //   markup  → selling_price = 0, markup_percentage > 0
        //   auto    → selling_price = 0, markup_percentage = 0  (cost = effective price)
        $products = [
            [
                'name'               => 'Chocolate Cake',
                'selling_price'      => 0,
                'markup_percentage'  => 30,
            ],
            [
                'name'               => 'Butter Cookies',
                'selling_price'      => 150.00,
                'markup_percentage'  => 0,
            ],
            [
                'name'               => 'Classic Bread Roll',
                'selling_price'      => 0,
                'markup_percentage'  => 0,
            ],
            [
                'name'               => 'Vanilla Cupcake',
                'selling_price'      => 0,
                'markup_percentage'  => 25,
            ],
            [
                'name'               => 'Cheesecake Slice',
                'selling_price'      => 180.00,
                'markup_percentage'  => 0,
            ],
        ];

        foreach ($products as $data) {
            Product::create([
                'name'              => $data['name'],
                'image_path'        => null,
                'selling_price'     => $data['selling_price'],
                'markup_percentage' => $data['markup_percentage'],
            ]);
        }
    }
}
