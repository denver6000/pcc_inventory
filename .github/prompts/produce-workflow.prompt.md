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
