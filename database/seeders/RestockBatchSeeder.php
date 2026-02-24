<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\RestockBatch;
use App\Models\RestockBatchItem;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

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

        // Precompute a 7-day span starting Feb 24, 2026 (rolls into March for overflow)
        $dateSpan = collect(range(0, 6))->map(fn ($i) => Carbon::create(2026, 2, 24)->addDays($i));
        $lineCursor = 0;

        foreach ($batches as $idx => $batchData) {
            $totalCost = collect($batchData['items'])
                ->sum(fn ($line) => $line['qty'] * $line['cpu']);

            $batchDate = $dateSpan[$idx % $dateSpan->count()];

            $batch = new RestockBatch([
                'notes'      => $batchData['notes'],
                'total_cost' => $totalCost,
            ]);
            $batch->created_at = $batchDate;
            $batch->updated_at = $batchDate;
            $batch->save();

            foreach ($batchData['items'] as $line) {
                $lineDate = $dateSpan[$lineCursor % $dateSpan->count()];
                $lineCursor++;

                $itemModel = $item($line['item']);
                $batchItem = new RestockBatchItem([
                    'restock_batch_id' => $batch->id,
                    'item_id'          => $itemModel->id,
                    'quantity_added'   => $line['qty'],
                    'cost_per_unit'    => $line['cpu'],
                    'subtotal'         => $line['qty'] * $line['cpu'],
                ]);
                $batchItem->created_at = $lineDate;
                $batchItem->updated_at = $lineDate;
                $batchItem->save();
            }
        }
    }
}
