import React from 'react';
import { ui } from '@/theme';
import { batchDate, fmtDate } from './helpers';

/**
 * Batch Configuration panel — per-ingredient cards with Sequential / Distributed
 * mode toggle, drag-and-drop reordering, and per-ingredient reset.
 */
const BatchConfigPanel = ({ product, batchConfig }) => {
    const {
        batchConfigData,
        distributedAmounts,
        modeFor,
        setMode,
        autoAssignIngredient,
        autoDistributeEvenly,
        primeBatchOrders,
        setDistAmount,
        handleDragStart,
        handleDragEnter,
        handleDragEnd,
        orderFor,
        setOrder,
        resetIngredient,
    } = batchConfig;

    if (!product?.ingredients?.length) return null;

    return (
        <div className={[ui.card, 'mt-4 p-4'].join(' ')}>
            <div className="flex items-center justify-between mb-3">
                <div>
                    <p className={ui.subheading}>Batch Configuration</p>
                    <p className="text-[11px] text-slate-500 mt-0.5">
                        <span className="font-semibold text-slate-700">Sequential</span> draws from batches in order (drag to reorder).{' '}
                        <span className="font-semibold text-slate-700">Distributed</span> lets you split amounts freely across batches.
                    </p>
                </div>
                <button onClick={primeBatchOrders} className={ui.button.subtle}>
                    ↻ Auto-assign All (FIFO)
                </button>
            </div>

            <div className="space-y-3">
                {product.ingredients.map((ing) => {
                    const cfg = batchConfigData[ing.item_id];
                    if (!cfg) return null;
                    const pct = cfg.needed > 0 ? Math.min(100, (cfg.sourced / cfg.needed) * 100) : 0;
                    const mode = modeFor(ing.item_id);

                    return (
                        <div key={ing.id} className="border border-slate-200 rounded-lg overflow-hidden">
                            {/* ── Ingredient header ─────────────────────── */}
                            <div className="px-3 py-2.5 bg-slate-50 border-b border-slate-200">
                                <div className="flex items-center justify-between mb-1.5">
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-semibold text-slate-800">{cfg.itemName}</p>
                                        <span
                                            className={[
                                                'text-[10px] font-semibold px-1.5 py-0.5 rounded-full',
                                                cfg.fulfilled
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : cfg.needed > 0
                                                        ? 'bg-amber-100 text-amber-700'
                                                        : 'bg-slate-100 text-slate-500',
                                            ].join(' ')}
                                        >
                                            {cfg.fulfilled ? '✓ Fulfilled' : cfg.needed > 0 ? 'Unfulfilled' : 'None needed'}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {/* Mode toggle */}
                                        <div className="flex items-center bg-slate-200/60 rounded-md p-0.5 text-[10px] font-semibold">
                                            <button
                                                onClick={() => setMode(ing.item_id, 'sequential')}
                                                className={[
                                                    'px-2 py-0.5 rounded transition-colors',
                                                    mode === 'sequential'
                                                        ? 'bg-white text-slate-900 shadow-sm'
                                                        : 'text-slate-500 hover:text-slate-700',
                                                ].join(' ')}
                                            >
                                                Sequential
                                            </button>
                                            <button
                                                onClick={() => setMode(ing.item_id, 'distributed')}
                                                className={[
                                                    'px-2 py-0.5 rounded transition-colors',
                                                    mode === 'distributed'
                                                        ? 'bg-white text-slate-900 shadow-sm'
                                                        : 'text-slate-500 hover:text-slate-700',
                                                ].join(' ')}
                                            >
                                                Distributed
                                            </button>
                                        </div>
                                        {mode === 'sequential' && (
                                            <button
                                                onClick={() => autoAssignIngredient(ing.item_id)}
                                                className={ui.button.ghost}
                                            >
                                                ↻ FIFO
                                            </button>
                                        )}
                                        {mode === 'distributed' && (
                                            <button
                                                onClick={() => autoDistributeEvenly(ing.item_id)}
                                                className={ui.button.ghost}
                                            >
                                                ⚖ Even
                                            </button>
                                        )}
                                        <button
                                            onClick={() => resetIngredient(ing.item_id)}
                                            className={ui.button.ghost}
                                            title="Reset this ingredient's batch config"
                                        >
                                            ✕ Reset
                                        </button>
                                    </div>
                                </div>

                                {/* Stats */}
                                <div className="flex items-center gap-4 text-[11px] text-slate-500">
                                    <span>
                                        Need{' '}
                                        <span className="font-semibold text-slate-700">{cfg.needed.toFixed(4)}</span>{' '}
                                        {cfg.unit}
                                    </span>
                                    <span>
                                        Sourced{' '}
                                        <span
                                            className={[
                                                'font-semibold',
                                                cfg.fulfilled ? 'text-emerald-600' : 'text-amber-600',
                                            ].join(' ')}
                                        >
                                            {cfg.sourced.toFixed(4)}
                                        </span>{' '}
                                        {cfg.unit}
                                    </span>
                                    {!cfg.fulfilled && cfg.needed > 0 && (
                                        <span>
                                            Shortfall{' '}
                                            <span className="font-semibold text-rose-600">
                                                {(cfg.needed - cfg.sourced).toFixed(4)}
                                            </span>{' '}
                                            {cfg.unit}
                                        </span>
                                    )}
                                </div>

                                {/* Progress bar */}
                                <div className="mt-1.5 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                    <div
                                        className={[
                                            'h-full rounded-full transition-all',
                                            cfg.fulfilled ? 'bg-emerald-500' : 'bg-amber-500',
                                        ].join(' ')}
                                        style={{ width: `${pct}%` }}
                                    />
                                </div>
                            </div>

                            {/* ── Batch table ───────────────────────────── */}
                            <div className="overflow-auto">
                                <table className="min-w-full text-xs">
                                    <thead className="bg-slate-100/70 text-slate-500 uppercase tracking-wide text-[10px]">
                                        <tr>
                                            {mode === 'sequential' && (
                                                <th className="py-1.5 px-2 text-left w-14">#</th>
                                            )}
                                            <th className="py-1.5 px-2 text-left">Batch</th>
                                            <th className="py-1.5 px-2 text-left">Date</th>
                                            <th className="py-1.5 px-2 text-right">In stock</th>
                                            <th className="py-1.5 px-2 text-center">
                                                {mode === 'distributed' ? 'Allocate' : '→ Take'}
                                            </th>
                                            <th className="py-1.5 px-2 text-right">
                                                {mode === 'distributed' ? 'Resolved' : 'Still needed'}
                                            </th>
                                            <th className="py-1.5 px-2 text-right">Cost/unit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {cfg.batches.map((b) => (
                                            <tr
                                                key={b.line.id}
                                                draggable={mode === 'sequential'}
                                                onDragStart={() => handleDragStart(ing.item_id, b.line.id)}
                                                onDragEnter={() => handleDragEnter(ing.item_id, b.line.id)}
                                                onDragEnd={handleDragEnd}
                                                onDragOver={(e) => e.preventDefault()}
                                                className={[
                                                    'border-b border-slate-100 last:border-0 transition-colors',
                                                    mode === 'sequential' ? 'cursor-grab active:cursor-grabbing' : '',
                                                    b.isFull ? 'bg-rose-50/60' : b.isPartial ? 'bg-amber-50/60' : '',
                                                ].join(' ')}
                                            >
                                                {mode === 'sequential' && (
                                                    <td className="py-1.5 px-2">
                                                        <div className="flex items-center gap-1">
                                                            <span
                                                                className="text-slate-300 select-none cursor-grab"
                                                                title="Drag to reorder"
                                                            >
                                                                ⠿
                                                            </span>
                                                            <input
                                                                type="number"
                                                                value={orderFor(ing.item_id, b.line.id)}
                                                                onChange={(e) =>
                                                                    setOrder(
                                                                        ing.item_id,
                                                                        b.line.id,
                                                                        e.target.value ? parseInt(e.target.value, 10) : '',
                                                                    )
                                                                }
                                                                className="w-10 border border-slate-200 rounded px-1 py-0.5 text-center text-slate-800 text-xs"
                                                                placeholder="#"
                                                                min="1"
                                                            />
                                                        </div>
                                                    </td>
                                                )}
                                                <td className="py-1.5 px-2 font-mono text-slate-800">
                                                    {b.line.batch?.batch_code ?? `#${b.line.id}`}
                                                </td>
                                                <td className="py-1.5 px-2 text-slate-600">
                                                    {fmtDate(batchDate(b.line))}
                                                </td>
                                                <td className="py-1.5 px-2 text-right tabular-nums text-slate-600">
                                                    {b.available.toFixed(4)}
                                                </td>
                                                {mode === 'distributed' ? (
                                                    <td className="py-1.5 px-2 text-center">
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            max={b.available}
                                                            step="1"
                                                            value={distributedAmounts[ing.item_id]?.[b.line.id] ?? ''}
                                                            onChange={(e) =>
                                                                setDistAmount(ing.item_id, b.line.id, e.target.value)
                                                            }
                                                            className="w-20 border border-slate-200 rounded px-1.5 py-0.5 text-center text-xs text-slate-800"
                                                            placeholder="0"
                                                        />
                                                    </td>
                                                ) : (
                                                    <td className="py-1.5 px-2 text-center tabular-nums">
                                                        {b.take > 1e-9 ? (
                                                            <span
                                                                className={[
                                                                    'inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded',
                                                                    b.isFull
                                                                        ? 'bg-rose-100 text-rose-700 font-bold'
                                                                        : 'bg-amber-100 text-amber-700 font-semibold',
                                                                ].join(' ')}
                                                            >
                                                                → {b.take.toFixed(4)}
                                                            </span>
                                                        ) : (
                                                            <span className="text-slate-300">—</span>
                                                        )}
                                                    </td>
                                                )}
                                                <td className="py-1.5 px-2 text-right tabular-nums">
                                                    {b.take > 1e-9 ? (
                                                        b.stillNeeded <= 1e-9 ? (
                                                            <span className="font-semibold text-emerald-600">
                                                                0.0000 ✓
                                                            </span>
                                                        ) : (
                                                            <span className="text-slate-700">
                                                                {b.stillNeeded.toFixed(4)}
                                                            </span>
                                                        )
                                                    ) : (
                                                        <span className="text-slate-300">—</span>
                                                    )}
                                                </td>
                                                <td className="py-1.5 px-2 text-right tabular-nums text-slate-500">
                                                    {parseFloat(b.line.cost_per_unit).toFixed(2)}
                                                </td>
                                            </tr>
                                        ))}
                                        {!cfg.batches.length && (
                                            <tr>
                                                <td
                                                    colSpan={mode === 'sequential' ? 7 : 6}
                                                    className="py-4 text-center text-slate-400"
                                                >
                                                    No batches available for this ingredient.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

export default BatchConfigPanel;
