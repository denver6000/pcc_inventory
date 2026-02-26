<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SaleHistoryController extends Controller
{
    public function index(Request $request)
    {
        $end = Carbon::parse($request->query('date', now()->toDateString()))->toDateString();
        $start = Carbon::parse($end)->subDays(29)->toDateString();

        $sales = Sale::with('product:id,name')
            ->where(function ($q) use ($end) {
                $q->whereDate('sale_date', '<=', $end)
                    ->orWhere(function ($q2) use ($end) {
                        $q2->whereNull('sale_date')
                            ->whereDate('created_at', '<=', $end);
                    });
            })
            ->latest('id')
            ->get();

        $history = $sales->map(function (Sale $sale) {
            $saleDate = $sale->sale_date ?? $sale->created_at;

            return [
                'id' => $sale->id,
                'sold_on' => $saleDate?->toDateString(),
                'product' => [
                    'id' => $sale->product?->id,
                    'name' => $sale->product?->name ?? 'Unknown Product',
                ],
                'quantity_sold' => (float) $sale->quantity_sold,
                'unit_price' => (float) $sale->unit_price,
                'total_price' => (float) $sale->total_price,
                'notes' => $sale->notes,
            ];
        })->values();

        $trendScope = $history->filter(fn ($row) => $row['sold_on'] >= $start && $row['sold_on'] <= $end);
        $grouped = $trendScope->groupBy('sold_on');

        $trend = collect(CarbonPeriod::create($start, $end))->map(function (Carbon $day) use ($grouped) {
            $key = $day->toDateString();
            $rows = $grouped->get($key, collect());

            return [
                'date' => $key,
                'revenue' => round((float) $rows->sum('total_price'), 2),
                'units' => round((float) $rows->sum('quantity_sold'), 4),
                'sales_count' => (int) $rows->count(),
            ];
        })->values();

        return Inertia::render('SalesHistory/Index', [
            'sales' => $history,
            'trend' => $trend,
            'window' => [
                'start' => $start,
                'end' => $end,
            ],
            'summary' => [
                'sales_count' => (int) $history->count(),
                'units_sold' => round((float) $history->sum('quantity_sold'), 4),
                'revenue' => round((float) $history->sum('total_price'), 2),
            ],
        ]);
    }
}
