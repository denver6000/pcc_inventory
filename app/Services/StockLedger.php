<?php

namespace App\Services;

use App\Models\DailyJournalLine;
use App\Models\RestockBatchItem;
use Illuminate\Support\Facades\DB;

/**
 * Pure-function stock ledger.
 *
 * Every quantity in the system is derived from the immutable, append-only
 * `daily_journal_lines` table.  Nothing is stored as mutable state —
 * "stock on day D" is always:
 *
 *   Σ(in-lines where journal_date ≤ D) − Σ(out-lines where journal_date ≤ D)
 *
 * The same formula applies to items, products, and individual batch-line
 * balances.  Controllers should call these helpers instead of reading
 * `current_stock` or `quantity_added` columns.
 */
class StockLedger
{
    // ── private scope builder ───────────────────────────────

    /**
     * Base query: journal_lines joined to journals, filtered to date ≤ $date.
     * Uses DATE() to normalise datetime vs date column values across drivers.
     */
    private static function linesThrough(string $date)
    {
        return DailyJournalLine::query()
            ->join('daily_journals', 'daily_journals.id', '=', 'daily_journal_lines.daily_journal_id')
            ->where(DB::raw('DATE(daily_journals.journal_date)'), '<=', $date);
    }

    // ── item stock ──────────────────────────────────────────

    /**
     * Compute every item's stock as of a date (inclusive).
     *
     * @return array<int, float>  [item_id => balance]
     */
    public static function itemStocksAsOf(string $date): array
    {
        return static::linesThrough($date)
            ->whereNotNull('daily_journal_lines.item_id')
            ->groupBy('daily_journal_lines.item_id')
            ->select(
                'daily_journal_lines.item_id',
                DB::raw("ROUND(SUM(CASE WHEN daily_journal_lines.direction = 'in' THEN daily_journal_lines.quantity ELSE -daily_journal_lines.quantity END), 4) as balance")
            )
            ->pluck('balance', 'item_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * Single-item variant.
     */
    public static function itemStockAsOf(int $itemId, string $date): float
    {
        $val = static::linesThrough($date)
            ->where('daily_journal_lines.item_id', $itemId)
            ->select(
                DB::raw("ROUND(SUM(CASE WHEN daily_journal_lines.direction = 'in' THEN daily_journal_lines.quantity ELSE -daily_journal_lines.quantity END), 4) as balance")
            )
            ->value('balance');

        return (float) ($val ?? 0);
    }

    // ── product stock ───────────────────────────────────────

    /**
     * Compute every product's stock as of a date.
     * Only lines where item_id IS NULL count (production output / sales).
     *
     * @return array<int, float>  [product_id => balance]
     */
    public static function productStocksAsOf(string $date): array
    {
        return static::linesThrough($date)
            ->whereNotNull('daily_journal_lines.product_id')
            ->whereNull('daily_journal_lines.item_id')
            ->groupBy('daily_journal_lines.product_id')
            ->select(
                'daily_journal_lines.product_id',
                DB::raw("ROUND(SUM(CASE WHEN daily_journal_lines.direction = 'in' THEN daily_journal_lines.quantity ELSE -daily_journal_lines.quantity END), 4) as balance")
            )
            ->pluck('balance', 'product_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    // ── batch-line balances ─────────────────────────────────

    /**
     * Remaining balance for every restock batch item as of a date.
     * balance = Σ in − Σ out  (both from journal lines).
     *
     * @return array<int, float>  [restock_batch_item_id => remaining]
     */
    public static function batchBalancesAsOf(string $date): array
    {
        return static::linesThrough($date)
            ->whereNotNull('daily_journal_lines.restock_batch_item_id')
            ->groupBy('daily_journal_lines.restock_batch_item_id')
            ->select(
                'daily_journal_lines.restock_batch_item_id',
                DB::raw("ROUND(SUM(CASE WHEN daily_journal_lines.direction = 'in' THEN daily_journal_lines.quantity ELSE -daily_journal_lines.quantity END), 4) as balance")
            )
            ->pluck('balance', 'restock_batch_item_id')
            ->map(fn ($v) => max(0.0, (float) $v))
            ->all();
    }

    /**
     * Balances for batch items belonging to a single item.
     *
     * @return array<int, float>  [restock_batch_item_id => remaining]
     */
    public static function batchBalancesForItemAsOf(int $itemId, string $date): array
    {
        return static::linesThrough($date)
            ->whereNotNull('daily_journal_lines.restock_batch_item_id')
            ->where('daily_journal_lines.item_id', $itemId)
            ->groupBy('daily_journal_lines.restock_batch_item_id')
            ->select(
                'daily_journal_lines.restock_batch_item_id',
                DB::raw("ROUND(SUM(CASE WHEN daily_journal_lines.direction = 'in' THEN daily_journal_lines.quantity ELSE -daily_journal_lines.quantity END), 4) as balance")
            )
            ->pluck('balance', 'restock_batch_item_id')
            ->map(fn ($v) => max(0.0, (float) $v))
            ->all();
    }

    // ── hydration helpers ───────────────────────────────────

    /**
     * Set `current_stock` on each Item model and `quantity_added` on each
     * nested RestockBatchItem using ledger-derived values.
     *
     * Items and their `restockBatchItems` relation must already be loaded.
     */
    public static function hydrateItems($items, string $date): void
    {
        $stocks   = static::itemStocksAsOf($date);
        $balances = static::batchBalancesAsOf($date);

        foreach ($items as $item) {
            $item->current_stock = $stocks[$item->id] ?? 0.0;

            if ($item->relationLoaded('restockBatchItems')) {
                foreach ($item->restockBatchItems as $bi) {
                    $bi->quantity_added = $balances[$bi->id] ?? 0.0;
                }
                // Drop batch lines with zero balance so the UI stays clean
                $item->setRelation(
                    'restockBatchItems',
                    $item->restockBatchItems->filter(fn ($bi) => $bi->quantity_added > 0)->values()
                );
            }
        }
    }

    /**
     * Set `current_stock` on each Product model using ledger values.
     */
    public static function hydrateProducts($products, string $date): void
    {
        $stocks = static::productStocksAsOf($date);

        foreach ($products as $product) {
            $product->current_stock = $stocks[$product->id] ?? 0.0;
        }
    }
}
