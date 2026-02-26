<?php

namespace Tests\Feature;

use App\Models\DailyJournal;
use App\Models\DailyJournalLine;
use App\Models\Item;
use App\Models\Product;
use App\Models\RestockBatch;
use App\Models\RestockBatchItem;
use App\Models\Unit;
use App\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeTravelTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ──────────────────────────────────────────────

    private function createUnit(): Unit
    {
        return Unit::create(['name' => 'Kilogram', 'abbreviation' => 'kg']);
    }

    private function createItem(Unit $unit, string $name = 'Flour', float $cpu = 50): Item
    {
        return Item::create([
            'name'          => $name,
            'unit_id'       => $unit->id,
            'cost_per_unit' => $cpu,
            'default_stock' => 0,
            'current_stock' => 0,
        ]);
    }

    private function restockItem(Item $item, float $qty, string $date): RestockBatchItem
    {
        $journal = DailyJournal::create([
            'journal_date' => $date,
            'kind'         => 'restock',
            'notes'        => null,
        ]);

        $batch = RestockBatch::create([
            'batch_code' => 'RST-TEST-' . $item->id . '-' . uniqid(),
            'journal_id' => $journal->id,
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

    private function produceProduct(Product $product, int $qty, string $date): void
    {
        $journal = DailyJournal::create([
            'journal_date' => $date,
            'kind'         => 'produce',
            'notes'        => null,
        ]);

        // Out lines per ingredient
        $balances = StockLedger::batchBalancesAsOf($date);
        foreach ($product->ingredients as $ing) {
            $needed = round($ing->quantity * $qty, 4);
            $batchItems = RestockBatchItem::where('item_id', $ing->item_id)
                ->orderBy('created_at')->get();

            $remaining = $needed;
            foreach ($batchItems as $bi) {
                if ($remaining <= 0) break;
                $balance = $balances[$bi->id] ?? 0.0;
                if ($balance <= 0) continue;
                $take = min($remaining, $balance);

                DailyJournalLine::create([
                    'daily_journal_id'      => $journal->id,
                    'item_id'               => $ing->item_id,
                    'product_id'            => $product->id,
                    'restock_batch_item_id' => $bi->id,
                    'direction'             => 'out',
                    'quantity'              => $take,
                ]);

                $remaining -= $take;
            }
        }

        // In line for product
        DailyJournalLine::create([
            'daily_journal_id' => $journal->id,
            'product_id'       => $product->id,
            'direction'        => 'in',
            'quantity'         => $qty,
        ]);
    }

    // ── middleware shares currentDate ────────────────────────

    public function test_middleware_shares_current_date_from_query_param(): void
    {
        $this->get('/?date=2026-02-20')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('currentDate', '2026-02-20')
            );
    }

    public function test_middleware_defaults_current_date_to_today(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('currentDate', now()->toDateString())
            );
    }

    public function test_middleware_shares_date_on_all_pages(): void
    {
        foreach (['/', '/products', '/produce', '/restock', '/units'] as $url) {
            $this->get($url . '?date=2026-02-18')
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('currentDate', '2026-02-18')
                );
        }
    }

    // ── items index: stock consolidation by date ────────────

    public function test_items_index_consolidates_multiple_restocks_up_to_date(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        // Restock 10 on Feb 20, 5 on Feb 22, 8 on Feb 24
        $this->restockItem($item, 10, '2026-02-20');
        $this->restockItem($item, 5, '2026-02-22');
        $this->restockItem($item, 8, '2026-02-24');

        // On Feb 21: only the first restock is visible → 10
        $this->get('/?date=2026-02-21')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.current_stock', fn ($v) => (float) $v === 10.0)
            );

        // On Feb 23: first two restocks → 15
        $this->get('/?date=2026-02-23')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.current_stock', fn ($v) => (float) $v === 15.0)
            );

        // On Feb 25: all three → 23
        $this->get('/?date=2026-02-25')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.current_stock', fn ($v) => (float) $v === 23.0)
            );
    }

    public function test_items_index_consolidates_restocks_and_consumes_by_date(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        $bi = $this->restockItem($item, 20, '2026-02-20');
        $this->consumeItem($item, [[$bi, 7]], '2026-02-22');

        // Before consume: full stock
        $this->get('/?date=2026-02-21')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.current_stock', fn ($v) => (float) $v === 20.0)
            );

        // On consume date: reduced
        $this->get('/?date=2026-02-22')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.current_stock', fn ($v) => (float) $v === 13.0)
            );
    }

    public function test_items_index_before_any_restock_shows_zero(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        $this->restockItem($item, 15, '2026-02-20');

        // Day before the restock → zero
        $this->get('/?date=2026-02-19')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.current_stock', fn ($v) => (float) $v === 0.0)
            );
    }

    // ── items index: batch items pruned by date ─────────────

    public function test_items_index_hides_depleted_batches_at_viewed_date(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        $bi = $this->restockItem($item, 5, '2026-02-20');
        $this->consumeItem($item, [[$bi, 5]], '2026-02-22');

        // Before consume: batch still visible
        $this->get('/?date=2026-02-21')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.current_stock', fn ($v) => (float) $v === 5.0)
                ->has('items.0.restock_batch_items', 1)
            );

        // After consume: zero balance → batch pruned from response
        $this->get('/?date=2026-02-22')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.current_stock', fn ($v) => (float) $v === 0.0)
                ->has('items.0.restock_batch_items', 0)
            );
    }

    // ── products index: stock consolidation by date ─────────

    public function test_products_index_shows_product_stock_scoped_by_date(): void
    {
        $unit  = $this->createUnit();
        $flour = $this->createItem($unit, 'Flour', 50);

        $this->restockItem($flour, 100, '2026-02-18');

        $product = Product::create([
            'name' => 'Bread', 'selling_price' => 0, 'markup_percentage' => 0,
        ]);
        $product->ingredients()->create(['item_id' => $flour->id, 'quantity' => 2]);

        // Produce 5 on Feb 20
        $this->produceProduct($product, 5, '2026-02-20');

        // Before production: zero product stock
        $this->get('/products?date=2026-02-19')
            ->assertInertia(fn ($page) => $page
                ->where('products.0.current_stock', fn ($v) => (float) $v === 0.0)
            );

        // On production date: 5
        $this->get('/products?date=2026-02-20')
            ->assertInertia(fn ($page) => $page
                ->where('products.0.current_stock', fn ($v) => (float) $v === 5.0)
            );
    }

    // ── restock index: batch visibility by date ─────────────

    public function test_restock_index_consolidates_batches_up_to_date(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        // Two restocks on different days
        $this->restockItem($item, 10, '2026-02-20');
        $this->restockItem($item, 5, '2026-02-23');

        // Viewing Feb 21: only first batch
        $this->get('/restock?date=2026-02-21')
            ->assertInertia(fn ($page) => $page->has('batches', 1));

        // Viewing Feb 23: both batches
        $this->get('/restock?date=2026-02-23')
            ->assertInertia(fn ($page) => $page->has('batches', 2));

        // Viewing Feb 19: none
        $this->get('/restock?date=2026-02-19')
            ->assertInertia(fn ($page) => $page->has('batches', 0));
    }

    public function test_restock_index_shows_remaining_qty_scoped_by_date(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        $bi = $this->restockItem($item, 20, '2026-02-20');
        $this->consumeItem($item, [[$bi, 8]], '2026-02-22');

        // On Feb 21 (before consume): batch shows full 20
        $this->get('/restock?date=2026-02-21')
            ->assertInertia(fn ($page) => $page
                ->where('batches.0.items.0.quantity_added', fn ($v) => (float) $v === 20.0)
            );

        // On Feb 22 (after consume): batch shows 12
        $this->get('/restock?date=2026-02-22')
            ->assertInertia(fn ($page) => $page
                ->where('batches.0.items.0.quantity_added', fn ($v) => (float) $v === 12.0)
            );
    }

    // ── produce index: ingredient availability by date ──────

    public function test_produce_index_shows_ingredient_stock_scoped_by_date(): void
    {
        $unit  = $this->createUnit();
        $flour = $this->createItem($unit, 'Flour', 50);

        $this->restockItem($flour, 30, '2026-02-20');

        $product = Product::create([
            'name' => 'Bread', 'selling_price' => 0, 'markup_percentage' => 0,
        ]);
        $product->ingredients()->create(['item_id' => $flour->id, 'quantity' => 2]);

        // Before restock: ingredient shows 0
        $this->get('/produce?date=2026-02-19')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('products.0.ingredients.0.item.current_stock', fn ($v) => (float) $v === 0.0)
            );

        // After restock: ingredient shows 30
        $this->get('/produce?date=2026-02-20')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('products.0.ingredients.0.item.current_stock', fn ($v) => (float) $v === 30.0)
            );
    }

    // ── store with journal_date: restock ────────────────────

    public function test_restock_store_respects_journal_date_for_visibility(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        // Post a restock dated Feb 25
        $this->post('/restock', [
            'journal_date' => '2026-02-25',
            'items'        => [
                ['item_id' => $item->id, 'quantity_added' => 10],
            ],
        ])->assertRedirect();

        // Visible on Feb 25
        $this->assertEquals(10.0, StockLedger::itemStockAsOf($item->id, '2026-02-25'));

        // Not visible on Feb 24
        $this->assertEquals(0.0, StockLedger::itemStockAsOf($item->id, '2026-02-24'));

        // Items page on Feb 24 shows zero
        $this->get('/?date=2026-02-24')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.current_stock', fn ($v) => (float) $v === 0.0)
            );

        // Items page on Feb 25 shows 10
        $this->get('/?date=2026-02-25')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.current_stock', fn ($v) => (float) $v === 10.0)
            );
    }

    // ── store with journal_date: produce ────────────────────

    public function test_produce_store_respects_journal_date_for_scoping(): void
    {
        $unit  = $this->createUnit();
        $flour = $this->createItem($unit, 'Flour', 50);

        $this->restockItem($flour, 50, '2026-02-18');

        $product = Product::create([
            'name' => 'Bread', 'selling_price' => 0, 'markup_percentage' => 0,
        ]);
        $product->ingredients()->create(['item_id' => $flour->id, 'quantity' => 5]);

        // Produce 3 breads dated Feb 22
        $this->post('/produce', [
            'product_id'   => $product->id,
            'quantity'     => 3,
            'journal_date' => '2026-02-22',
        ])->assertRedirect();

        // Feb 21: flour still full, no product stock
        $this->assertEquals(50.0, StockLedger::itemStockAsOf($flour->id, '2026-02-21'));
        $this->assertEmpty(StockLedger::productStocksAsOf('2026-02-21'));

        // Feb 22: flour reduced by 15, product stock = 3
        $this->assertEquals(35.0, StockLedger::itemStockAsOf($flour->id, '2026-02-22'));
        $pStocks = StockLedger::productStocksAsOf('2026-02-22');
        $this->assertEquals(3.0, $pStocks[$product->id]);
    }

    // ── store with journal_date: consume ────────────────────

    public function test_consume_store_respects_journal_date_for_scoping(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        $bi = $this->restockItem($item, 20, '2026-02-18');

        // Consume 6 dated Feb 22
        $this->post("/items/{$item->id}/consume", [
            'quantity'     => 6,
            'batch_order'  => [$bi->id],
            'journal_date' => '2026-02-22',
        ])->assertRedirect();

        // Feb 21: still full
        $this->assertEquals(20.0, StockLedger::itemStockAsOf($item->id, '2026-02-21'));

        // Feb 22: reduced
        $this->assertEquals(14.0, StockLedger::itemStockAsOf($item->id, '2026-02-22'));
    }

    // ── store with journal_date: item initial stock ─────────

    public function test_item_store_initial_stock_uses_journal_date(): void
    {
        $unit = $this->createUnit();

        $this->post('/items', [
            'name'          => 'Butter',
            'unit_id'       => $unit->id,
            'cost_per_unit' => 100,
            'default_stock' => 15,
            'journal_date'  => '2026-02-20',
        ])->assertRedirect();

        $item = Item::where('name', 'Butter')->first();

        // Visible from Feb 20 onwards
        $this->assertEquals(15.0, StockLedger::itemStockAsOf($item->id, '2026-02-20'));

        // Not visible before
        $this->assertEquals(0.0, StockLedger::itemStockAsOf($item->id, '2026-02-19'));
    }

    // ── production blocked by date-scoped ingredient stock ──

    public function test_produce_rejects_when_ingredients_not_yet_restocked_at_date(): void
    {
        $unit  = $this->createUnit();
        $flour = $this->createItem($unit, 'Flour', 50);

        // Restock on Feb 25
        $this->restockItem($flour, 50, '2026-02-25');

        $product = Product::create([
            'name' => 'Bread', 'selling_price' => 0, 'markup_percentage' => 0,
        ]);
        $product->ingredients()->create(['item_id' => $flour->id, 'quantity' => 2]);

        // Try to produce dated Feb 24 — flour not restocked yet at that date
        $response = $this->post('/produce', [
            'product_id'   => $product->id,
            'quantity'     => 1,
            'journal_date' => '2026-02-24',
        ]);

        $response->assertSessionHasErrors('produce');

        // Nothing was created
        $this->assertEmpty(StockLedger::productStocksAsOf('2026-02-24'));
        $this->assertEquals(0.0, StockLedger::itemStockAsOf($flour->id, '2026-02-24'));
    }

    // ── consume blocked by date-scoped batch balance ────────

    public function test_consume_rejects_when_batch_not_yet_available_at_date(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        // Restock on Feb 25
        $bi = $this->restockItem($item, 10, '2026-02-25');

        // Try to consume dated Feb 24 — batch not available yet
        $response = $this->post("/items/{$item->id}/consume", [
            'quantity'     => 1,
            'batch_order'  => [$bi->id],
            'journal_date' => '2026-02-24',
        ]);

        $response->assertSessionHasErrors('quantity');
    }

    // ── full multi-day scenario ─────────────────────────────

    public function test_full_multi_day_scenario_consolidation(): void
    {
        $unit  = $this->createUnit();
        $flour = $this->createItem($unit, 'Flour', 50);
        $sugar = $this->createItem($unit, 'Sugar', 60);

        // Day 1 (Feb 20): Restock flour=30, sugar=20
        $bFlour1 = $this->restockItem($flour, 30, '2026-02-20');
        $bSugar1 = $this->restockItem($sugar, 20, '2026-02-20');

        // Day 2 (Feb 21): Consume 5 flour
        $this->consumeItem($flour, [[$bFlour1, 5]], '2026-02-21');

        // Day 3 (Feb 22): Restock more flour=10
        $bFlour2 = $this->restockItem($flour, 10, '2026-02-22');

        // Day 4 (Feb 23): Produce cakes (uses both ingredients)
        $product = Product::create([
            'name' => 'Cake', 'selling_price' => 0, 'markup_percentage' => 0,
        ]);
        $product->ingredients()->create(['item_id' => $flour->id, 'quantity' => 3]);
        $product->ingredients()->create(['item_id' => $sugar->id, 'quantity' => 2]);

        $this->post('/produce', [
            'product_id'   => $product->id,
            'quantity'     => 4,
            'journal_date' => '2026-02-23',
        ])->assertRedirect();

        // ── Verify state at each date ──

        // Feb 19: nothing
        $this->assertEquals(0.0, StockLedger::itemStockAsOf($flour->id, '2026-02-19'));
        $this->assertEquals(0.0, StockLedger::itemStockAsOf($sugar->id, '2026-02-19'));

        // Feb 20: restocks only → flour=30, sugar=20
        $this->assertEquals(30.0, StockLedger::itemStockAsOf($flour->id, '2026-02-20'));
        $this->assertEquals(20.0, StockLedger::itemStockAsOf($sugar->id, '2026-02-20'));

        // Feb 21: after consume → flour=25, sugar=20
        $this->assertEquals(25.0, StockLedger::itemStockAsOf($flour->id, '2026-02-21'));
        $this->assertEquals(20.0, StockLedger::itemStockAsOf($sugar->id, '2026-02-21'));

        // Feb 22: second restock → flour=35, sugar=20
        $this->assertEquals(35.0, StockLedger::itemStockAsOf($flour->id, '2026-02-22'));
        $this->assertEquals(20.0, StockLedger::itemStockAsOf($sugar->id, '2026-02-22'));

        // Feb 23: after production (4 cakes = 12 flour, 8 sugar out)
        // flour: 35 - 12 = 23, sugar: 20 - 8 = 12, product: 4
        $this->assertEquals(23.0, StockLedger::itemStockAsOf($flour->id, '2026-02-23'));
        $this->assertEquals(12.0, StockLedger::itemStockAsOf($sugar->id, '2026-02-23'));
        $pStocks = StockLedger::productStocksAsOf('2026-02-23');
        $this->assertEquals(4.0, $pStocks[$product->id]);

        // Product stock not visible before production
        $this->assertEmpty(StockLedger::productStocksAsOf('2026-02-22'));
    }

    public function test_full_scenario_viewed_through_pages(): void
    {
        $unit  = $this->createUnit();
        $flour = $this->createItem($unit, 'Flour', 50);

        // Restock 20 on Feb 20
        $bi = $this->restockItem($flour, 20, '2026-02-20');

        // Consume 8 on Feb 22
        $this->consumeItem($flour, [[$bi, 8]], '2026-02-22');

        // Restock 5 more on Feb 24
        $this->restockItem($flour, 5, '2026-02-24');

        // Items page at each date
        // Feb 19: 0
        $this->get('/?date=2026-02-19')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.current_stock', fn ($v) => (float) $v === 0.0)
            );

        // Feb 20: 20
        $this->get('/?date=2026-02-20')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.current_stock', fn ($v) => (float) $v === 20.0)
            );

        // Feb 21: still 20 (no ops on this day)
        $this->get('/?date=2026-02-21')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.current_stock', fn ($v) => (float) $v === 20.0)
            );

        // Feb 22: 12 (consumed 8)
        $this->get('/?date=2026-02-22')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.current_stock', fn ($v) => (float) $v === 12.0)
            );

        // Feb 23: still 12
        $this->get('/?date=2026-02-23')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.current_stock', fn ($v) => (float) $v === 12.0)
            );

        // Feb 24: 17 (restocked 5 more)
        $this->get('/?date=2026-02-24')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.current_stock', fn ($v) => (float) $v === 17.0)
            );

        // Restock page: batch count grows over time
        $this->get('/restock?date=2026-02-19')
            ->assertInertia(fn ($page) => $page->has('batches', 0));

        $this->get('/restock?date=2026-02-20')
            ->assertInertia(fn ($page) => $page->has('batches', 1));

        $this->get('/restock?date=2026-02-24')
            ->assertInertia(fn ($page) => $page->has('batches', 2));
    }

    // ── batch balances consolidate date-scoped operations ───

    public function test_batch_balances_reflect_operations_up_to_viewed_date(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        $bi1 = $this->restockItem($item, 10, '2026-02-20');
        $bi2 = $this->restockItem($item, 8, '2026-02-22');

        // Consume 6 from bi1 on Feb 21
        $this->consumeItem($item, [[$bi1, 6]], '2026-02-21');

        // Consume 3 from bi2 on Feb 23
        $this->consumeItem($item, [[$bi2, 3]], '2026-02-23');

        // Feb 20: bi1=10, bi2 not yet visible
        $bal = StockLedger::batchBalancesAsOf('2026-02-20');
        $this->assertEquals(10.0, $bal[$bi1->id]);
        $this->assertArrayNotHasKey($bi2->id, $bal);

        // Feb 21: bi1=4
        $bal = StockLedger::batchBalancesAsOf('2026-02-21');
        $this->assertEquals(4.0, $bal[$bi1->id]);

        // Feb 22: bi1=4, bi2=8
        $bal = StockLedger::batchBalancesAsOf('2026-02-22');
        $this->assertEquals(4.0, $bal[$bi1->id]);
        $this->assertEquals(8.0, $bal[$bi2->id]);

        // Feb 23: bi1=4, bi2=5
        $bal = StockLedger::batchBalancesAsOf('2026-02-23');
        $this->assertEquals(4.0, $bal[$bi1->id]);
        $this->assertEquals(5.0, $bal[$bi2->id]);
    }

    // ── operations on the same date cumulate ────────────────

    public function test_multiple_operations_on_same_date_cumulate(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        // Two restocks on the same date
        $bi1 = $this->restockItem($item, 10, '2026-02-20');
        $bi2 = $this->restockItem($item, 5, '2026-02-20');

        // Two consumes on the same date
        $this->consumeItem($item, [[$bi1, 3]], '2026-02-20');
        $this->consumeItem($item, [[$bi2, 2]], '2026-02-20');

        // All four operations visible on that date: 10 + 5 - 3 - 2 = 10
        $this->assertEquals(10.0, StockLedger::itemStockAsOf($item->id, '2026-02-20'));

        // Verify via items page
        $this->get('/?date=2026-02-20')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.current_stock', fn ($v) => (float) $v === 10.0)
            );
    }

    // ── backward date navigation shows historical state ─────

    public function test_navigating_backward_restores_past_state(): void
    {
        $unit  = $this->createUnit();
        $flour = $this->createItem($unit, 'Flour', 50);
        $sugar = $this->createItem($unit, 'Sugar', 60);

        $bFlour = $this->restockItem($flour, 50, '2026-02-18');
        $bSugar = $this->restockItem($sugar, 30, '2026-02-18');

        // Consume heavily on Feb 22
        $this->consumeItem($flour, [[$bFlour, 40]], '2026-02-22');
        $this->consumeItem($sugar, [[$bSugar, 25]], '2026-02-22');

        // Navigate to Feb 20 (before the consumption): full stock
        $this->get('/?date=2026-02-20')
            ->assertInertia(function ($page) {
                $items = collect($page->toArray()['props']['items']);
                $flour = $items->firstWhere('name', 'Flour');
                $sugar = $items->firstWhere('name', 'Sugar');

                $this->assertEquals(50.0, (float) $flour['current_stock']);
                $this->assertEquals(30.0, (float) $sugar['current_stock']);
            });

        // Navigate to Feb 22 (after consumption): depleted
        $this->get('/?date=2026-02-22')
            ->assertInertia(function ($page) {
                $items = collect($page->toArray()['props']['items']);
                $flour = $items->firstWhere('name', 'Flour');
                $sugar = $items->firstWhere('name', 'Sugar');

                $this->assertEquals(10.0, (float) $flour['current_stock']);
                $this->assertEquals(5.0, (float) $sugar['current_stock']);
            });

        // Navigate back to Feb 20 again: full stock restored
        $this->get('/?date=2026-02-20')
            ->assertInertia(function ($page) {
                $items = collect($page->toArray()['props']['items']);
                $flour = $items->firstWhere('name', 'Flour');

                $this->assertEquals(50.0, (float) $flour['current_stock']);
            });
    }
}
