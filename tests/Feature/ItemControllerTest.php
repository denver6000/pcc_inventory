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

class ItemControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUnit(): Unit
    {
        return Unit::create(['name' => 'Kilogram', 'abbreviation' => 'kg']);
    }

    // ── index ───────────────────────────────────────────────

    public function test_items_index_returns_inertia_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Items/Index')
                ->has('items')
                ->has('units')
            );
    }

    public function test_items_index_respects_date_param(): void
    {
        $unit = $this->createUnit();

        // Create item with initial stock via the store endpoint
        $this->post('/items', [
            'name'          => 'Flour',
            'unit_id'       => $unit->id,
            'cost_per_unit' => 50,
            'default_stock' => 10,
            'journal_date'  => '2026-02-25',
        ]);

        $item = Item::first();

        // Stock visible on the restock date
        $this->get('/?date=2026-02-25')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Items/Index')
                ->where('items.0.current_stock', fn ($v) => (float) $v === 10.0)
            );

        // Stock NOT visible the day before
        $this->get('/?date=2026-02-24')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Items/Index')
                ->where('items.0.current_stock', fn ($v) => (float) $v === 0.0)
            );
    }

    // ── store ───────────────────────────────────────────────

    public function test_store_creates_item_and_initial_stock_via_ledger(): void
    {
        $unit = $this->createUnit();

        $response = $this->post('/items', [
            'name'          => 'Flour',
            'unit_id'       => $unit->id,
            'cost_per_unit' => 50,
            'default_stock' => 20,
            'journal_date'  => '2026-02-25',
        ]);

        $response->assertRedirect();

        // Item was created
        $this->assertDatabaseHas('items', ['name' => 'Flour']);

        $item = Item::where('name', 'Flour')->first();

        // Journal, batch and line were created
        $this->assertDatabaseHas('daily_journals', [
            'kind' => 'restock',
        ]);
        $this->assertDatabaseHas('daily_journal_lines', [
            'item_id'   => $item->id,
            'direction' => 'in',
            'quantity'  => 20,
        ]);
        $this->assertDatabaseHas('restock_batches', [
            'total_cost' => 1000.0, // 20 * 50
        ]);

        // Ledger confirms stock
        $this->assertEquals(20.0, StockLedger::itemStockAsOf($item->id, '2026-02-25'));
    }

    public function test_store_with_zero_default_stock_creates_no_journal(): void
    {
        $unit = $this->createUnit();

        $this->post('/items', [
            'name'          => 'Empty Item',
            'unit_id'       => $unit->id,
            'cost_per_unit' => 10,
            'default_stock' => 0,
        ]);

        $this->assertDatabaseHas('items', ['name' => 'Empty Item']);
        $this->assertDatabaseCount('daily_journals', 0);
        $this->assertDatabaseCount('daily_journal_lines', 0);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->post('/items', [])
            ->assertSessionHasErrors(['name', 'default_stock']);
    }

    // ── update ──────────────────────────────────────────────

    public function test_update_changes_item_metadata(): void
    {
        $unit = $this->createUnit();
        $item = Item::create([
            'name'          => 'Flour',
            'unit_id'       => $unit->id,
            'cost_per_unit' => 50,
            'default_stock' => 10,
            'current_stock' => 0,
        ]);

        $this->put("/items/{$item->id}", [
            'name'          => 'Whole Wheat Flour',
            'unit_id'       => $unit->id,
            'cost_per_unit' => 55,
            'default_stock' => 10,
        ])->assertRedirect();

        $item->refresh();
        $this->assertEquals('Whole Wheat Flour', $item->name);
        $this->assertEquals(55.0, $item->cost_per_unit);
    }

    // ── destroy ─────────────────────────────────────────────

    public function test_destroy_deletes_item(): void
    {
        $unit = $this->createUnit();
        $item = Item::create([
            'name'          => 'Flour',
            'unit_id'       => $unit->id,
            'cost_per_unit' => 50,
            'default_stock' => 10,
            'current_stock' => 0,
        ]);

        $this->delete("/items/{$item->id}")->assertRedirect();
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }
}
