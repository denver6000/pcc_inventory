# New Controller Pattern

Reference: #file:app/Http/Controllers/ItemController.php  
Reference: #file:app/Http/Controllers/RestockController.php  
Reference: #file:routes/web.php

## Rules

- Always `return Inertia::render('Section/Index', [...])` — **never** `return view(...)`.
- All mutation methods (`store`, `update`, `destroy`) must `return back()`.
- Read the `?date=` query param: `$date = $request->query('date', now()->toDateString())`.
- Pass `$date` to every `StockLedger::*` call.
- Wrap all DB writes in `DB::transaction(function () use (...) { ... })`.
- **Never** call `$model->increment()` / `$model->decrement()` for quantities.
- Images: store on `public` disk (`Storage::disk('public')->delete()` on removal).

## Index skeleton

```php
public function index(Request $request)
{
    $date = $request->query('date', now()->toDateString());

    $items = Item::with(['unit', 'restockBatchItems.batch'])->latest()->get();

    // Hydrate stock from the immutable ledger — never read current_stock from DB
    StockLedger::hydrateItems($items, $date);

    return Inertia::render('Items/Index', [
        'items' => $items,
        'units' => Unit::orderBy('name')->get(),
    ]);
}
```

## Store skeleton

```php
public function store(Request $request)
{
    $validated = $request->validate([...]);

    DB::transaction(function () use ($validated) {
        $model = ModelClass::create($validated);

        // append-only journal entry if stock changes
        DailyJournalLine::create([...]);
    });

    return back();
}
```

## Routing convention (routes/web.php)

```php
Route::get('/section',           [SectionController::class, 'index']);
Route::post('/section',          [SectionController::class, 'store']);
Route::put('/section/{model}',   [SectionController::class, 'update']);
Route::delete('/section/{model}',[SectionController::class, 'destroy']);
```

Mutations have no named routes — the frontend posts to bare paths.

## Required imports (typical)

```php
use App\Models\Item;
use App\Models\DailyJournal;
use App\Models\DailyJournalLine;
use App\Services\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
```

## Model conventions

- All models use `$fillable` (no `$guarded`).
- Casts use the `casts()` method (Laravel 12 style), not `$casts` property.
- `computed_cost` on `Product` is a `getComputedCostAttribute` accessor; append after loading: `->each->append('computed_cost')`.
