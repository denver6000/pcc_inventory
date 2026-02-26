<?php

namespace Database\Seeders\Scenarios;

use App\Models\DailyJournal;
use App\Models\DailyJournalLine;
use App\Models\Item;
use App\Models\Product;
use App\Models\ProductIngredient;
use App\Models\RestockBatch;
use App\Models\RestockBatchItem;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Shortage Scenario
 * =================
 * Demonstrates the insufficient-stock validation in ProductionController.
 * One ingredient has enough, the other falls short so the produce attempt must fail.
 *
 * One product: "Brioche Roll"
 *   Recipe per roll:
 *     • 0.1 kg  All-Purpose Flour   (need 1 kg for 10 rolls — stock: 1.5 kg  ✔)
 *     • 0.05 kg Salted Butter       (need 0.5 kg for 10 rolls — stock: 0.2 kg  ✘)
 *
 *   Attempting to produce 10 rolls must error:
 *     "Salted Butter: need 0.5 kg, have 0.2 kg"
 *
 * Run standalone:
 *   php artisan db:seed --class="Database\Seeders\Scenarios\ShortageScenarioSeeder"
 *
 * Or via scenario env:
 *   DB_SEED_SCENARIO=shortage php artisan db:seed
 */
class ShortageScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $kg = Unit::firstOrCreate(['abbreviation' => 'kg'], ['name' => 'Kilogram']);

        $flour  = Item::create([
            'name'          => 'All-Purpose Flour',
            'unit_id'       => $kg->id,
            'cost_per_unit' => 50.00,
            'default_stock' => 0,
            'current_stock' => 0,
        ]);

        $butter = Item::create([
            'name'          => 'Salted Butter',
            'unit_id'       => $kg->id,
            'cost_per_unit' => 200.00,
            'default_stock' => 0,
            'current_stock' => 0,
        ]);

        $product = Product::create([
            'name'              => 'Brioche Roll',
            'selling_price'     => 0,
            'markup_percentage' => 20,
        ]);

        ProductIngredient::create(['product_id' => $product->id, 'item_id' => $flour->id,  'quantity' => 0.1]);
        ProductIngredient::create(['product_id' => $product->id, 'item_id' => $butter->id, 'quantity' => 0.05]);

        // Flour: plenty (1.5 kg; need 1.0 kg for 10 rolls)
        $this->restockBatch('RST-TEST-FL-001', Carbon::create(2026, 2, 24), 'Flour — sufficient', [
            [$flour, 1.5, 50.00],
        ]);

        // Butter: not enough (0.2 kg; need 0.5 kg for 10 rolls)
        $this->restockBatch('RST-TEST-BU-001', Carbon::create(2026, 2, 24), 'Butter — insufficient', [
            [$butter, 0.2, 200.00],
        ]);

        $this->command?->newLine();
        $this->command?->info('✔ Shortage scenario seeded.');
        $this->command?->table(
            ['What', 'Detail'],
            [
                ['Product',          "Brioche Roll (id: {$product->id})"],
                ['Recipe',           '0.1 kg Flour + 0.05 kg Butter per roll'],
                ['Flour stock',      '1.5 kg  (need 1.0 for 10 rolls → OK)'],
                ['Butter stock',     '0.2 kg  (need 0.5 for 10 rolls → INSUFFICIENT)'],
                ['Expected error',   '"Salted Butter: need 0.5 kg, have 0.2 kg"'],
                ['Max build (flour)', '15 rolls'],
                ['Max build (butter)','4 rolls (limits the product)'],
                ['Go to',            '/produce  →  select Brioche Roll  →  try qty 10  →  expect validation error'],
            ]
        );
        $this->command?->newLine();
    }

    private function restockBatch(string $code, Carbon $date, string $notes, array $lines): void
    {
        $journal = new DailyJournal([
            'journal_date' => $date->toDateString(),
            'kind'         => 'restock',
            'notes'        => $notes,
        ]);
        $journal->created_at = $date;
        $journal->updated_at = $date;
        $journal->save();

        $totalCost = collect($lines)->sum(fn ($l) => $l[1] * $l[2]);

        $batch = new RestockBatch([
            'batch_code' => $code,
            'journal_id' => $journal->id,
            'notes'      => $notes,
            'total_cost' => round($totalCost, 2),
        ]);
        $batch->created_at = $date;
        $batch->updated_at = $date;
        $batch->save();

        foreach ($lines as [$item, $qty, $cpu]) {
            $batchItem = new RestockBatchItem([
                'restock_batch_id' => $batch->id,
                'item_id'          => $item->id,
                'quantity_added'   => $qty,
                'cost_per_unit'    => $cpu,
                'subtotal'         => round($qty * $cpu, 4),
            ]);
            $batchItem->created_at = $date;
            $batchItem->updated_at = $date;
            $batchItem->save();

            $line = new DailyJournalLine([
                'daily_journal_id'      => $journal->id,
                'item_id'               => $item->id,
                'restock_batch_item_id' => $batchItem->id,
                'direction'             => 'in',
                'quantity'              => $qty,
                'meta'                  => ['batch_code' => $code],
            ]);
            $line->created_at = $date;
            $line->updated_at = $date;
            $line->save();

            $batchItem->update(['journal_line_id' => $line->id]);
        }
    }
}
