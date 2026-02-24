<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Database\Seeder;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        // Load products with ingredients so computed_cost is available
        $products = Product::with('ingredients.item.unit')->get()->keyBy('name');

        $getEffectivePrice = function (Product $product): float {
            if ($product->selling_price > 0) {
                return (float) $product->selling_price;
            }
            if ($product->markup_percentage > 0) {
                return round($product->computed_cost * (1 + $product->markup_percentage / 100), 2);
            }
            return $product->computed_cost;
        };

        $sales = [
            ['product' => 'Chocolate Cake',    'qty' => 2,  'notes' => null],
            ['product' => 'Chocolate Cake',    'qty' => 1,  'notes' => 'Birthday order'],
            ['product' => 'Butter Cookies',    'qty' => 5,  'notes' => null],
            ['product' => 'Butter Cookies',    'qty' => 3,  'notes' => 'Packed as gift'],
            ['product' => 'Classic Bread Roll','qty' => 10, 'notes' => null],
            ['product' => 'Classic Bread Roll','qty' => 6,  'notes' => 'Morning batch'],
            ['product' => 'Vanilla Cupcake',   'qty' => 4,  'notes' => null],
            ['product' => 'Vanilla Cupcake',   'qty' => 2,  'notes' => 'Walk-in customer'],
            ['product' => 'Cheesecake Slice',  'qty' => 3,  'notes' => null],
            ['product' => 'Cheesecake Slice',  'qty' => 1,  'notes' => 'Online order'],
        ];

        foreach ($sales as $data) {
            $product    = $products[$data['product']];
            $unitPrice  = $getEffectivePrice($product);
            $totalPrice = round($unitPrice * $data['qty'], 2);

            Sale::create([
                'product_id'    => $product->id,
                'quantity_sold' => $data['qty'],
                'unit_price'    => $unitPrice,
                'total_price'   => $totalPrice,
                'notes'         => $data['notes'],
            ]);
        }
    }
}
