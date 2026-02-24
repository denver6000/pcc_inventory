<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $kg  = Unit::where('abbreviation', 'kg')->first()->id;
        $g   = Unit::where('abbreviation', 'g')->first()->id;
        $pcs = Unit::where('abbreviation', 'pcs')->first()->id;
        $L   = Unit::where('abbreviation', 'L')->first()->id;
        $mL  = Unit::where('abbreviation', 'mL')->first()->id;

        // Fields: name, image_path, unit_id, cost_per_unit, default_stock, current_stock
        $items = [
            ['name' => 'All-Purpose Flour',  'unit_id' => $kg,  'cost_per_unit' => 50.00,  'default_stock' => 20],
            ['name' => 'White Sugar',         'unit_id' => $kg,  'cost_per_unit' => 60.00,  'default_stock' => 10],
            ['name' => 'Salted Butter',       'unit_id' => $kg,  'cost_per_unit' => 200.00, 'default_stock' => 5],
            ['name' => 'Eggs',                'unit_id' => $pcs, 'cost_per_unit' => 8.00,   'default_stock' => 100],
            ['name' => 'Fresh Milk',          'unit_id' => $L,   'cost_per_unit' => 70.00,  'default_stock' => 10],
            ['name' => 'Salt',                'unit_id' => $g,   'cost_per_unit' => 0.05,   'default_stock' => 500],
            ['name' => 'Baking Powder',       'unit_id' => $g,   'cost_per_unit' => 0.10,   'default_stock' => 200],
            ['name' => 'Cocoa Powder',        'unit_id' => $g,   'cost_per_unit' => 0.20,   'default_stock' => 300],
            ['name' => 'Vanilla Extract',     'unit_id' => $mL,  'cost_per_unit' => 0.50,   'default_stock' => 100],
            ['name' => 'Bread Crumbs',        'unit_id' => $g,   'cost_per_unit' => 0.08,   'default_stock' => 500],
            ['name' => 'Condensed Milk',      'unit_id' => $mL,  'cost_per_unit' => 0.30,   'default_stock' => 400],
            ['name' => 'Vegetable Oil',       'unit_id' => $mL,  'cost_per_unit' => 0.12,   'default_stock' => 1000],
            ['name' => 'Active Dry Yeast',    'unit_id' => $g,   'cost_per_unit' => 0.40,   'default_stock' => 100],
            ['name' => 'Cream Cheese',        'unit_id' => $kg,  'cost_per_unit' => 350.00, 'default_stock' => 3],
            ['name' => 'Graham Crackers',     'unit_id' => $g,   'cost_per_unit' => 0.15,   'default_stock' => 400],
        ];

        foreach ($items as $data) {
            Item::create([
                'name'          => $data['name'],
                'image_path'    => null,
                'unit_id'       => $data['unit_id'],
                'cost_per_unit' => $data['cost_per_unit'],
                'default_stock' => $data['default_stock'],
                'current_stock' => 0, // stock comes exclusively from RestockBatchItem rows
            ]);
        }
    }
}
