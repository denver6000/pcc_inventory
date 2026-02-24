<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Item;
use App\Models\RestockBatchItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;

class ProductionController extends Controller
{
    public function index()
    {
        $products = Product::with('ingredients.item.unit', 'ingredients.item.restockBatchItems.batch')
            ->latest()
            ->get()
            ->each->append('computed_cost');

        return Inertia::render('Produce/Index', [
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
            'notes'      => 'nullable|string|max:500',
            'batch_orders' => 'nullable|array',
            'batch_orders.*' => 'array',
            'batch_orders.*.*' => 'integer',
        ]);

        $product = Product::with('ingredients.item.unit')->findOrFail($validated['product_id']);
        $qty = (float) $validated['quantity'];

        if ($product->ingredients->isEmpty()) {
            return back()->withErrors(['produce' => 'Product has no recipe/ingredients defined.']);
        }

        $batchOrders = $validated['batch_orders'] ?? [];

        DB::transaction(function () use ($product, $qty, $batchOrders) {
            $insufficient = [];

            // Lock each ingredient item to ensure consistent stock read + write
            $lockedItems = [];
            foreach ($product->ingredients as $ing) {
                $item = Item::where('id', $ing->item_id)->lockForUpdate()->first();
                $needed = round($ing->quantity * $qty, 4);

                // Determine batch lines in user order (or oldest-first fallback)
                $requested = $batchOrders[$ing->item_id] ?? [];
                $lineQuery = RestockBatchItem::where('item_id', $ing->item_id)->lockForUpdate();

                if (!empty($requested)) {
                    $lines = $lineQuery->whereIn('id', $requested)->get();
                    $foundIds = $lines->pluck('id')->all();
                    $missing = array_diff($requested, $foundIds);
                    if (!empty($missing)) {
                        throw ValidationException::withMessages([
                            'produce' => 'One or more selected batches were not found for an ingredient.',
                        ]);
                    }
                    $lines = $lines->sortBy(function ($line) use ($requested) {
                        return array_search($line->id, $requested);
                    })->values();
                } else {
                    $lines = $lineQuery->orderBy('created_at')->get();
                }

                $available = $lines->sum('quantity_added');
                $lockedItems[] = [$item, $needed, $lines];

                if ($available + 1e-9 < $needed) {
                    $unit = optional($item->unit)->abbreviation ?? '';
                    $insufficient[] = "{$item->name}: need {$needed} {$unit}, have {$available} {$unit}";
                }
            }

            if (!empty($insufficient)) {
                throw ValidationException::withMessages(['produce' => implode(' · ', $insufficient)]);
            }

            foreach ($lockedItems as [$lockedItem, $needed, $lines]) {
                $remaining = $needed;
                foreach ($lines as $line) {
                    if ($remaining <= 0) break;
                    $take = min($remaining, (float) $line->quantity_added);
                    $line->decrement('quantity_added', $take);
                    $remaining -= $take;
                }
                $lockedItem->decrement('current_stock', $needed);
            }

            // Lock product row and increment its stock
            Product::where('id', $product->id)->lockForUpdate()->increment('current_stock', $qty);
        });

        return back();
    }
}
