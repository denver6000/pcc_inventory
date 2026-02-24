<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ItemController extends Controller
{
    public function index()
    {
        return Inertia::render('Items/Index', [
            'items' => Item::with('unit')->latest()->get(),
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

        $validated['current_stock'] = $validated['default_stock'];

        Item::create($validated);

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
            'current_stock' => 'required|numeric|min:0',
        ]);

        if ($request->hasFile('image')) {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('items', 'public');
        }
        unset($validated['image']);

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
