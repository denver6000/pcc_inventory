<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Inertia\Inertia;

class PosController extends Controller
{
    public function index()
    {
        return Inertia::render('Pos/Index', [
            'products' => Product::with('ingredients.item.unit')
                ->latest()
                ->get()
                ->each->append('computed_cost'),
        ]);
    }
}
