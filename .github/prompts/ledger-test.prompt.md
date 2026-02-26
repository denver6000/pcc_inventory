# Ledger Test Fixture Pattern

Use this when writing any PHPUnit test that involves stock quantities.

## Rules
- Extend `Tests\TestCase`, use `RefreshDatabase` — every test runs against fresh `:memory:` SQLite.
- **Never** set `current_stock` directly on a model. Stock must come from `DailyJournalLine` rows.
- No model factories exist (except `UserFactory`). Create records directly via models.
- Assert stock via `StockLedger::itemStockAsOf()` / `StockLedger::batchBalancesAsOf()` — never read `current_stock` from DB in assertions.

## Minimal fixture helpers (copy into test class)

```php
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
        'current_stock' => 0,   // vestigial — ledger is the truth
    ]);
}
```

## Adding stock via a restock journal (the only correct way)

```php
private function restockItem(Item $item, float $qty, string $date): RestockBatchItem
{
    $journal = DailyJournal::create([
        'journal_date' => $date,
        'kind'         => 'restock',
    ]);

    $batch = RestockBatch::create([
        'batch_code' => 'RST-TEST-001',
        'journal_id' => $journal->id,
        'total_cost' => round($qty * $item->cost_per_unit, 4),
    ]);

    $batchItem = $batch->items()->create([
        'item_id'        => $item->id,
        'quantity_added' => $qty,
        'cost_per_unit'  => $item->cost_per_unit,
        'subtotal'       => round($qty * $item->cost_per_unit, 4),
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
```

## Standard assertion flow

```php
// 1. Build fixtures
$unit = $this->createUnit();
$item = $this->createItem($unit);
$bi   = $this->restockItem($item, 10.0, '2026-02-26');

// 2. Assert item stock
$this->assertEquals(10.0, StockLedger::itemStockAsOf($item->id, '2026-02-26'));

// 3. Assert batch balance
$balances = StockLedger::batchBalancesAsOf('2026-02-26');
$this->assertEquals(10.0, $balances[$bi->id]);

// 4. Write an out line and re-assert
DailyJournalLine::create([
    'daily_journal_id'      => DailyJournal::create(['journal_date' => '2026-02-26', 'kind' => 'consume'])->id,
    'item_id'               => $item->id,
    'restock_batch_item_id' => $bi->id,
    'direction'             => 'out',
    'quantity'              => 3.0,
]);
$this->assertEquals(7.0, StockLedger::itemStockAsOf($item->id, '2026-02-26'));
```

## Time-travel pattern

```php
$bi = $this->restockItem($item, 10.0, '2026-02-26');

// Stock does NOT exist before the journal date
$this->assertEquals(0.0, StockLedger::itemStockAsOf($item->id, '2026-02-25'));
// Stock exists on and after
$this->assertEquals(10.0, StockLedger::itemStockAsOf($item->id, '2026-02-26'));
$this->assertEquals(10.0, StockLedger::itemStockAsOf($item->id, '2026-02-27'));
```

## Controller / Inertia test pattern

```php
// GET — assert Inertia component and props
$this->get('/restock?date=2026-02-26')
    ->assertOk()
    ->assertInertia(fn ($page) => $page
        ->component('Restock/Index')
        ->has('batches', 1)
        ->has('items')
    );

// POST mutation — assert redirect back
$this->post('/restock', [
    'journal_date' => '2026-02-26',
    'items' => [['item_id' => $item->id, 'quantity_added' => 5]],
])->assertRedirect();

// After mutation, verify via ledger
$this->assertEquals(5.0, StockLedger::itemStockAsOf($item->id, '2026-02-26'));
```

## What NOT to test
- `CheckoutController`, `PosController`, `TimelineController` — unrouted dormant code.
- The `current_stock` column value in the DB — it's a display cache, not the truth.
