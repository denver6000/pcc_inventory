<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function index()
    {
        return Inertia::render('Units/Index', [
            'units' => Unit::withCount('items')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:100|unique:units,name',
            'abbreviation' => 'required|string|max:20',
        ]);

        Unit::create($request->only('name', 'abbreviation'));

        return back();
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();

        return back();
    }
}
