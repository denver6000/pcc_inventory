<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\RestockBatch;
use App\Models\RestockBatchItem;
use Illuminate\Database\Seeder;

class RestockBatchSeeder extends Seeder
{
    public function run(): void
    {
        $item = fn (string $name) => Item::where('name', $name)->first();

        $batches = [
            [
                'notes' => 'Initial stock loading',
                'items' => [
                    ['item' => 'All-Purpose Flour',  'qty' => 20,   'cpu' => 50.00],
                    ['item' => 'White Sugar',         'qty' => 10,   'cpu' => 60.00],
                    ['item' => 'Salted Butter',       'qty' => 5,    'cpu' => 200.00],
                    ['item' => 'Eggs',                'qty' => 100,  'cpu' => 8.00],
                    ['item' => 'Fresh Milk',          'qty' => 10,   'cpu' => 70.00],
                    ['item' => 'Cocoa Powder',        'qty' => 300,  'cpu' => 0.20],
                    ['item' => 'Baking Powder',       'qty' => 200,  'cpu' => 0.10],
                    ['item' => 'Vanilla Extract',     'qty' => 100,  'cpu' => 0.50],
                ],
            ],
            [
                'notes' => 'Mid-month replenishment',
                'items' => [
                    ['item' => 'All-Purpose Flour',  'qty' => 10,   'cpu' => 50.00],
                    ['item' => 'White Sugar',         'qty' => 5,    'cpu' => 60.00],
                    ['item' => 'Cream Cheese',        'qty' => 3,    'cpu' => 350.00],
                    ['item' => 'Graham Crackers',     'qty' => 400,  'cpu' => 0.15],
                    ['item' => 'Condensed Milk',      'qty' => 400,  'cpu' => 0.30],
                    ['item' => 'Active Dry Yeast',    'qty' => 100,  'cpu' => 0.40],
                ],
            ],
        ];

        foreach ($batches as $batchData) {
            $totalCost = collect($batchData['items'])
                ->sum(fn ($line) => $line['qty'] * $line['cpu']);

            $batch = RestockBatch::create([
                'notes'      => $batchData['notes'],
                'total_cost' => $totalCost,
            ]);

            foreach ($batchData['items'] as $line) {
                $itemModel = $item($line['item']);
                RestockBatchItem::create([
                    'restock_batch_id' => $batch->id,
                    'item_id'          => $itemModel->id,
                    'quantity_added'   => $line['qty'],
                    'cost_per_unit'    => $line['cpu'],
                    'subtotal'         => $line['qty'] * $line['cpu'],
                ]);
            }
        }
    }
}
