<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ItemController extends Controller
{
    public function index()
    {
        return Inertia::render('Items/Index', [
            'items' => Item::with(['unit', 'restockBatchItems.batch'])->latest()->get(),
            'units' => Unit::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'image'         => 'nullable|image|max:4096',
            'unit_id'       => 'nullable|exists:units,id',
            'cost_per_unit' => 'nullable|numeric|min:0',
            'default_stock' => 'required|numeric|min:0',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('items', 'public');
        }
        unset($validated['image']);

        DB::transaction(function () use ($validated) {
            // Start items at zero and seed initial stock via a batch entry to keep all stock batch-bound
            $item = Item::create(array_merge($validated, ['current_stock' => 0]));

            $initialQty = (float) $validated['default_stock'];
            if ($initialQty > 0) {
                $code = 'RST-INIT-' . now()->format('Ymd') . '-' . str_pad($item->id, 4, '0', STR_PAD_LEFT);
                $total = round($initialQty * ((float) ($validated['cost_per_unit'] ?? 0)), 4);

                $batchId = DB::table('restock_batches')->insertGetId([
                    'batch_code' => $code,
                    'notes'      => 'Initial stock for item #' . $item->id,
                    'total_cost' => $total,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('restock_batch_items')->insert([
                    'restock_batch_id' => $batchId,
                    'item_id'          => $item->id,
                    'quantity_added'   => $initialQty,
                    'cost_per_unit'    => (float) ($validated['cost_per_unit'] ?? 0),
                    'subtotal'         => $total,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);

                $item->increment('current_stock', $initialQty);
            }
        });

        return back();
    }

    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'image'         => 'nullable|image|max:4096',
            'unit_id'       => 'nullable|exists:units,id',
            'cost_per_unit' => 'nullable|numeric|min:0',
            'default_stock' => 'required|numeric|min:0',
        ]);

        if ($request->hasFile('image')) {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('items', 'public');
        }
        unset($validated['image']);

        unset($validated['current_stock']);

        $item->update($validated);

        return back();
    }

    public function destroy(Item $item)
    {
        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }
        $item->delete();

        return back();
    }
}
