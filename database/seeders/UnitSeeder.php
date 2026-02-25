<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'Kilogram',    'abbreviation' => 'kg'],
            ['name' => 'Gram',        'abbreviation' => 'g'],
            ['name' => 'Piece',       'abbreviation' => 'pcs'],
            ['name' => 'Liter',       'abbreviation' => 'L'],
            ['name' => 'Milliliter',  'abbreviation' => 'mL'],
            ['name' => 'Cup',         'abbreviation' => 'cup'],
            ['name' => 'Tablespoon',  'abbreviation' => 'tbsp'],
            ['name' => 'Teaspoon',    'abbreviation' => 'tsp'],
        ];

        foreach ($units as $unit) {
            Unit::create($unit);
        }
    }
}
