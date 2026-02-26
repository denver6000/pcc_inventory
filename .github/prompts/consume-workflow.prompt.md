# Consume Workflow

Reference: #file:app/Http/Controllers/ItemConsumptionController.php  
Reference: #file:app/Services/StockLedger.php

## What a consume creates (in order, inside DB::transaction)

1. `DailyJournal` — `kind = 'consume'`, `journal_date`
2. Fetch batch lines in user-specified `batch_order` (array of `restock_batch_item_id`s)
3. Validate: all IDs exist for the item; aggregate ledger balance ≥ requested qty
4. Deplete batches in order: one `DailyJournalLine` per batch line touched — `direction = 'out'`

**Never** decrement `quantity_added` on `RestockBatchItem` or any column on `Item`.  
Use `StockLedger::batchBalancesForItemAsOf()` (single-item variant) rather than the full map.

## Full store skeleton

```php
public function store(Request $request, Item $item)
{
    $validated = $request->validate([
        'journal_date' => 'nullable|date',
        'quantity'     => 'required|numeric|min:0.0001',
        'batch_order'  => 'required|array|min:1',
        'batch_order.*'=> 'integer|distinct',
    ]);

    $qty         = (float) $validated['quantity'];
    $order       = array_values($validated['batch_order']);
    $journalDate = $validated['journal_date'] ?? now()->toDateString();

    DB::transaction(function () use ($item, $qty, $order, $journalDate) {
        $journal = DailyJournal::create([
            'journal_date' => $journalDate,
            'kind'         => 'consume',
            'notes'        => null,
        ]);

        $lines    = RestockBatchItem::where('item_id', $item->id)->whereIn('id', $order)->get();
        $foundIds = $lines->pluck('id')->all();
        $missing  = array_diff($order, $foundIds);
        if (!empty($missing)) {
            throw ValidationException::withMessages(['batch_order' => 'One or more selected batches were not found.']);
        }

        $lines    = $lines->sortBy(fn ($l) => array_search($l->id, $order));
        $balances = StockLedger::batchBalancesForItemAsOf($item->id, $journalDate);

        foreach ($lines as $line) {
            $line->setAttribute('ledger_balance', $balances[$line->id] ?? 0.0);
        }

        $available = $lines->sum(fn ($l) => $l->getAttribute('ledger_balance'));
        if ($available + 1e-9 < $qty) {
            throw ValidationException::withMessages([
                'quantity' => 'Insufficient stock in selected batches (available ' . round($available, 4) . ').',
            ]);
        }

        $remaining = $qty;
        foreach ($lines as $line) {
            if ($remaining <= 0) break;
            $balance = $line->getAttribute('ledger_balance');
            if ($balance <= 0) continue;
            $take = min($remaining, $balance);

            DailyJournalLine::create([
                'daily_journal_id'      => $journal->id,
                'item_id'               => $item->id,
                'restock_batch_item_id' => $line->id,
                'direction'             => 'out',
                'quantity'              => $take,
                'meta'                  => ['reason' => 'manual_consume'],
            ]);

            $remaining -= $take;
        }
    });

    return back();
}
```

## Key difference from produce

| | Produce | Consume |
|---|---|---|
| Journal kind | `produce` | `consume` |
| Product in-line | ✅ one `in` line for product | ❌ none |
| Batch balance query | `batchBalancesAsOf()` (all items) | `batchBalancesForItemAsOf($itemId, $date)` |
| Route target | `ProductionController` | `ItemConsumptionController` via `Item` route model binding |
