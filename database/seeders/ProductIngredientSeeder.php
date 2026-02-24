<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Product;
use App\Models\ProductIngredient;
use Illuminate\Database\Seeder;

class ProductIngredientSeeder extends Seeder
{
    public function run(): void
    {
        // Helper to fetch IDs by name
        $item    = fn (string $name) => Item::where('name', $name)->first()->id;
        $product = fn (string $name) => Product::where('name', $name)->first()->id;

        // Quantities must match the unit of each Item
        // e.g. Flour is in kg → 0.2 means 200 g worth
        $recipes = [
            'Chocolate Cake' => [
                ['item' => 'All-Purpose Flour', 'qty' => 0.2],    // 200 g
                ['item' => 'White Sugar',        'qty' => 0.15],   // 150 g
                ['item' => 'Salted Butter',      'qty' => 0.1],    // 100 g
                ['item' => 'Eggs',               'qty' => 2],      // 2 pcs
                ['item' => 'Fresh Milk',         'qty' => 0.1],    // 100 mL
                ['item' => 'Cocoa Powder',       'qty' => 50],     // 50 g
                ['item' => 'Baking Powder',      'qty' => 5],      // 5 g
            ],
            'Butter Cookies' => [
                ['item' => 'All-Purpose Flour',  'qty' => 0.1],    // 100 g
                ['item' => 'White Sugar',         'qty' => 0.08],   // 80 g
                ['item' => 'Salted Butter',       'qty' => 0.06],   // 60 g
                ['item' => 'Eggs',                'qty' => 1],      // 1 pcs
            ],
            'Classic Bread Roll' => [
                ['item' => 'All-Purpose Flour',  'qty' => 0.05],   // 50 g
                ['item' => 'White Sugar',         'qty' => 0.005],  // 5 g
                ['item' => 'Salted Butter',       'qty' => 0.01],   // 10 g
                ['item' => 'Salt',                'qty' => 1],      // 1 g
                ['item' => 'Active Dry Yeast',    'qty' => 2],      // 2 g
            ],
            'Vanilla Cupcake' => [
                ['item' => 'All-Purpose Flour',  'qty' => 0.08],   // 80 g
                ['item' => 'White Sugar',         'qty' => 0.06],   // 60 g
                ['item' => 'Salted Butter',       'qty' => 0.04],   // 40 g
                ['item' => 'Eggs',                'qty' => 1],      // 1 pcs
                ['item' => 'Vanilla Extract',     'qty' => 5],      // 5 mL
                ['item' => 'Baking Powder',       'qty' => 3],      // 3 g
            ],
            'Cheesecake Slice' => [
                ['item' => 'Cream Cheese',       'qty' => 0.1],    // 100 g
                ['item' => 'White Sugar',         'qty' => 0.04],   // 40 g
                ['item' => 'Eggs',                'qty' => 1],      // 1 pcs
                ['item' => 'Graham Crackers',     'qty' => 30],     // 30 g
                ['item' => 'Salted Butter',       'qty' => 0.02],   // 20 g
                ['item' => 'Condensed Milk',      'qty' => 60],     // 60 mL
            ],
        ];

        foreach ($recipes as $productName => $ingredients) {
            $productId = $product($productName);
            foreach ($ingredients as $line) {
                ProductIngredient::create([
                    'product_id' => $productId,
                    'item_id'    => $item($line['item']),
                    'quantity'   => $line['qty'],
                ]);
            }
        }
    }
}
