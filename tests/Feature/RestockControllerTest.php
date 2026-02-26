<?php

namespace Tests\Feature;

use App\Models\DailyJournal;
use App\Models\DailyJournalLine;
use App\Models\Item;
use App\Models\RestockBatch;
use App\Models\RestockBatchItem;
use App\Models\Unit;
use App\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestockControllerTest extends TestCase
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

    // ── index ───────────────────────────────────────────────

    public function test_restock_index_renders_inertia_page(): void
    {
        $this->get('/restock')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Restock/Index')
                ->has('batches')
                ->has('items')
            );
    }

    public function test_restock_index_filters_batches_by_date(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        // Create a batch dated Feb 26
        $this->post('/restock', [
            'journal_date' => '2026-02-26',
            'items'        => [
                ['item_id' => $item->id, 'quantity_added' => 10],
            ],
        ]);

        // Viewing on Feb 25: batch not yet visible
        $this->get('/restock?date=2026-02-25')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Restock/Index')
                ->has('batches', 0)
            );

        // Viewing on Feb 26: batch visible
        $this->get('/restock?date=2026-02-26')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Restock/Index')
                ->has('batches', 1)
            );
    }

    // ── store ───────────────────────────────────────────────

    public function test_restock_store_creates_journal_batch_and_lines(): void
    {
        $unit  = $this->createUnit();
        $item1 = $this->createItem($unit, 'Flour', 50);
        $item2 = $this->createItem($unit, 'Sugar', 60);

        $response = $this->post('/restock', [
            'journal_date' => '2026-02-25',
            'notes'        => 'Weekly delivery',
            'items'        => [
                ['item_id' => $item1->id, 'quantity_added' => 10],
                ['item_id' => $item2->id, 'quantity_added' => 5],
            ],
        ]);

        $response->assertRedirect();

        // Journal was created
        $this->assertDatabaseHas('daily_journals', [
            'kind'  => 'restock',
            'notes' => 'Weekly delivery',
        ]);

        // Batch was created with computed total cost
        $this->assertDatabaseHas('restock_batches', [
            'total_cost' => 800.0, // (10*50) + (5*60)
        ]);

        // Two batch items
        $this->assertDatabaseCount('restock_batch_items', 2);

        // Two journal lines (both 'in')
        $this->assertDatabaseCount('daily_journal_lines', 2);
        $this->assertDatabaseHas('daily_journal_lines', [
            'item_id'   => $item1->id,
            'direction' => 'in',
            'quantity'  => 10,
        ]);
        $this->assertDatabaseHas('daily_journal_lines', [
            'item_id'   => $item2->id,
            'direction' => 'in',
            'quantity'  => 5,
        ]);

        // Ledger reflects the stock
        $this->assertEquals(10.0, StockLedger::itemStockAsOf($item1->id, '2026-02-25'));
        $this->assertEquals(5.0, StockLedger::itemStockAsOf($item2->id, '2026-02-25'));
    }

    public function test_restock_batch_code_follows_pattern(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit);

        $this->post('/restock', [
            'items' => [
                ['item_id' => $item->id, 'quantity_added' => 1],
            ],
        ]);

        $batch = RestockBatch::first();
        $this->assertMatchesRegularExpression('/^RST-\d{8}-\d{3}$/', $batch->batch_code);
    }

    public function test_restock_store_uses_item_cost_per_unit(): void
    {
        $unit = $this->createUnit();
        $item = $this->createItem($unit, 'Flour', 50);

        $this->post('/restock', [
            'items' => [
                ['item_id' => $item->id, 'quantity_added' => 10],
            ],
        ]);

        $batchItem = RestockBatchItem::first();
        $this->assertEquals(50.0, $batchItem->cost_per_unit);
        $this->assertEquals(500.0, $batchItem->subtotal);
    }

    // ── validation ──────────────────────────────────────────

    public function test_restock_store_requires_items(): void
    {
        $this->post('/restock', ['items' => []])
            ->assertSessionHasErrors('items');
    }

    public function test_restock_store_validates_item_fields(): void
    {
        $this->post('/restock', [
            'items' => [
                ['item_id' => 99999, 'quantity_added' => 10],
            ],
        ])->assertSessionHasErrors('items.0.item_id');
    }
}
