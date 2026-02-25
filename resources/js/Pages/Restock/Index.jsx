import React, { useMemo, useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ui } from '@/theme';

const RestockIndex = ({ batches, items }) => {
    const { props } = usePage();
    const [mode, setMode] = useState(null); // null | 'add'
    const [expandedId, setExpandedId] = useState(null);
    const [showRange, setShowRange] = useState(false);
    const [cols, setCols] = useState({ date: true, notes: true, count: true, total: true });

    const form = useForm({
        journal_date: '',
        notes: '',
        items: [],
    });

    const liveTotal = useMemo(
        () =>
            form.data.items.reduce((sum, line) => {
                const item = getItem(line.item_id);
                return sum + (parseFloat(line.quantity_added) || 0) * (parseFloat(item?.cost_per_unit) || 0);
            }, 0),
        [form.data.items, items],
    );

    const getItem = (id) => items.find((i) => i.id == id) ?? null;

    const lineSubtotal = (line) => {
        const item = getItem(line.item_id);
        return (parseFloat(line.quantity_added) || 0) * (parseFloat(item?.cost_per_unit) || 0);
    };

    const rangeMax = (line) => {
        const item = getItem(line.item_id);
        return item ? Math.max((parseFloat(item.default_stock) || 0) * 2, 100) : 100;
    };

    const visibleColCount = useMemo(() => Object.values(cols).filter(Boolean).length, [cols]);

    const openAdd = () => {
        setMode('add');
        form.reset();
        form.setData('items', [{ item_id: '', quantity_added: '' }]);
    };

    const closeForm = () => {
        setMode(null);
        form.reset();
        form.setData('items', []);
    };

    const addLine = () => form.setData('items', [...form.data.items, { item_id: '', quantity_added: '' }]);
    const removeLine = (idx) => {
        const next = [...form.data.items];
        next.splice(idx, 1);
        form.setData('items', next);
    };

    const submitBatch = () => {
        form.setData('journal_date', props.currentDate);
        form.post('/restock', { preserveScroll: true, onSuccess: closeForm });
    };

    const toggleExpand = (id) => setExpandedId(expandedId === id ? null : id);

    const pillBtn = (active) => ui.pill(active);
    const currency = (n) => parseFloat(n ?? 0).toFixed(2);
    const fmt = (d) =>
        new Date(d).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });

    return (
        <>
            <Head title="Restock" />

            <div className="flex items-center justify-between mb-5">
                <div>
                    <h1 className={ui.heading}>Restock</h1>
                    <p className="text-xs text-slate-500">Record batches with snapshot costs and live totals.</p>
                </div>
                {!mode && (
                    <button onClick={openAdd} className={ui.button.primary}>
                        + New Batch
                    </button>
                )}
            </div>

            {mode === 'add' && (
                <div className={[ui.card, 'p-5 mb-5'].join(' ')}>
                    <p className={[ui.subheading, 'mb-4'].join(' ')}>New Restock Batch</p>

                    <div className="mb-4">
                        <label className={ui.fieldLabel}>Notes</label>
                        <input
                            type="text"
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            className={ui.input}
                            placeholder="Optional — e.g. supplier name, PO number"
                        />
                    </div>

                    <div className="mb-4">
                        <div className="flex items-center justify-between mb-2">
                            <p className={ui.subheading}>Items to Restock</p>
                            <div className="flex items-center gap-2">
                                <button type="button" onClick={() => setShowRange(!showRange)} className={pillBtn(showRange)} title="Toggle quantity range sliders">
                                    {showRange ? '⊟ Hide sliders' : '⊞ Show sliders'}
                                </button>
                                <button type="button" onClick={addLine} className="text-xs text-slate-500 hover:text-slate-900 transition-colors">
                                    + Add item
                                </button>
                            </div>
                        </div>

                        {!form.data.items.length ? (
                            <div className="text-xs text-slate-400 py-3 text-center border border-dashed border-slate-200 rounded-lg bg-slate-50">
                                No items — click "+ Add item" above.
                            </div>
                        ) : (
                            <>
                                <div
                                    className={`grid gap-2 mb-1 text-xs text-gray-300 ${
                                        showRange ? 'grid-cols-[1fr_11rem_7rem_7rem_1.5rem]' : 'grid-cols-[1fr_7rem_7rem_7rem_1.5rem]'
                                    }`}
                                >
                                    <span>Stock Item</span>
                                    <span>Qty Added</span>
                                    <span>Unit Cost</span>
                                    <span className="text-right">Subtotal</span>
                                    <span></span>
                                </div>

                                {form.data.items.map((line, idx) => (
                                    <div
                                        key={idx}
                                        className={`grid gap-2 mb-2 items-center ${
                                            showRange ? 'grid-cols-[1fr_11rem_7rem_7rem_1.5rem]' : 'grid-cols-[1fr_7rem_7rem_7rem_1.5rem]'
                                        }`}
                                    >
                                        <div>
                                            <select
                                                value={line.item_id}
                                                onChange={(e) => {
                                                    const next = [...form.data.items];
                                                    next[idx] = { ...next[idx], item_id: e.target.value };
                                                    form.setData('items', next);
                                                }}
                                                className={ui.input}
                                            >
                                                <option value="">— select item —</option>
                                                {items.map((item) => (
                                                    <option key={item.id} value={item.id}>
                                                        {item.name}
                                                        {item.unit ? ` (${item.unit.abbreviation})` : ''}
                                                    </option>
                                                ))}
                                            </select>
                                            {form.errors[`items.${idx}.item_id`] && (
                                                <p className="text-xs text-red-500 mt-0.5">{form.errors[`items.${idx}.item_id`]}</p>
                                            )}
                                        </div>

                                        <div>
                                            {showRange ? (
                                                <div className="flex items-center gap-1.5">
                                                    <input
                                                        type="range"
                                                        value={line.quantity_added}
                                                        min="0"
                                                        max={rangeMax(line)}
                                                        step="0.01"
                                                        onChange={(e) => {
                                                            const next = [...form.data.items];
                                                            next[idx] = { ...next[idx], quantity_added: e.target.value };
                                                            form.setData('items', next);
                                                        }}
                                                        className="flex-1 accent-black h-1"
                                                    />
                                                    <input
                                                        type="number"
                                                        value={line.quantity_added}
                                                        min="0.0001"
                                                        step="0.001"
                                                        onChange={(e) => {
                                                            const next = [...form.data.items];
                                                            next[idx] = { ...next[idx], quantity_added: e.target.value };
                                                            form.setData('items', next);
                                                        }}
                                                        className="w-16 border border-gray-200 focus:border-gray-500 focus:outline-none px-2 py-1.5 text-sm bg-white"
                                                        placeholder="0"
                                                    />
                                                </div>
                                            ) : (
                                                <input
                                                    type="number"
                                                    value={line.quantity_added}
                                                    min="0.0001"
                                                    step="0.001"
                                                    onChange={(e) => {
                                                        const next = [...form.data.items];
                                                        next[idx] = { ...next[idx], quantity_added: e.target.value };
                                                        form.setData('items', next);
                                                    }}
                                                    className={ui.input}
                                                    placeholder="0"
                                                />
                                            )}
                                            {form.errors[`items.${idx}.quantity_added`] && (
                                                <p className="text-xs text-red-500 mt-0.5">{form.errors[`items.${idx}.quantity_added`]}</p>
                                            )}
                                        </div>

                                        <div className="px-2.5 py-1.5 border border-gray-100 bg-gray-50 text-sm tabular-nums text-gray-400">
                                            {currency(getItem(line.item_id)?.cost_per_unit ?? 0)}
                                        </div>

                                        <div className="px-2.5 py-1.5 text-sm tabular-nums text-right font-medium">
                                            {currency(lineSubtotal(line))}
                                        </div>

                                        <button
                                            type="button"
                                            onClick={() => removeLine(idx)}
                                            className="text-xs text-gray-300 hover:text-red-500 transition-colors text-center"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                ))}

                                <div
                                    className={`grid gap-2 pt-2 mt-1 border-t border-gray-100 ${
                                        showRange ? 'grid-cols-[1fr_11rem_7rem_7rem_1.5rem]' : 'grid-cols-[1fr_7rem_7rem_7rem_1.5rem]'
                                    }`}
                                >
                                    <span></span>
                                    <span></span>
                                    <p className="text-xs text-gray-400 self-center text-right pr-2">Batch Total</p>
                                    <p className="text-sm font-semibold tabular-nums text-right">{currency(liveTotal)}</p>
                                    <span></span>
                                </div>
                            </>
                        )}
                    </div>

                    <div className="flex gap-2 pt-1">
                        <button onClick={submitBatch} disabled={form.processing || !form.data.items.length} className={ui.button.primary}>
                            Commit Batch
                        </button>
                        <button onClick={closeForm} className={ui.button.secondary}>
                            Cancel
                        </button>
                    </div>
                </div>
            )}

            <div className="flex items-center gap-1.5 mb-2 flex-wrap">
                <span className="text-xs text-slate-400 mr-0.5">Columns:</span>
                <button onClick={() => setCols({ ...cols, date: !cols.date })} className={pillBtn(cols.date)}>
                    Date
                </button>
                <button onClick={() => setCols({ ...cols, notes: !cols.notes })} className={pillBtn(cols.notes)}>
                    Notes
                </button>
                <button onClick={() => setCols({ ...cols, count: !cols.count })} className={pillBtn(cols.count)}>
                    Items
                </button>
                <button onClick={() => setCols({ ...cols, total: !cols.total })} className={pillBtn(cols.total)}>
                    Total Cost
                </button>
            </div>

            <div className={ui.table}>
                <table className="w-full">
                    <thead>
                        <tr className={`border-b border-slate-200 ${ui.tableHead}`}>
                            <th className="py-3 px-3 text-left whitespace-nowrap">Batch ID</th>
                            {cols.date && <th className="py-3 px-3 text-left whitespace-nowrap">Date</th>}
                            {cols.notes && <th className="py-3 px-3 text-left">Notes</th>}
                            {cols.count && <th className="py-3 px-3 text-right">Items</th>}
                            {cols.total && <th className="py-3 px-3 text-right">Total Cost</th>}
                            <th className="w-8 py-3 px-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {batches.map((batch) => (
                            <React.Fragment key={batch.id}>
                                <tr
                                    className={`border-b border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer ${
                                        expandedId === batch.id ? 'bg-slate-50' : ''
                                    }`}
                                    onClick={() => toggleExpand(batch.id)}
                                >
                                    <td className="py-2 px-3">
                                        <span className="font-mono text-xs font-semibold tracking-wide text-slate-800 bg-slate-100 px-1.5 py-0.5 rounded">
                                            {batch.batch_code}
                                        </span>
                                    </td>
                                    {cols.date && <td className="py-2 px-3 text-sm tabular-nums text-slate-500 whitespace-nowrap">{fmt(batch.created_at)}</td>}
                                    {cols.notes && <td className="py-2 px-3 text-sm text-slate-600">{batch.notes || '—'}</td>}
                                    {cols.count && <td className="py-2 px-3 text-sm text-right tabular-nums text-slate-600">{batch.items.length}</td>}
                                    {cols.total && (
                                        <td className="py-2 px-3 text-sm text-right tabular-nums font-semibold text-slate-900">
                                            {currency(batch.total_cost)}
                                        </td>
                                    )}
                                    <td className="py-2 px-3 text-center">
                                        <span className="text-xs text-slate-400 select-none">{expandedId === batch.id ? '▲' : '▼'}</span>
                                    </td>
                                </tr>

                                {expandedId === batch.id && (
                                    <tr>
                                        <td colSpan={visibleColCount + 2} className="px-6 pb-3 pt-1 bg-slate-50 border-b border-slate-100">
                                            <p className="text-xs text-slate-500 mb-2">
                                                Batch <span className="font-mono font-semibold text-slate-800">{batch.batch_code}</span>
                                                {batch.notes && <span className="ml-2 text-slate-400">— {batch.notes}</span>}
                                            </p>
                                            <table className="w-full">
                                                <thead>
                                                    <tr>
                                                        <th className="text-left text-xs text-slate-400 font-normal pb-1">Item</th>
                                                        <th className="text-right text-xs text-slate-400 font-normal pb-1">Qty Added</th>
                                                        <th className="text-right text-xs text-slate-400 font-normal pb-1">Unit Cost</th>
                                                        <th className="text-right text-xs text-slate-400 font-normal pb-1">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {batch.items.map((bi) => (
                                                        <tr key={bi.id} className="border-t border-slate-100">
                                                            <td className="py-1.5 text-sm text-slate-800">
                                                                {bi.item.name}
                                                                {bi.item.unit && (
                                                                    <span className="text-xs text-slate-400 ml-0.5">{bi.item.unit.abbreviation}</span>
                                                                )}
                                                            </td>
                                                            <td className="py-1.5 text-sm text-right tabular-nums text-emerald-700 font-semibold">+{bi.quantity_added}</td>
                                                            <td className="py-1.5 text-sm text-right tabular-nums text-slate-500">{currency(bi.cost_per_unit)}</td>
                                                            <td className="py-1.5 text-sm text-right tabular-nums font-semibold text-slate-900">{currency(bi.subtotal)}</td>
                                                        </tr>
                                                    ))}
                                                    <tr className="border-t border-slate-200">
                                                        <td colSpan={3} className="pt-2 pb-0.5 text-xs text-slate-500 text-right pr-3 font-medium">
                                                            Batch Total
                                                        </td>
                                                        <td className="pt-2 pb-0.5 text-sm text-right tabular-nums font-semibold text-slate-900">
                                                            {currency(batch.total_cost)}
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                )}
                            </React.Fragment>
                        ))}

                        {!batches.length && (
                            <tr>
                                <td colSpan={visibleColCount + 1} className="py-14 text-center text-xs text-gray-300">
                                    No restock batches yet.
                                    <button onClick={openAdd} className="underline hover:text-gray-500">
                                        Create the first one
                                    </button>
                                    .
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
};

export default RestockIndex;
