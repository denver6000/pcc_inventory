<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Item;
use App\Models\RestockBatchItem;
use App\Models\DailyJournal;
use App\Models\DailyJournalLine;
use App\Services\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;

class ProductionController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', now()->toDateString());

        $products = Product::with('ingredients.item.unit', 'ingredients.item.restockBatchItems.batch')
            ->latest()
            ->get()
            ->each->append('computed_cost');

        // Hydrate all stock values from the ledger for the target date
        StockLedger::hydrateProducts($products, $date);
        foreach ($products as $product) {
            if ($product->relationLoaded('ingredients')) {
                $items = $product->ingredients->pluck('item')->filter();
                StockLedger::hydrateItems($items, $date);
            }
        }

        return Inertia::render('Produce/Index', [
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id'    => 'required|exists:products,id',
            'quantity'      => 'required|integer|min:1',
            'notes'         => 'nullable|string|max:500',
            'journal_date'  => 'nullable|date',
            'batch_orders'  => 'nullable|array',
            'batch_orders.*' => 'array',
            'batch_orders.*.*' => 'integer',
        ]);

        $product = Product::with('ingredients.item.unit')->findOrFail($validated['product_id']);
        $qty = (float) $validated['quantity'];

        if ($product->ingredients->isEmpty()) {
            return back()->withErrors(['produce' => 'Product has no recipe/ingredients defined.']);
        }

        $batchOrders = $validated['batch_orders'] ?? [];
        $journalDate = $validated['journal_date'] ?? now()->toDateString();

        DB::transaction(function () use ($product, $qty, $batchOrders, $journalDate, $validated) {
            $insufficient = [];

            $journal = DailyJournal::create([
                'journal_date' => $journalDate,
                'kind'         => 'produce',
                'notes'        => $validated['notes'] ?? null,
            ]);

            // Pre-compute ledger balances for the target date
            $allBatchBalances = StockLedger::batchBalancesAsOf($journalDate);

            $depletionPlan = []; // collect [item, needed, lines-with-balances]

            foreach ($product->ingredients as $ing) {
                $item   = Item::find($ing->item_id);
                $needed = round($ing->quantity * $qty, 4);

                // Determine batch lines in user order (or oldest-first fallback)
                $requested = $batchOrders[$ing->item_id] ?? [];
                $lineQuery = RestockBatchItem::where('item_id', $ing->item_id);

                if (!empty($requested)) {
                    $lines = $lineQuery->whereIn('id', $requested)->get();
                    $foundIds = $lines->pluck('id')->all();
                    $missing  = array_diff($requested, $foundIds);
                    if (!empty($missing)) {
                        throw ValidationException::withMessages([
                            'produce' => 'One or more selected batches were not found for an ingredient.',
                        ]);
                    }
                    $lines = $lines->sortBy(fn ($l) => array_search($l->id, $requested))->values();
                } else {
                    $lines = $lineQuery->orderBy('created_at')->get();
                }

                // Attach ledger balance to each batch line (in-memory only)
                foreach ($lines as $line) {
                    $line->setAttribute('ledger_balance', $allBatchBalances[$line->id] ?? 0.0);
                }

                $available = $lines->sum(fn ($l) => $l->getAttribute('ledger_balance'));
                $depletionPlan[] = [$item, $needed, $lines];

                if ($available + 1e-9 < $needed) {
                    $unit = optional($item->unit)->abbreviation ?? '';
                    $insufficient[] = "{$item->name}: need {$needed} {$unit}, have {$available} {$unit}";
                }
            }

            if (!empty($insufficient)) {
                throw ValidationException::withMessages(['produce' => implode(' · ', $insufficient)]);
            }

            // Write depletion journal lines — append only, no mutation
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

            // Product stock gained — one journal line
            DailyJournalLine::create([
                'daily_journal_id' => $journal->id,
                'product_id'       => $product->id,
                'direction'        => 'in',
                'quantity'         => $qty,
                'meta'             => ['reason' => 'production'],
            ]);
        });

        return back();
    }
}
