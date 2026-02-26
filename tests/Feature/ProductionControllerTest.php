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

class ProductionControllerTest extends TestCase
{
    use RefreshDatabase;

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

        DailyJournalLine::create([
            'daily_journal_id'      => $journal->id,
            'item_id'               => $item->id,
            'restock_batch_item_id' => $batchItem->id,
            'direction'             => 'in',
            'quantity'              => $qty,
        ]);

        return $batchItem;
    }

    // ── index ───────────────────────────────────────────────

    public function test_produce_index_renders_inertia_page(): void
    {
        $this->get('/produce')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Produce/Index')
                ->has('products')
            );
    }

    // ── store: happy path ───────────────────────────────────

    public function test_produce_depletes_ingredients_and_creates_product_stock(): void
    {
        $unit  = $this->createUnit();
        $flour = $this->createItem($unit, 'Flour', 50);
        $sugar = $this->createItem($unit, 'Sugar', 60);

        // Restock raw ingredients
        $this->restockItem($flour, 20, '2026-02-25');
        $this->restockItem($sugar, 10, '2026-02-25');

        // Product with recipe: 2 flour + 1 sugar per unit
        $product = Product::create([
            'name'              => 'Cake',
            'selling_price'     => 0,
            'markup_percentage' => 0,
        ]);
        $product->ingredients()->create(['item_id' => $flour->id, 'quantity' => 2]);
        $product->ingredients()->create(['item_id' => $sugar->id, 'quantity' => 1]);

        // Produce 3 cakes
        $response = $this->post('/produce', [
            'product_id'   => $product->id,
            'quantity'     => 3,
            'journal_date' => '2026-02-25',
        ]);

        $response->assertRedirect();

        // Flour: 20 - (2*3) = 14
        $this->assertEquals(14.0, StockLedger::itemStockAsOf($flour->id, '2026-02-25'));

        // Sugar: 10 - (1*3) = 7
        $this->assertEquals(7.0, StockLedger::itemStockAsOf($sugar->id, '2026-02-25'));

        // Product stock: +3
        $stocks = StockLedger::productStocksAsOf('2026-02-25');
        $this->assertEquals(3.0, $stocks[$product->id]);
    }

    public function test_produce_creates_proper_journal_entries(): void
    {
        $unit  = $this->createUnit();
        $flour = $this->createItem($unit, 'Flour', 50);

        $this->restockItem($flour, 20, '2026-02-25');

        $product = Product::create([
            'name'              => 'Bread',
            'selling_price'     => 0,
            'markup_percentage' => 0,
        ]);
        $product->ingredients()->create(['item_id' => $flour->id, 'quantity' => 3]);

        $this->post('/produce', [
            'product_id'   => $product->id,
            'quantity'     => 2,
            'journal_date' => '2026-02-25',
        ]);

        // A produce journal was created
        $this->assertDatabaseHas('daily_journals', ['kind' => 'produce']);

        // Out line for flour depletion (3*2 = 6)
        $this->assertDatabaseHas('daily_journal_lines', [
            'item_id'    => $flour->id,
            'product_id' => $product->id,
            'direction'  => 'out',
        ]);

        // In line for product gain
        $this->assertDatabaseHas('daily_journal_lines', [
            'product_id' => $product->id,
            'direction'  => 'in',
            'quantity'   => 2,
        ]);
    }

    // ── store: batch order ──────────────────────────────────

    public function test_produce_respects_user_batch_order(): void
    {
        $unit  = $this->createUnit();
        $flour = $this->createItem($unit, 'Flour', 50);

        $bi1 = $this->restockItem($flour, 5, '2026-02-24');
        $bi2 = $this->restockItem($flour, 10, '2026-02-24');

        $product = Product::create([
            'name'              => 'Bread',
            'selling_price'     => 0,
            'markup_percentage' => 0,
        ]);
        $product->ingredients()->create(['item_id' => $flour->id, 'quantity' => 4]);

        // Request batch_orders: use bi2 first (has 10), then bi1 (has 5)
        $this->post('/produce', [
            'product_id'   => $product->id,
            'quantity'     => 2, // needs 8 flour total
            'journal_date' => '2026-02-24',
            'batch_orders' => [
                $flour->id => [$bi2->id, $bi1->id],
            ],
        ])->assertRedirect();

        $balances = StockLedger::batchBalancesAsOf('2026-02-24');
        // bi2 should be depleted first: 10 - 8 = 2
        $this->assertEquals(2.0, $balances[$bi2->id]);
        // bi1 untouched
        $this->assertEquals(5.0, $balances[$bi1->id]);
    }

    // ── store: shortage validation ──────────────────────────

    public function test_produce_rejects_when_insufficient_stock(): void
    {
        $unit  = $this->createUnit();
        $flour = $this->createItem($unit, 'Flour', 50);

        $this->restockItem($flour, 5, '2026-02-25');

        $product = Product::create([
            'name'              => 'Cake',
            'selling_price'     => 0,
            'markup_percentage' => 0,
        ]);
        $product->ingredients()->create(['item_id' => $flour->id, 'quantity' => 3]);

        // Try to produce 3 cakes = needs 9 flour, only have 5
        $response = $this->post('/produce', [
            'product_id'   => $product->id,
            'quantity'     => 3,
            'journal_date' => '2026-02-25',
        ]);

        $response->assertSessionHasErrors('produce');

        // No product stock was created
        $this->assertEmpty(StockLedger::productStocksAsOf('2026-02-25'));

        // Flour stock unchanged
        $this->assertEquals(5.0, StockLedger::itemStockAsOf($flour->id, '2026-02-25'));
    }

    public function test_produce_rejects_product_with_no_recipe(): void
    {
        $product = Product::create([
            'name'              => 'Empty Product',
            'selling_price'     => 0,
            'markup_percentage' => 0,
        ]);

        $response = $this->post('/produce', [
            'product_id'   => $product->id,
            'quantity'     => 1,
            'journal_date' => '2026-02-25',
        ]);

        $response->assertSessionHasErrors('produce');
    }

    // ── store: validation ───────────────────────────────────

    public function test_produce_validates_required_fields(): void
    {
        $this->post('/produce', [])
            ->assertSessionHasErrors(['product_id', 'quantity']);
    }

    // ── time-travel: production is date-scoped ──────────────

    public function test_produce_stock_is_date_scoped(): void
    {
        $unit  = $this->createUnit();
        $flour = $this->createItem($unit, 'Flour', 50);

        $this->restockItem($flour, 20, '2026-02-24');

        $product = Product::create([
            'name'              => 'Cake',
            'selling_price'     => 0,
            'markup_percentage' => 0,
        ]);
        $product->ingredients()->create(['item_id' => $flour->id, 'quantity' => 2]);

        // Produce on Feb 26
        $this->post('/produce', [
            'product_id'   => $product->id,
            'quantity'     => 3,
            'journal_date' => '2026-02-26',
        ]);

        // Before production date: no product stock, flour still full
        $this->assertEmpty(StockLedger::productStocksAsOf('2026-02-25'));
        $this->assertEquals(20.0, StockLedger::itemStockAsOf($flour->id, '2026-02-25'));

        // On production date: product exists, flour reduced
        $stocks = StockLedger::productStocksAsOf('2026-02-26');
        $this->assertEquals(3.0, $stocks[$product->id]);
        $this->assertEquals(14.0, StockLedger::itemStockAsOf($flour->id, '2026-02-26'));
    }
}
