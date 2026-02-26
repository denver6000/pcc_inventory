<?php

namespace App\Http\Controllers;

use App\Models\DailyJournal;
use App\Models\DailyJournalLine;
use App\Models\Product;
use App\Models\Sale;
use App\Services\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity'     => 'required|integer|min:1',
            'notes'        => 'nullable|string|max:500',
            'journal_date' => 'nullable|date',
        ]);

        $qty = (float) $validated['quantity'];
        $journalDate = $validated['journal_date'] ?? now()->toDateString();

        $available = StockLedger::productStocksAsOf($journalDate)[$product->id] ?? 0.0;
        if ($available + 1e-9 < $qty) {
            return back()->withErrors([
                'checkout' => 'Insufficient product stock (available ' . round($available, 4) . ').',
            ]);
        }

        $product->load('ingredients.item');
        $effectivePrice = $this->effectivePrice($product);

        DB::transaction(function () use ($product, $qty, $validated, $effectivePrice, $journalDate) {
            $journal = DailyJournal::create([
                'journal_date' => $journalDate,
                'kind'         => 'consume',
                'notes'        => $validated['notes'] ?? null,
            ]);

            DailyJournalLine::create([
                'daily_journal_id' => $journal->id,
                'product_id'       => $product->id,
                'direction'        => 'out',
                'quantity'         => $qty,
                'meta'             => ['reason' => 'sale'],
            ]);

            Sale::create([
                'product_id'    => $product->id,
                'quantity_sold' => $qty,
                'unit_price'    => $effectivePrice,
                'total_price'   => round($effectivePrice * $qty, 2),
                'sale_date'     => $journalDate,
                'notes'         => $validated['notes'] ?? null,
            ]);
        });

        return back();
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'notes'              => 'nullable|string|max:500',
            'journal_date'       => 'nullable|date',
        ]);

        $journalDate = $validated['journal_date'] ?? now()->toDateString();

        $requested = collect($validated['items'])
            ->groupBy('product_id')
            ->map(fn ($rows) => (float) collect($rows)->sum('quantity'));

        $productIds = $requested->keys()->map(fn ($id) => (int) $id)->values();
        $products = Product::with('ingredients.item')->whereIn('id', $productIds)->get()->keyBy('id');

        $availableStocks = StockLedger::productStocksAsOf($journalDate);
        $insufficient = [];

        foreach ($requested as $productId => $qty) {
            $product = $products->get((int) $productId);
            if (!$product) {
                $insufficient[] = 'One or more products were not found.';
                continue;
            }

            $available = (float) ($availableStocks[$product->id] ?? 0.0);
            if ($available + 1e-9 < $qty) {
                $insufficient[] = $product->name . ': need ' . round($qty, 4) . ', available ' . round($available, 4) . '.';
            }
        }

        if (!empty($insufficient)) {
            return back()->withErrors(['checkout' => implode(' · ', $insufficient)]);
        }

        DB::transaction(function () use ($products, $requested, $validated, $journalDate) {
            $journal = DailyJournal::create([
                'journal_date' => $journalDate,
                'kind'         => 'consume',
                'notes'        => $validated['notes'] ?? null,
            ]);

            foreach ($requested as $productId => $qty) {
                $product = $products->get((int) $productId);
                if (!$product) {
                    continue;
                }

                $effectivePrice = $this->effectivePrice($product);

                DailyJournalLine::create([
                    'daily_journal_id' => $journal->id,
                    'product_id'       => $product->id,
                    'direction'        => 'out',
                    'quantity'         => $qty,
                    'meta'             => ['reason' => 'sale'],
                ]);

                Sale::create([
                    'product_id'    => $product->id,
                    'quantity_sold' => $qty,
                    'unit_price'    => $effectivePrice,
                    'total_price'   => round($effectivePrice * $qty, 2),
                    'sale_date'     => $journalDate,
                    'notes'         => $validated['notes'] ?? null,
                ]);
            }
        });

        return back();
    }

    private function effectivePrice(Product $product): float
    {
        if ($product->selling_price > 0) {
            return (float) $product->selling_price;
        }

        if ($product->markup_percentage > 0) {
            return (float) round($product->computed_cost * (1 + $product->markup_percentage / 100), 2);
        }

        return (float) $product->computed_cost;
    }
}
