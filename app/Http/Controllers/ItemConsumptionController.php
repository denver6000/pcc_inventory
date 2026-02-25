<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\RestockBatchItem;
use App\Models\DailyJournal;
use App\Models\DailyJournalLine;
use App\Services\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemConsumptionController extends Controller
{
    public function store(Request $request, Item $item)
    {
        $validated = $request->validate([
            'journal_date' => 'nullable|date',
            'quantity'     => 'required|numeric|min:0.0001',
            'batch_order'  => 'required|array|min:1',
            'batch_order.*'=> 'integer|distinct',
        ]);

        $qty   = (float) $validated['quantity'];
        $order = array_values($validated['batch_order']);
        $journalDate = $validated['journal_date'] ?? now()->toDateString();

        DB::transaction(function () use ($item, $qty, $order, $journalDate) {
            $journal = DailyJournal::create([
                'journal_date' => $journalDate,
                'kind'         => 'consume',
                'notes'        => null,
            ]);

            // Fetch the requested batch lines (metadata only — we don't mutate them)
            $lines = RestockBatchItem::where('item_id', $item->id)
                ->whereIn('id', $order)
                ->get();

            $foundIds = $lines->pluck('id')->all();
            $missing  = array_diff($order, $foundIds);
            if (!empty($missing)) {
                throw ValidationException::withMessages(['batch_order' => 'One or more selected batches were not found.']);
            }

            // Sort by user-specified order
            $lines = $lines->sortBy(fn ($l) => array_search($l->id, $order));

            // Compute remaining balances from the ledger
            $balances = StockLedger::batchBalancesForItemAsOf($item->id, $journalDate);

            foreach ($lines as $line) {
                $line->setAttribute('ledger_balance', $balances[$line->id] ?? 0.0);
            }

            $available = $lines->sum(fn ($l) => $l->getAttribute('ledger_balance'));
            if ($available + 1e-9 < $qty) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient stock in selected batches (available ' . round($available, 4) . ').',
                ]);
            }

            // Append-only: write journal lines, never decrement batch rows
            $remaining = $qty;
            foreach ($lines as $line) {
                if ($remaining <= 0) break;
                $balance = $line->getAttribute('ledger_balance');
                if ($balance <= 0) continue;
                $take = min($remaining, $balance);

                DailyJournalLine::create([
                    'daily_journal_id'      => $journal->id,
                    'item_id'               => $item->id,
                    'restock_batch_item_id' => $line->id,
                    'direction'             => 'out',
                    'quantity'              => $take,
                    'meta'                  => ['reason' => 'manual_consume'],
                ]);

                $remaining -= $take;
            }
        });

        return back();
    }
}
