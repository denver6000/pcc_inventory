<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\RestockBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RestockController extends Controller
{
    public function index()
    {
        return Inertia::render('Restock/Index', [
            'batches' => RestockBatch::with('items.item.unit')->latest()->get(),
            'items'   => Item::with('unit')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'notes'                    => 'nullable|string|max:500',
            'items'                    => 'required|array|min:1',
            'items.*.item_id'          => 'required|exists:items,id',
            'items.*.quantity_added'   => 'required|numeric|min:0.0001',
        ]);

        DB::transaction(function () use ($request) {
            $lines     = [];
            $totalCost = 0;

            foreach ($request->items as $line) {
                $item    = Item::findOrFail($line['item_id']);
                $qty     = (float) $line['quantity_added'];
                $cost    = (float) $item->cost_per_unit;
                $sub     = round($qty * $cost, 4);
                $totalCost += $sub;

                $lines[] = [
                    'item_id'        => $item->id,
                    'quantity_added' => $qty,
                    'cost_per_unit'  => $cost,
                    'subtotal'       => $sub,
                ];

                $item->increment('current_stock', $qty);
            }

            // Human-readable code: RST-YYYYMMDD-NNN (daily sequence)
            $today      = now()->toDateString();
            $dailyCount = RestockBatch::whereDate('created_at', $today)->lockForUpdate()->count() + 1;
            $batchCode  = 'RST-' . now()->format('Ymd') . '-' . str_pad($dailyCount, 3, '0', STR_PAD_LEFT);

            $batch = RestockBatch::create([
                'batch_code' => $batchCode,
                'notes'      => $request->notes,
                'total_cost' => round($totalCost, 2),
            ]);

            foreach ($lines as $l) {
                $batch->items()->create($l);
            }
        });

        return back();
    }
}
