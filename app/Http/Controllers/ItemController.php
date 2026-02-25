<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Unit;
use App\Models\DailyJournal;
use App\Models\DailyJournalLine;
use App\Models\RestockBatch;
use App\Services\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $date  = $request->query('date', now()->toDateString());
        $items = Item::with(['unit', 'restockBatchItems.batch'])->latest()->get();

        // Hydrate current_stock and batch balances from the immutable ledger
        StockLedger::hydrateItems($items, $date);

        return Inertia::render('Items/Index', [
            'items' => $items,
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
            'journal_date'  => 'nullable|date',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('items', 'public');
        }
        unset($validated['image']);

        $journalDate = $validated['journal_date'] ?? now()->toDateString();
        unset($validated['journal_date']);

        DB::transaction(function () use ($validated, $journalDate) {
            $item = Item::create(array_merge($validated, ['current_stock' => 0]));

            $initialQty = (float) $validated['default_stock'];
            if ($initialQty > 0) {
                $code  = 'RST-INIT-' . now()->format('Ymd') . '-' . str_pad($item->id, 4, '0', STR_PAD_LEFT);
                $cost  = (float) ($validated['cost_per_unit'] ?? 0);
                $total = round($initialQty * $cost, 4);

                // Create the journal header
                $journal = DailyJournal::create([
                    'journal_date' => $journalDate,
                    'kind'         => 'restock',
                    'notes'        => 'Initial stock for item #' . $item->id,
                ]);

                $batch = RestockBatch::create([
                    'batch_code' => $code,
                    'journal_id' => $journal->id,
                    'notes'      => 'Initial stock for item #' . $item->id,
                    'total_cost' => $total,
                ]);

                $batchItem = $batch->items()->create([
                    'item_id'        => $item->id,
                    'quantity_added' => $initialQty,
                    'cost_per_unit'  => $cost,
                    'subtotal'       => $total,
                ]);

                // Append the journal line — no increment on the item row
                $journalLine = DailyJournalLine::create([
                    'daily_journal_id'      => $journal->id,
                    'item_id'               => $item->id,
                    'restock_batch_item_id' => $batchItem->id,
                    'direction'             => 'in',
                    'quantity'              => $initialQty,
                    'meta'                  => ['batch_code' => $code],
                ]);

                $batchItem->update(['journal_line_id' => $journalLine->id]);
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
