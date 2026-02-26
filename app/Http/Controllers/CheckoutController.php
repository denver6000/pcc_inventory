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

        $effectivePrice = $product->selling_price > 0
            ? (float) $product->selling_price
            : ($product->markup_percentage > 0
                ? round($product->computed_cost * (1 + $product->markup_percentage / 100), 2)
                : $product->computed_cost);

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
                'notes'         => $validated['notes'] ?? null,
            ]);
        });

        return back();
    }
}
