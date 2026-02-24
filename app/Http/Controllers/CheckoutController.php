<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $request->validate([
            'quantity' => 'required|numeric|min:0.01',
            'notes'    => 'nullable|string|max:500',
        ]);

        $qty = (float) $request->quantity;

        $product->load('ingredients.item.unit');

        // Verify every ingredient has enough stock
        $insufficient = [];
        foreach ($product->ingredients as $ing) {
            $required  = round($ing->quantity * $qty, 4);
            $available = (float) $ing->item->current_stock;
            if ($available < $required) {
                $unit = optional($ing->item->unit)->abbreviation ?? '';
                $insufficient[] = "{$ing->item->name}: need {$required} {$unit}, have {$available} {$unit}";
            }
        }

        if (!empty($insufficient)) {
            return back()->withErrors(['checkout' => implode(' · ', $insufficient)]);
        }

        $effectivePrice = $product->selling_price > 0
            ? (float) $product->selling_price
            : ($product->markup_percentage > 0
                ? round($product->computed_cost * (1 + $product->markup_percentage / 100), 2)
                : $product->computed_cost);

        DB::transaction(function () use ($product, $qty, $request, $effectivePrice) {
            foreach ($product->ingredients as $ing) {
                $ing->item->decrement('current_stock', round($ing->quantity * $qty, 4));
            }

            Sale::create([
                'product_id'    => $product->id,
                'quantity_sold' => $qty,
                'unit_price'    => $effectivePrice,
                'total_price'   => round($effectivePrice * $qty, 2),
                'notes'         => $request->notes,
            ]);
        });

        return back();
    }
}
