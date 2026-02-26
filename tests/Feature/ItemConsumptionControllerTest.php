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

class ItemConsumptionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUnit(): Unit
    {
        return Unit::create(['name' => 'Kilogram', 'abbreviation' => 'kg']);
    }

    /**
     * Create an item and restock it, returning [Item, RestockBatchItem].
     */
    private function createStockedItem(Unit $unit, float $qty = 20, string $date = '2026-02-25'): array
    {
        $item = Item::create([
            'name'          => 'Flour',
            'unit_id'       => $unit->id,
            'cost_per_unit' => 50,
            'default_stock' => 0,
            'current_stock' => 0,
        ]);

        $journal = DailyJournal::create([
            'journal_date' => $date,
            'kind'         => 'restock',
            'notes'        => null,
        ]);

        $batch = RestockBatch::create([
            'batch_code' => 'RST-TEST-' . $item->id,
            'journal_id' => $journal->id,
            'total_cost' => $qty * 50,
        ]);

        $batchItem = RestockBatchItem::create([
            'restock_batch_id' => $batch->id,
            'item_id'          => $item->id,
            'quantity_added'   => $qty,
            'cost_per_unit'    => 50,
            'subtotal'         => $qty * 50,
        ]);

        DailyJournalLine::create([
            'daily_journal_id'      => $journal->id,
            'item_id'               => $item->id,
            'restock_batch_item_id' => $batchItem->id,
            'direction'             => 'in',
            'quantity'              => $qty,
        ]);

        return [$item, $batchItem];
    }

    // ── consume happy path ──────────────────────────────────

    public function test_consume_depletes_stock_via_ledger(): void
    {
        $unit = $this->createUnit();
        [$item, $bi] = $this->createStockedItem($unit, 20, '2026-02-25');

        $response = $this->post("/items/{$item->id}/consume", [
            'quantity'     => 7,
            'batch_order'  => [$bi->id],
            'journal_date' => '2026-02-25',
        ]);

        $response->assertRedirect();

        // Ledger confirms depletion
        $this->assertEquals(13.0, StockLedger::itemStockAsOf($item->id, '2026-02-25'));

        // Batch balance also reflects it
        $balances = StockLedger::batchBalancesAsOf('2026-02-25');
        $this->assertEquals(13.0, $balances[$bi->id]);
    }

    public function test_consume_creates_journal_lines_not_mutations(): void
    {
        $unit = $this->createUnit();
        [$item, $bi] = $this->createStockedItem($unit, 20, '2026-02-25');

        $this->post("/items/{$item->id}/consume", [
            'quantity'     => 5,
            'batch_order'  => [$bi->id],
            'journal_date' => '2026-02-25',
        ]);

        // A consume journal was created
        $this->assertDatabaseHas('daily_journals', ['kind' => 'consume']);

        // An out line was appended
        $this->assertDatabaseHas('daily_journal_lines', [
            'item_id'               => $item->id,
            'restock_batch_item_id' => $bi->id,
            'direction'             => 'out',
            'quantity'              => 5,
        ]);

        // The restock_batch_items row was NOT mutated
        $bi->refresh();
        $this->assertEquals(20.0, $bi->quantity_added); // original value unchanged
    }

    // ── consume across multiple batches ─────────────────────

    public function test_consume_spreads_across_batches_in_order(): void
    {
        $unit = $this->createUnit();
        $item = Item::create([
            'name'          => 'Flour',
            'unit_id'       => $unit->id,
            'cost_per_unit' => 50,
            'default_stock' => 0,
            'current_stock' => 0,
        ]);

        // Two restock batches
        $journal1 = DailyJournal::create(['journal_date' => '2026-02-24', 'kind' => 'restock']);
        $batch1 = RestockBatch::create(['batch_code' => 'RST-A', 'journal_id' => $journal1->id, 'total_cost' => 500]);
        $bi1 = RestockBatchItem::create([
            'restock_batch_id' => $batch1->id, 'item_id' => $item->id,
            'quantity_added' => 10, 'cost_per_unit' => 50, 'subtotal' => 500,
        ]);
        DailyJournalLine::create([
            'daily_journal_id' => $journal1->id, 'item_id' => $item->id,
            'restock_batch_item_id' => $bi1->id, 'direction' => 'in', 'quantity' => 10,
        ]);

        $journal2 = DailyJournal::create(['journal_date' => '2026-02-24', 'kind' => 'restock']);
        $batch2 = RestockBatch::create(['batch_code' => 'RST-B', 'journal_id' => $journal2->id, 'total_cost' => 250]);
        $bi2 = RestockBatchItem::create([
            'restock_batch_id' => $batch2->id, 'item_id' => $item->id,
            'quantity_added' => 5, 'cost_per_unit' => 50, 'subtotal' => 250,
        ]);
        DailyJournalLine::create([
            'daily_journal_id' => $journal2->id, 'item_id' => $item->id,
            'restock_batch_item_id' => $bi2->id, 'direction' => 'in', 'quantity' => 5,
        ]);

        // Consume 12 across both batches (bi1 first, then bi2)
        $this->post("/items/{$item->id}/consume", [
            'quantity'     => 12,
            'batch_order'  => [$bi1->id, $bi2->id],
            'journal_date' => '2026-02-24',
        ])->assertRedirect();

        $balances = StockLedger::batchBalancesAsOf('2026-02-24');
        $this->assertEquals(0.0, $balances[$bi1->id]);  // 10 - 10 = 0
        $this->assertEquals(3.0, $balances[$bi2->id]);   // 5 - 2 = 3

        $this->assertEquals(3.0, StockLedger::itemStockAsOf($item->id, '2026-02-24'));
    }

    // ── consume validation ──────────────────────────────────

    public function test_consume_rejects_insufficient_stock(): void
    {
        $unit = $this->createUnit();
        [$item, $bi] = $this->createStockedItem($unit, 5, '2026-02-25');

        $response = $this->post("/items/{$item->id}/consume", [
            'quantity'     => 10, // more than available
            'batch_order'  => [$bi->id],
            'journal_date' => '2026-02-25',
        ]);

        $response->assertSessionHasErrors('quantity');

        // Stock unchanged
        $this->assertEquals(5.0, StockLedger::itemStockAsOf($item->id, '2026-02-25'));
    }

    public function test_consume_validates_required_fields(): void
    {
        $unit = $this->createUnit();
        [$item, $bi] = $this->createStockedItem($unit);

        $this->post("/items/{$item->id}/consume", [])
            ->assertSessionHasErrors(['quantity', 'batch_order']);
    }

    public function test_consume_rejects_invalid_batch_ids(): void
    {
        $unit = $this->createUnit();
        [$item, $bi] = $this->createStockedItem($unit);

        $this->post("/items/{$item->id}/consume", [
            'quantity'     => 1,
            'batch_order'  => [99999],
            'journal_date' => '2026-02-25',
        ])->assertSessionHasErrors('batch_order');
    }
}
