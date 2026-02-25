<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\RestockBatch;
use App\Models\RestockBatchItem;
use App\Models\DailyJournal;
use App\Models\DailyJournalLine;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class RestockBatchSeeder extends Seeder
{
    public function run(): void
    {
        $item = fn (string $name) => Item::where('name', $name)->first();

        $batches = [
            [
                'date'  => Carbon::create(2026, 2, 24),
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
                    ['item' => 'Salt',                'qty' => 500,  'cpu' => 0.05],
                ],
            ],
            [
                'date'  => Carbon::create(2026, 2, 25),
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

        // Track daily sequence per date string to generate RST-YYYYMMDD-NNN codes
        $dailyCount = [];

        foreach ($batches as $batchData) {
            $batchDate  = $batchData['date'];
            $dateKey    = $batchDate->format('Ymd');
            $dailyCount[$dateKey] = ($dailyCount[$dateKey] ?? 0) + 1;
            $batchCode  = 'RST-' . $dateKey . '-' . str_pad($dailyCount[$dateKey], 3, '0', STR_PAD_LEFT);

            // Journal header for this restock
            $journal = new DailyJournal([
                'journal_date' => $batchDate->toDateString(),
                'kind'         => 'restock',
                'notes'        => $batchData['notes'],
            ]);
            $journal->created_at = $batchDate;
            $journal->updated_at = $batchDate;
            $journal->save();

            $totalCost = collect($batchData['items'])
                ->sum(fn ($line) => $line['qty'] * $line['cpu']);

            $batch = new RestockBatch([
                'batch_code' => $batchCode,
                'journal_id' => $journal->id,
                'notes'      => $batchData['notes'],
                'total_cost' => round($totalCost, 2),
            ]);
            $batch->created_at = $batchDate;
            $batch->updated_at = $batchDate;
            $batch->save();

            foreach ($batchData['items'] as $line) {
                $itemModel = $item($line['item']);
                $batchItem = new RestockBatchItem([
                    'restock_batch_id' => $batch->id,
                    'item_id'          => $itemModel->id,
                    'quantity_added'   => $line['qty'],
                    'cost_per_unit'    => $line['cpu'],
                    'subtotal'         => round($line['qty'] * $line['cpu'], 4),
                ]);
                $batchItem->created_at = $batchDate;
                $batchItem->updated_at = $batchDate;
                $batchItem->save();

                // Append-only journal line — the sole source of truth for stock
                $journalLine = new DailyJournalLine([
                    'daily_journal_id'      => $journal->id,
                    'item_id'               => $itemModel->id,
                    'restock_batch_item_id' => $batchItem->id,
                    'direction'             => 'in',
                    'quantity'              => $line['qty'],
                    'meta'                  => ['batch_code' => $batchCode],
                ]);
                $journalLine->created_at = $batchDate;
                $journalLine->updated_at = $batchDate;
                $journalLine->save();

                $batchItem->update(['journal_line_id' => $journalLine->id]);

                // NOTE: no $itemModel->increment() — stock is derived from the ledger
            }
        }
    }
}
