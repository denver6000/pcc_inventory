# Produce Workflow

Reference: #file:app/Http/Controllers/ProductionController.php  
Reference: #file:app/Services/StockLedger.php

## What a produce run creates (in order, inside DB::transaction)

1. `DailyJournal` — `kind = 'produce'`, `journal_date`, optional `notes`
2. Pre-fetch `StockLedger::batchBalancesAsOf($journalDate)` — compute depletion plan first
3. **Shortage check** — throw `ValidationException` before writing any lines if any ingredient is short
4. For each ingredient → batch depletion:
   - One `DailyJournalLine` per batch line consumed: `direction = 'out'`, `item_id`, `product_id`, `restock_batch_item_id`, `quantity = take`
   - Deplete batches in user-specified order (`batch_orders[item_id]`) or FIFO (oldest `created_at`) fallback
5. One `DailyJournalLine` for the product gained: `direction = 'in'`, `product_id` only (no `item_id`, no `restock_batch_item_id`)

**Never** mutate `current_stock`, `quantity_added`, or any batch row quantity.

## Batch order resolution

```php
$requested = $batchOrders[$ing->item_id] ?? [];
$lineQuery = RestockBatchItem::where('item_id', $ing->item_id);

if (!empty($requested)) {
    $lines = $lineQuery->whereIn('id', $requested)->get();
    // validate no missing IDs
    $lines = $lines->sortBy(fn ($l) => array_search($l->id, $requested))->values();
} else {
    $lines = $lineQuery->orderBy('created_at')->get(); // FIFO fallback
}

// Attach ledger balance as transient attribute
foreach ($lines as $line) {
    $line->setAttribute('ledger_balance', $allBatchBalances[$line->id] ?? 0.0);
}
```

## Carry-over / Batch Configuration (frontend)

The Produce page is modularised into focused files under `resources/js/Pages/Produce/`:

| File | Purpose |
|---|---|
| `Index.jsx` | Slim orchestrator: product selection, form state, submission, layout composition |
| `helpers.js` | Pure utilities: `batchDate`, `sortByTimeline`, `fmtDate`, `currency` |
| `useBatchConfig.js` | Custom hook: all batch-config state, memos, DnD, auto-assign, reset |
| `BatchConfigPanel.jsx` | Batch Configuration card UI (per-ingredient cards with mode toggle) |
| `IngredientCheck.jsx` | Ingredient Check table (need / available / sourced / status) |
| `ProductCards.jsx` | Product selection card grid |

### Helpers (`helpers.js`)

```js
export const batchDate = (line) =>
    line.batch?.journal?.journal_date ?? line.batch?.created_at ?? line.created_at ?? '1970-01-01';
export const sortByTimeline = (a, b) => /* compare batchDate(a) vs batchDate(b) */;
```

All sort operations use `batchDate()` → `journal_date` (the timeline date) as primary key.
`created_at` is only a last-resort fallback inside `batchDate`.

### `useBatchConfig(product, quantity)` hook

Returns all batch-config state and functions. Called in `Index.jsx` and threaded to child components.

**Internal state:**

| State var | Shape | Purpose |
|---|---|---|
| `batchOrders` | `{ [itemId]: [{id, order}] }` | User-defined depletion sequence (sequential mode) |
| `batchModes` | `{ [itemId]: 'sequential' \| 'distributed' }` | Per-ingredient mode selection |
| `distributedAmounts` | `{ [itemId]: { [batchItemId]: number } }` | User-entered allocations (distributed mode) |

**Key memos:**

1. **`carryoverPlan`** — shape `{ [itemId]: { [batchItemId]: takeAmount } }`
   - Sequential: user order → `sortByTimeline` FIFO fallback, waterfall depletion
   - Distributed: user-entered amounts, clamped to `min(userAmt, available, needed − totalTaken)`
2. **`batchConfigData`** — extends carryoverPlan with running "still needed" waterfall per ingredient

**Exposed functions:**

| Function | Purpose |
|---|---|
| `modeFor(itemId)` / `setMode(itemId, mode)` | Get/set mode ('sequential' \| 'distributed') |
| `autoAssignIngredient(itemId)` | FIFO auto-assign for one ingredient (sequential) |
| `primeBatchOrders()` | FIFO-assign ALL ingredients of current product (sequential) |
| `autoDistributeEvenly(itemId)` | Spread needed amount evenly across batches (distributed) |
| `resetIngredient(itemId)` | Clear orders (sequential) or amounts (distributed) for one ingredient |
| `initForProduct(p)` | Full reset for a newly selected product (FIFO + sequential defaults) |
| `resetAll()` | Clear all batch config state |
| `handleDragStart/Enter/End` | HTML5 DnD handlers for sequential row reordering |
| `orderFor` / `setOrder` | Get/set manual order number for a batch line |
| `normalisedOrders()` | Build `batch_orders` payload for form submission |

