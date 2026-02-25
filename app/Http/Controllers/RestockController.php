<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\RestockBatch;
use App\Models\DailyJournal;
use App\Models\DailyJournalLine;
use App\Services\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RestockController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', now()->toDateString());

        // Only show batches whose journal entry falls on or before the viewed date
        $batches = RestockBatch::with('items.item.unit')
            ->whereHas('journal', fn ($q) => $q->whereRaw('DATE(journal_date) <= ?', [$date]))
            ->latest()
            ->get();

        $items = Item::with('unit')->orderBy('name')->get();

        // Hydrate batch-line balances from ledger (shows remaining qty as of $date)
        $balances = StockLedger::batchBalancesAsOf($date);
        foreach ($batches as $batch) {
            foreach ($batch->items as $bi) {
                $bi->quantity_added = $balances[$bi->id] ?? 0.0;
            }
        }

        return Inertia::render('Restock/Index', [
            'batches' => $batches,
            'items'   => $items,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'journal_date'             => 'nullable|date',
            'notes'                    => 'nullable|string|max:500',
            'items'                    => 'required|array|min:1',
            'items.*.item_id'          => 'required|exists:items,id',
            'items.*.quantity_added'   => 'required|numeric|min:0.0001',
        ]);

        DB::transaction(function () use ($request) {
            // Accept optional journal_date; if blank or missing, default to today
            $journalDate = $request->input('journal_date') ?: now()->toDateString();

            $journal = DailyJournal::create([
                'journal_date' => $journalDate,
                'kind'         => 'restock',
                'notes'        => $request->notes,
            ]);

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
                // NOTE: no $item->increment() — stock is derived from the ledger
            }

            // Human-readable code: RST-YYYYMMDD-NNN (daily sequence)
            $today      = now()->toDateString();
            $dailyCount = RestockBatch::whereDate('created_at', $today)->lockForUpdate()->count() + 1;
            $batchCode  = 'RST-' . now()->format('Ymd') . '-' . str_pad($dailyCount, 3, '0', STR_PAD_LEFT);

            $batch = RestockBatch::create([
                'batch_code' => $batchCode,
                'journal_id' => $journal->id,
                'notes'      => $request->notes,
                'total_cost' => round($totalCost, 2),
            ]);

            foreach ($lines as $l) {
                $batchItem = $batch->items()->create($l);

                $journalLine = DailyJournalLine::create([
                    'daily_journal_id'      => $journal->id,
                    'item_id'               => $batchItem->item_id,
                    'restock_batch_item_id' => $batchItem->id,
                    'direction'             => 'in',
                    'quantity'              => $batchItem->quantity_added,
                    'meta'                  => ['batch_code' => $batch->batch_code],
                ]);

                $batchItem->update(['journal_line_id' => $journalLine->id]);
            }
        });

        return back();
    }
}
