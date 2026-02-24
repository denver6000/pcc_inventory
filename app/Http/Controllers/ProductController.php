<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Models\Item;

class ProductController extends Controller
{
    public function index()
    {
        return Inertia::render('Products/Index', [
            'products' => Product::with('ingredients.item.unit')->latest()->get()->each->append('computed_cost'),
            'items'    => Item::with('unit')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                   => 'required|string|max:255',
            'image'                  => 'nullable|image|max:4096',
            'selling_price'          => 'nullable|numeric|min:0',
            'markup_percentage'      => 'nullable|numeric|min:0',
            'ingredients'            => 'array',
            'ingredients.*.item_id'  => 'required|exists:items,id',
            'ingredients.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        $data = [
            'name'              => $request->name,
            'selling_price'     => $request->selling_price ?? 0,
            'markup_percentage' => $request->markup_percentage ?? 0,
        ];
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);

        foreach ($request->input('ingredients', []) as $ing) {
            $product->ingredients()->create([
                'item_id'  => $ing['item_id'],
                'quantity' => $ing['quantity'],
            ]);
        }

        return back();
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name'                   => 'required|string|max:255',
            'image'                  => 'nullable|image|max:4096',
            'selling_price'          => 'nullable|numeric|min:0',
            'markup_percentage'      => 'nullable|numeric|min:0',
            'ingredients'            => 'array',
            'ingredients.*.item_id'  => 'required|exists:items,id',
            'ingredients.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        $data = [
            'name'              => $request->name,
            'selling_price'     => $request->selling_price ?? 0,
            'markup_percentage' => $request->markup_percentage ?? 0,
        ];
        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        // Full replace of recipe
        $product->ingredients()->delete();
        foreach ($request->input('ingredients', []) as $ing) {
            $product->ingredients()->create([
                'item_id'  => $ing['item_id'],
                'quantity' => $ing['quantity'],
            ]);
        }

        return back();
    }

    public function destroy(Product $product)
    {
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }
        $product->delete();

        return back();
    }
}
