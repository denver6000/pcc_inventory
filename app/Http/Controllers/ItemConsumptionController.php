<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\RestockBatchItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemConsumptionController extends Controller
{
    public function store(Request $request, Item $item)
    {
        $validated = $request->validate([
            'quantity'     => 'required|numeric|min:0.0001',
            'batch_order'  => 'required|array|min:1',
            'batch_order.*'=> 'integer|distinct',
        ]);

        $qty = (float) $validated['quantity'];
        $order = array_values($validated['batch_order']);

        DB::transaction(function () use ($item, $qty, $order) {
            // Lock the item row first
            $lockedItem = Item::where('id', $item->id)->lockForUpdate()->first();

            // Lock selected batch lines
            $lines = RestockBatchItem::where('item_id', $item->id)
                ->whereIn('id', $order)
                ->lockForUpdate()
                ->get();

            // Ensure all requested batch ids exist for this item
            $foundIds = $lines->pluck('id')->all();
            $missing = array_diff($order, $foundIds);
            if (!empty($missing)) {
                throw ValidationException::withMessages(['batch_order' => 'One or more selected batches were not found.']);
            }

            // Sort lines by the user-specified order
            $lines = $lines->sortBy(function ($line) use ($order) {
                return array_search($line->id, $order);
            });

            $available = $lines->sum('quantity_added');
            if ($available + 1e-9 < $qty) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient stock in selected batches (available ' . $available . ').',
                ]);
            }

            $remaining = $qty;
            foreach ($lines as $line) {
                if ($remaining <= 0) break;
                $take = min($remaining, (float) $line->quantity_added);
                $line->decrement('quantity_added', $take);
                $remaining -= $take;
            }

            $lockedItem->decrement('current_stock', $qty);
        });

        return back();
    }
}
