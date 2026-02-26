# Restock Workflow

Reference: #file:app/Http/Controllers/RestockController.php  
Reference: #file:app/Services/StockLedger.php

## What a restock creates (in order, inside a DB::transaction)

1. `DailyJournal` — `kind = 'restock'`, `journal_date`, optional `notes`
2. `RestockBatch` — `batch_code`, `journal_id`, `total_cost`
3. For each item line:
   - `RestockBatchItem` via `$batch->items()->create([...])`
   - `DailyJournalLine` — `direction = 'in'`, `restock_batch_item_id`, `item_id`, `quantity`
   - Back-link: `$batchItem->update(['journal_line_id' => $line->id])`

**Never** call `$item->increment('current_stock', ...)` or write to `current_stock` directly.

## Batch code formats

| Context | Format |
|---|---|
| Regular restock | `RST-YYYYMMDD-NNN` (NNN = daily sequence, padded to 3) |
| Initial item stock | `RST-INIT-YYYYMMDD-{item_id padded to 4}` |

```php
// Regular batch code
$dailyCount = RestockBatch::whereDate('created_at', $today)->lockForUpdate()->count() + 1;
$batchCode  = 'RST-' . now()->format('Ymd') . '-' . str_pad($dailyCount, 3, '0', STR_PAD_LEFT);

// Initial stock code (used in ItemController::store)
$code = 'RST-INIT-' . now()->format('Ymd') . '-' . str_pad($item->id, 4, '0', STR_PAD_LEFT);
```

## Full store transaction skeleton

```php
DB::transaction(function () use ($request) {
    $journalDate = $request->input('journal_date') ?: now()->toDateString();

    $journal = DailyJournal::create([
        'journal_date' => $journalDate,
        'kind'         => 'restock',
        'notes'        => $request->notes,
    ]);

    $lines = [];
    $totalCost = 0;

    foreach ($request->items as $line) {
        $item  = Item::findOrFail($line['item_id']);
        $qty   = (float) $line['quantity_added'];
        $cost  = (float) $item->cost_per_unit;
        $sub   = round($qty * $cost, 4);
        $totalCost += $sub;
        $lines[] = ['item_id' => $item->id, 'quantity_added' => $qty, 'cost_per_unit' => $cost, 'subtotal' => $sub];
    }

    $today      = now()->toDateString();
    $dailyCount = RestockBatch::whereDate('created_at', $today)->lockForUpdate()->count() + 1;
    $batchCode  = 'RST-' . now()->format('Ymd') . '-' . str_pad($dailyCount, 3, '0', STR_PAD_LEFT);

    $batch = RestockBatch::create([
        'batch_code' => $batchCode,
        'journal_id' => $journal->id,
        'notes'      => $request->notes,
        'total_cost' => round($totalCost, 2),
    ]);

    foreach ($lines as $l) {
        $batchItem = $batch->items()->create($l);

        $journalLine = DailyJournalLine::create([
            'daily_journal_id'      => $journal->id,
            'item_id'               => $batchItem->item_id,
            'restock_batch_item_id' => $batchItem->id,
            'direction'             => 'in',
            'quantity'              => $batchItem->quantity_added,
            'meta'                  => ['batch_code' => $batch->batch_code],
        ]);

        $batchItem->update(['journal_line_id' => $journalLine->id]);
    }
});

return back();
```

## Index controller pattern

```php
public function index(Request $request)
{
    $date = $request->query('date', now()->toDateString());

    $batches = RestockBatch::with('items.item.unit')
        ->whereHas('journal', fn ($q) => $q->whereRaw('DATE(journal_date) <= ?', [$date]))
        ->latest()
        ->get();

    $items    = Item::with('unit')->orderBy('name')->get();
    $balances = StockLedger::batchBalancesAsOf($date);

    foreach ($batches as $batch) {
        foreach ($batch->items as $bi) {
            $bi->quantity_added = $balances[$bi->id] ?? 0.0;
        }
    }

    return Inertia::render('Restock/Index', [
        'batches' => $batches,
        'items'   => $items,
    ]);
}
```

## Validation rules

```php
$request->validate([
    'journal_date'           => 'nullable|date',
    'notes'                  => 'nullable|string|max:500',
    'items'                  => 'required|array|min:1',
    'items.*.item_id'        => 'required|exists:items,id',
    'items.*.quantity_added' => 'required|numeric|min:0.0001',
]);
```
