<?php

namespace App\Http\Controllers;

use App\Models\DailyJournalLine;
use App\Models\Item;
use App\Services\StockLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TimelineController extends Controller
{
    public function index(Request $request)
    {
        $date   = $request->query('date', now()->toDateString());
        $limit  = max(5, (int) $request->query('limit', 5));
        $items  = Item::orderBy('name')->get(['id', 'name']);
        $itemId = $request->query('item_id') ?: optional($items->first())->id;

        $history = $this->buildHistory($date, $itemId, $limit);

        return Inertia::render('Timeline/Index', [
            'items'   => $items,
            'history' => $history,
        ]);
    }

    private function buildHistory(string $date, ?int $itemId, int $limit): ?array
    {
        if (!$itemId) {
            return null;
        }

        $rows = DailyJournalLine::join('daily_journals as dj', 'dj.id', '=', 'daily_journal_lines.daily_journal_id')
            ->where('daily_journal_lines.item_id', $itemId)
            ->whereRaw('DATE(dj.journal_date) <= ?', [$date])
            ->groupBy(DB::raw('DATE(dj.journal_date)'))
            ->orderBy(DB::raw('DATE(dj.journal_date)'), 'desc')
            ->limit($limit + 1) // lookahead for has_more flag
            ->get([
                DB::raw('DATE(dj.journal_date) as day'),
                DB::raw("ROUND(SUM(CASE WHEN daily_journal_lines.direction = 'in' THEN daily_journal_lines.quantity ELSE -daily_journal_lines.quantity END), 4) as delta"),
            ]);

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->reverse()->values();

        if ($rows->isEmpty()) {
            return [
                'item_id'   => $itemId,
                'item_name' => optional(Item::find($itemId))->name,
                'series'    => [],
                'has_more'  => false,
                'limit'     => $limit,
            ];
        }

        $earliest = $rows->first()->day;
        $base     = StockLedger::itemStockAsOf($itemId, Carbon::parse($earliest)->subDay()->toDateString());

        $series  = [];
        $running = $base;
        foreach ($rows as $r) {
            $running += (float) $r->delta;
            $series[] = [
                'date'    => $r->day,
                'delta'   => (float) $r->delta,
                'balance' => $running,
            ];
        }

        return [
            'item_id'   => $itemId,
            'item_name' => optional(Item::find($itemId))->name,
            'series'    => $series,
            'has_more'  => $hasMore,
            'limit'     => $limit,
        ];
    }
}
