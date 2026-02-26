<?php

namespace Tests\Unit;

use App\Models\DailyJournal;
use App\Models\DailyJournalLine;
use App\Models\Item;
use App\Models\Product;
use App\Models\ProductIngredient;
use App\Models\RestockBatch;
use App\Models\RestockBatchItem;
use App\Models\Unit;
use App\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockLedgerTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ──────────────────────────────────────────────

    private function createUnit(): Unit
    {
        return Unit::create(['name' => 'Kilogram', 'abbreviation' => 'kg']);
    }

    private function createItem(Unit $unit, array $overrides = []): Item
    {
        return Item::create(array_merge([
            'name'          => 'Flour',
            'unit_id'       => $unit->id,
            'cost_per_unit' => 50.0,
            'default_stock' => 0,
            'current_stock' => 0,
        ], $overrides));
    }

    /**
     * Restock an item via journal + batch, returning the RestockBatchItem.
     */
    private function restockItem(Item $item, float $qty, string $date): RestockBatchItem
    {
        $journal = DailyJournal::create([
            'journal_date' => $date,
            'kind'         => 'restock',
            'notes'        => null,
        ]);

        $batch = RestockBatch::create([
            'batch_code' => 'RST-TEST-' . $item->id . '-' . $qty,
            'journal_id' => $journal->id,
            'notes'      => null,
            'total_cost' => $qty * $item->cost_per_unit,
        ]);

        $batchItem = RestockBatchItem::create([
            'restock_batch_id' => $batch->id,
            'item_id'          => $item->id,
            'quantity_added'   => $qty,
            'cost_per_unit'    => $item->cost_per_unit,
            'subtotal'         => $qty * $item->cost_per_unit,
        ]);

        $line = DailyJournalLine::create([
            'daily_journal_id'      => $journal->id,
            'item_id'               => $item->id,
            'restock_batch_item_id' => $batchItem->id,
            'direction'             => 'in',
            'quantity'              => $qty,
        ]);

        $batchItem->update(['journal_line_id' => $line->id]);

        return $batchItem;
    }

    /**
     * Consume from specific batches via journal lines.
     */
    private function consumeItem(Item $item, array $takes, string $date): void
    {
        $journal = DailyJournal::create([
            'journal_date' => $date,
            'kind'         => 'consume',
            'notes'        => null,
        ]);

        foreach ($takes as [$batchItem, $qty]) {
            DailyJournalLine::create([
                'daily_journal_id'      => $journal->id,
                'item_id'               => $item->id,
                'restock_batch_item_id' => $batchItem->id,
                'direction'             => 'out',
                'quantity'              => $qty,
            ]);
        }
    }

    // ── item stock tests ────────────────────────────────────

    public function test_item_stock_is_zero_with_no_journal_lines(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        $this->assertEquals(0.0, StockLedger::itemStockAsOf($item->id, '2026-02-26'));
        $this->assertEmpty(StockLedger::itemStocksAsOf('2026-02-26'));
    }

    public function test_item_stock_after_single_restock(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        $this->restockItem($item, 20, '2026-02-25');

        $this->assertEquals(20.0, StockLedger::itemStockAsOf($item->id, '2026-02-25'));
    }

    public function test_item_stock_after_restock_and_consume(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        $bi = $this->restockItem($item, 20, '2026-02-25');
        $this->consumeItem($item, [[$bi, 7]], '2026-02-25');

        $this->assertEquals(13.0, StockLedger::itemStockAsOf($item->id, '2026-02-25'));
    }

    public function test_item_stocks_as_of_returns_all_items(): void
    {
        $unit  = $this->createUnit();
        $item1 = $this->createItem($unit, ['name' => 'Flour']);
        $item2 = $this->createItem($unit, ['name' => 'Sugar', 'cost_per_unit' => 60]);

        $this->restockItem($item1, 10, '2026-02-25');
        $this->restockItem($item2, 5, '2026-02-25');

        $stocks = StockLedger::itemStocksAsOf('2026-02-25');

        $this->assertEquals(10.0, $stocks[$item1->id]);
        $this->assertEquals(5.0, $stocks[$item2->id]);
    }

    // ── time-travel scoping ─────────────────────────────────

    public function test_stock_is_date_scoped(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        $this->restockItem($item, 10, '2026-02-24');
        $this->restockItem($item, 5, '2026-02-26');

        // Day before first restock: nothing
        $this->assertEquals(0.0, StockLedger::itemStockAsOf($item->id, '2026-02-23'));

        // Day of first restock: 10
        $this->assertEquals(10.0, StockLedger::itemStockAsOf($item->id, '2026-02-24'));

        // Day between restocks: still 10
        $this->assertEquals(10.0, StockLedger::itemStockAsOf($item->id, '2026-02-25'));

        // Day of second restock: 15
        $this->assertEquals(15.0, StockLedger::itemStockAsOf($item->id, '2026-02-26'));
    }

    public function test_consume_is_date_scoped(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        $bi = $this->restockItem($item, 20, '2026-02-24');
        $this->consumeItem($item, [[$bi, 8]], '2026-02-26');

        // Before consume: full stock
        $this->assertEquals(20.0, StockLedger::itemStockAsOf($item->id, '2026-02-25'));

        // On consume date: reduced
        $this->assertEquals(12.0, StockLedger::itemStockAsOf($item->id, '2026-02-26'));
    }

    // ── batch balances ──────────────────────────────────────

    public function test_batch_balances_as_of(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        $bi1 = $this->restockItem($item, 10, '2026-02-24');
        $bi2 = $this->restockItem($item, 5, '2026-02-24');

        $this->consumeItem($item, [[$bi1, 3]], '2026-02-24');

        $balances = StockLedger::batchBalancesAsOf('2026-02-24');

        $this->assertEquals(7.0, $balances[$bi1->id]);
        $this->assertEquals(5.0, $balances[$bi2->id]);
    }

    public function test_batch_balances_for_item_as_of(): void
    {
        $unit  = $this->createUnit();
        $item1 = $this->createItem($unit, ['name' => 'Flour']);
        $item2 = $this->createItem($unit, ['name' => 'Sugar']);

        $bi1 = $this->restockItem($item1, 10, '2026-02-24');
        $bi2 = $this->restockItem($item2, 5, '2026-02-24');

        $balances = StockLedger::batchBalancesForItemAsOf($item1->id, '2026-02-24');

        $this->assertArrayHasKey($bi1->id, $balances);
        $this->assertArrayNotHasKey($bi2->id, $balances);
        $this->assertEquals(10.0, $balances[$bi1->id]);
    }

    public function test_batch_balance_never_negative(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        $bi = $this->restockItem($item, 5, '2026-02-24');
        // over-consume (shouldn't normally happen, but ledger clamps to 0)
        $this->consumeItem($item, [[$bi, 10]], '2026-02-24');

        $balances = StockLedger::batchBalancesAsOf('2026-02-24');

        $this->assertEquals(0.0, $balances[$bi->id]);
    }

    // ── product stock ───────────────────────────────────────

    public function test_product_stock_from_production(): void
    {
        $unit    = $this->createUnit();
        $item    = $this->createItem($unit);
        $product = Product::create([
            'name'              => 'Cake',
            'selling_price'     => 0,
            'markup_percentage' => 0,
        ]);

        // Produce 3 cakes on that date
        $journal = DailyJournal::create([
            'journal_date' => '2026-02-25',
            'kind'         => 'produce',
            'notes'        => null,
        ]);

        DailyJournalLine::create([
            'daily_journal_id' => $journal->id,
            'product_id'       => $product->id,
            'direction'        => 'in',
            'quantity'         => 3,
        ]);

        $stocks = StockLedger::productStocksAsOf('2026-02-25');
        $this->assertEquals(3.0, $stocks[$product->id]);

        // Not visible the day before
        $this->assertEmpty(StockLedger::productStocksAsOf('2026-02-24'));
    }

    // ── hydration helpers ───────────────────────────────────

    public function test_hydrate_items_sets_current_stock(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        $this->restockItem($item, 15, '2026-02-25');

        $items = Item::with('restockBatchItems.batch')->get();
        StockLedger::hydrateItems($items, '2026-02-25');

        $this->assertEquals(15.0, $items->first()->current_stock);
    }

    public function test_hydrate_items_prunes_zero_balance_batches(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        $bi = $this->restockItem($item, 5, '2026-02-25');
        $this->consumeItem($item, [[$bi, 5]], '2026-02-25');

        $items = Item::with('restockBatchItems.batch')->get();
        StockLedger::hydrateItems($items, '2026-02-25');

        $this->assertEquals(0.0, $items->first()->current_stock);
        $this->assertCount(0, $items->first()->restockBatchItems);
    }

    public function test_hydrate_products_sets_current_stock(): void
    {
        $product = Product::create([
            'name'              => 'Cake',
            'selling_price'     => 0,
            'markup_percentage' => 0,
        ]);

        $journal = DailyJournal::create([
            'journal_date' => '2026-02-25',
            'kind'         => 'produce',
            'notes'        => null,
        ]);

        DailyJournalLine::create([
            'daily_journal_id' => $journal->id,
            'product_id'       => $product->id,
            'direction'        => 'in',
            'quantity'         => 7,
        ]);

        $products = Product::all();
        StockLedger::hydrateProducts($products, '2026-02-25');

        $this->assertEquals(7.0, $products->first()->current_stock);
    }

    // ── multiple operations composing ───────────────────────

    public function test_multiple_restocks_and_consumes_across_dates(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        $bi1 = $this->restockItem($item, 10, '2026-02-20');
        $bi2 = $this->restockItem($item, 5, '2026-02-22');

        $this->consumeItem($item, [[$bi1, 4]], '2026-02-21');
        $this->consumeItem($item, [[$bi1, 3], [$bi2, 2]], '2026-02-23');

        // 2026-02-20: +10 = 10
        $this->assertEquals(10.0, StockLedger::itemStockAsOf($item->id, '2026-02-20'));

        // 2026-02-21: +10 - 4 = 6
        $this->assertEquals(6.0, StockLedger::itemStockAsOf($item->id, '2026-02-21'));

        // 2026-02-22: +10 + 5 - 4 = 11
        $this->assertEquals(11.0, StockLedger::itemStockAsOf($item->id, '2026-02-22'));

        // 2026-02-23: +10 + 5 - 4 - 3 - 2 = 6
        $this->assertEquals(6.0, StockLedger::itemStockAsOf($item->id, '2026-02-23'));

        // Batch balances at end
        $balances = StockLedger::batchBalancesAsOf('2026-02-23');
        $this->assertEquals(3.0, $balances[$bi1->id]); // 10 - 4 - 3
        $this->assertEquals(3.0, $balances[$bi2->id]); // 5 - 2
    }
}
