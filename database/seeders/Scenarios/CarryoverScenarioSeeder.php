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
 * Carry-over Batch Scenario
 * =========================
 * Demonstrates (and lets you manually verify) the carry-over depletion mechanic
 * in ProductionController and the live carryoverPlan useMemo in Produce/Index.jsx.
 *
 * One product: "Sesame Cookie"
 *   Recipe per cookie:
 *     • 0.5 kg  All-Purpose Flour
 *     • 1   pcs Eggs
 *
 * Stock split deliberately so no single batch covers the full need:
 *
 *   Flour (need 5 kg to produce 10 cookies):
 *     RST-20260220-001  Feb 20 │  2.0 kg  → fully depleted       (rose)
 *     RST-20260222-001  Feb 22 │  1.5 kg  → fully depleted       (rose)
 *     RST-20260225-001  Feb 25 │  3.0 kg  → partially used 1.5   (amber)
 *                              │  total 6.5 kg
 *
 *   Eggs (need 10 pcs to produce 10 cookies):
 *     RST-20260221-001  Feb 21 │  6 pcs   → fully depleted       (rose)
 *     RST-20260224-001  Feb 24 │  8 pcs   → partially used 4     (amber)
 *                              │  total 14 pcs
 *
 * Expected carryoverPlan for qty=10 (FIFO order):
 *   Flour: { RST-20260220-001: 2.0, RST-20260222-001: 1.5, RST-20260225-001: 1.5 }
 *   Eggs:  { RST-20260221-001: 6,   RST-20260224-001: 4 }
 *
 * Max build: 13 (flour-limited: 6.5 kg / 0.5 kg per cookie)
 *
 * Run standalone:
 *   php artisan db:seed --class="Database\Seeders\Scenarios\CarryoverScenarioSeeder"
 *
 * Or via scenario env:
 *   DB_SEED_SCENARIO=carryover php artisan db:seed
 */
class CarryoverScenarioSeeder extends Seeder
{
    public function run(): void
    {
        // ── Units (firstOrCreate so it works standalone or after a full seed) ──
        $kg  = Unit::firstOrCreate(['abbreviation' => 'kg'],  ['name' => 'Kilogram']);
        $pcs = Unit::firstOrCreate(['abbreviation' => 'pcs'], ['name' => 'Piece']);

        // ── Items ─────────────────────────────────────────────────────────────
        $flour = Item::create([
            'name'          => 'All-Purpose Flour',
            'unit_id'       => $kg->id,
            'cost_per_unit' => 50.00,
            'default_stock' => 0,
            'current_stock' => 0,
        ]);

        $eggs = Item::create([
            'name'          => 'Eggs',
            'unit_id'       => $pcs->id,
            'cost_per_unit' => 8.00,
            'default_stock' => 0,
            'current_stock' => 0,
        ]);

        // ── Product ───────────────────────────────────────────────────────────
        $product = Product::create([
            'name'              => 'Sesame Cookie',
            'selling_price'     => 150.00,
            'markup_percentage' => 0,
        ]);

        // ── Recipe: 0.5 kg Flour + 1 Egg per cookie ──────────────────────────
        ProductIngredient::create([
            'product_id' => $product->id,
            'item_id'    => $flour->id,
            'quantity'   => 0.5,
        ]);
        ProductIngredient::create([
            'product_id' => $product->id,
            'item_id'    => $eggs->id,
            'quantity'   => 1,
        ]);

        // ── Flour batches (3 batches, none alone covers 10-cookie need) ───────
        $this->restockBatch('RST-20260220-001', Carbon::create(2026, 2, 20), 'Flour batch A — oldest', [
            [$flour, 2.0, 50.00],
        ]);

        $this->restockBatch('RST-20260222-001', Carbon::create(2026, 2, 22), 'Flour batch B', [
            [$flour, 1.5, 50.00],
        ]);

        $this->restockBatch('RST-20260225-001', Carbon::create(2026, 2, 25), 'Flour batch C — newest, partial draw', [
            [$flour, 3.0, 50.00],
        ]);

        // ── Egg batches (2 batches, neither alone covers 10-cookie need) ──────
        $this->restockBatch('RST-20260221-001', Carbon::create(2026, 2, 21), 'Egg batch A — oldest', [
            [$eggs, 6, 8.00],
        ]);

        $this->restockBatch('RST-20260224-001', Carbon::create(2026, 2, 24), 'Egg batch B — partial draw', [
            [$eggs, 8, 8.00],
        ]);

        // ── Summary ───────────────────────────────────────────────────────────
        $this->command?->newLine();
        $this->command?->info('✔ Carry-over scenario seeded.');
        $this->command?->table(
            ['What', 'Detail'],
            [
                ['Product',       "Sesame Cookie (id: {$product->id})"],
                ['Recipe',        '0.5 kg Flour + 1 Egg per cookie'],
                ['Flour batches', 'Feb 20: 2.0 kg  │  Feb 22: 1.5 kg  │  Feb 25: 3.0 kg  (total 6.5 kg)'],
                ['Egg batches',   'Feb 21: 6 pcs   │  Feb 24: 8 pcs   (total 14 pcs)'],
                ['Produce 10 →',  'Flour: 2.0 (A, fully depleted) + 1.5 (B, fully depleted) + 1.5 (C, partial)'],
                ['',              'Eggs:  6 (A, fully depleted) + 4 (B, partial)'],
                ['Max build',     '13 (flour-limited: 6.5 kg ÷ 0.5 kg)'],
                ['Go to',         '/produce  →  select Sesame Cookie  →  set qty to 10'],
            ]
        );
        $this->command?->newLine();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Creates one complete restock: DailyJournal → RestockBatch → RestockBatchItem(s) →
     * DailyJournalLine(s). Timestamps are back-dated to $date.
     *
     * $lines  array of  [Item $item, float $qty, float $cpu]
     */
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