### UI structure

The page layout is:
1. **Production Plan** (left, in `Index.jsx`) + **Ingredient Check** (right, `IngredientCheck.jsx`) — 2-column grid
2. **Batch Configuration** (`BatchConfigPanel.jsx`) — full-width card, only visible when a product is selected
3. **Products** (`ProductCards.jsx`) — product card grid for selection

Each ingredient in Batch Configuration gets:
- **Header:** name, fulfilled/unfulfilled badge, **mode toggle** (Sequential / Distributed pill), per-mode action button (↻ FIFO or ⚖ Even), **✕ Reset** button
- **Stats bar:** Need / Sourced / Shortfall (if any) + progress bar
- **Sequential table:** `⠿# | Batch | Date | In stock | → Take | Still needed | Cost/unit` — draggable rows
- **Distributed table:** `Batch | Date | In stock | Allocate [input, step=1] | Resolved | Cost/unit` — editable allocation inputs (stepper increments by 1, manual decimal entry allowed)

### Row highlight colours

| Colour | Condition | Meaning |
|---|---|---|
| `bg-rose-50` + rose pill | `take >= available − ε` | Batch **fully depleted** this run |
| `bg-amber-50` + amber pill | `0 < take < available` | Batch **partially used** |
| No colour | `take == 0` | Batch not touched |

### Ingredient Check "Sourced" column

The simplified Ingredient Check table (top right) shows a compact summary per ingredient:
`5.0000 from 3 batches` — sourced amount + count, coloured green (fulfilled) or amber (unfulfilled).

**`carryoverPlan` + `batchConfigData` are preview only.** The authoritative journal lines are written by `ProductionController::store`.

## Shortage check

```php
$available = $lines->sum(fn ($l) => $l->getAttribute('ledger_balance'));
if ($available + 1e-9 < $needed) {
    $unit = optional($item->unit)->abbreviation ?? '';
    $insufficient[] = "{$item->name}: need {$needed} {$unit}, have {$available} {$unit}";
}

// After iterating all ingredients:
if (!empty($insufficient)) {
    throw ValidationException::withMessages(['produce' => implode(' · ', $insufficient)]);
}
```

## Depletion loop (out lines)

```php
foreach ($depletionPlan as [$item, $needed, $lines]) {
    $remaining = $needed;
    foreach ($lines as $line) {
        if ($remaining <= 0) break;
        $balance = $line->getAttribute('ledger_balance');
        if ($balance <= 0) continue;
        $take = min($remaining, $balance);

        DailyJournalLine::create([
            'daily_journal_id'      => $journal->id,
            'item_id'               => $item->id,
            'product_id'            => $product->id,
            'restock_batch_item_id' => $line->id,
            'direction'             => 'out',
            'quantity'              => $take,
            'meta'                  => ['reason' => 'production'],
        ]);

        $remaining -= $take;
    }
}
```

## Product in-line (always last)

```php
DailyJournalLine::create([
    'daily_journal_id' => $journal->id,
    'product_id'       => $product->id,
    // item_id intentionally null — product stock lines have no item_id
    'direction'        => 'in',
    'quantity'         => $qty,
    'meta'             => ['reason' => 'production'],
]);
```

## Index hydration pattern

```php
$products = Product::with('ingredients.item.unit', 'ingredients.item.restockBatchItems.batch.journal')
    ->latest()->get()->each->append('computed_cost');

StockLedger::hydrateProducts($products, $date);
foreach ($products as $product) {
    if ($product->relationLoaded('ingredients')) {
        $items = $product->ingredients->pluck('item')->filter();
        StockLedger::hydrateItems($items, $date);
    }
}

return Inertia::render('Produce/Index', ['products' => $products]);
```

## Validation rules

```php
$request->validate([
    'product_id'       => 'required|exists:products,id',
    'quantity'         => 'required|integer|min:1',
    'notes'            => 'nullable|string|max:500',
    'journal_date'     => 'nullable|date',
    'batch_orders'     => 'nullable|array',
    'batch_orders.*'   => 'array',
    'batch_orders.*.*' => 'integer',
]);
```
