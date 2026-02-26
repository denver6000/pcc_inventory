<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\StockLedger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PosController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', now()->toDateString());

        $products = Product::with('ingredients.item.unit')
            ->latest()
            ->get()
            ->each->append('computed_cost');

        StockLedger::hydrateProducts($products, $date);

        return Inertia::render('Pos/Index', [
            'products' => $products,
        ]);
    }
}
