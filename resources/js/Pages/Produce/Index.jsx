import React, { useMemo, useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ui } from '@/theme';

const ProduceIndex = ({ products }) => {
    const { props } = usePage();
    const [selectedId, setSelectedId] = useState(null);
    const [batchOrders, setBatchOrders] = useState({}); // { [itemId]: [{id, order}] }

    const form = useForm({
        product_id: '',
        quantity: 1,
        notes: '',
        journal_date: '',
        batch_orders: {},
    });

    const product = useMemo(() => products.find((p) => p.id === selectedId) ?? null, [products, selectedId]);

    const maxBuild = useMemo(() => {
        if (!product || !product.ingredients?.length) return 0;
        let min = Infinity;
        product.ingredients.forEach((ing) => {
            const needPerUnit = parseFloat(ing.quantity) || 0;
            const available = parseFloat(ing.item?.current_stock) || 0;
            if (needPerUnit <= 0) return;
            min = Math.min(min, available / needPerUnit);
        });
        return Number.isFinite(min) ? min : 0;
    }, [product]);

    const ingredientStatus = (ing) => {
        const qty = parseFloat(form.data.quantity) || 0;
        const required = parseFloat(ing.quantity) * qty;
        const available = parseFloat(ing.item?.current_stock) || 0;
        return {
            required: Number.isFinite(required) ? required : 0,
            available,
            ok: available + 1e-9 >= required,
            unit: ing.item?.unit?.abbreviation ?? '',
        };
    };

    const select = (p) => {
        setSelectedId(p.id);
        form.setData({ ...form.data, product_id: p.id, quantity: 1, notes: '' });
        form.clearErrors();
        primeBatchOrders(p);
    };

    const submit = () => {
        form.setData('product_id', selectedId);
        form.setData('journal_date', props.currentDate);
        form.setData('batch_orders', normalisedOrders());
        form.post('/produce', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setSelectedId(null);
                setBatchOrders({});
            },
        });
    };

    const clearSelection = () => {
        form.reset();
        setSelectedId(null);
        setBatchOrders({});
    };

    const currency = (n) => parseFloat(n ?? 0).toFixed(2);
    const fmtDate = (d) => new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

    const primeBatchOrders = (p) => {
        const next = {};
        if (p?.ingredients?.length) {
            p.ingredients.forEach((ing) => {
                const lines = [...(ing.item?.restock_batch_items ?? [])].sort((a, b) => {
                    const da = new Date(a.batch?.journal?.journal_date ?? a.batch?.created_at ?? a.created_at ?? 0).getTime();
                    const db = new Date(b.batch?.journal?.journal_date ?? b.batch?.created_at ?? b.created_at ?? 0).getTime();
                    return da - db;
                });
                next[ing.item_id] = lines.map((line, idx) => ({ id: line.id, order: idx + 1 }));
            });
        }
        setBatchOrders(next);
    };

    const orderFor = (itemId, lineId) => batchOrders[itemId]?.find((o) => o.id === lineId)?.order ?? '';

    const setOrder = (itemId, lineId, val) => {
        const list = batchOrders[itemId] ?? [];
        const existing = list.find((o) => o.id === lineId);
        let nextList;
        if (existing) {
            nextList = list.map((o) => (o.id === lineId ? { ...o, order: val } : o));
        } else {
            nextList = [...list, { id: lineId, order: val }];
        }
        setBatchOrders({ ...batchOrders, [itemId]: nextList });
    };

    const normalisedOrders = () => {
        const out = {};
        Object.entries(batchOrders || {}).forEach(([itemId, orders]) => {
            const seq = [...orders]
                .filter((o) => o.order)
                .sort((a, b) => a.order - b.order)
                .map((o) => o.id);
            if (seq.length) out[itemId] = seq;
        });
        return out;
    };

    return (
        <>
            <Head title="Produce" />

            <div className="flex items-center justify-between mb-5">
                <div>
                    <h1 className={ui.heading}>Produce</h1>
                    <p className="text-xs text-slate-500">Convert raw ingredients into finished products without exceeding ingredient stock.</p>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-[1.2fr_1fr] gap-4">
                <div className={[ui.card, 'p-4'].join(' ')}>
                    <div className="flex items-center justify-between mb-3">
                        <p className={ui.subheading}>Production Plan</p>
                        <p className="text-xs text-slate-500">Max build: {maxBuild.toFixed(2)}</p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                        <div>
                            <label className={ui.fieldLabel}>Product</label>
                            <select
                                value={selectedId ?? ''}
                                onChange={(e) => setSelectedId(e.target.value ? Number(e.target.value) : null)}
                                className={ui.input}
                            >
                                <option value="">— select product —</option>
                                {products.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className={ui.fieldLabel}>Quantity to produce (whole units)</label>
                            <input
                                type="number"
                                min="1"
                                step="1"
                                value={form.data.quantity}
                                onChange={(e) => form.setData('quantity', e.target.value)}
                                className={ui.input}
                                max={maxBuild > 0 ? maxBuild : undefined}
                            />
                            <p className="text-[11px] text-slate-500 mt-1">Must be a whole number and cannot exceed max build based on ingredients.</p>
                        </div>
                    </div>

                    {product && (
                        <div className="flex flex-wrap items-center gap-3 mb-3">
                            <span className="text-xs text-slate-500">
                                Current stock: <span className="font-semibold text-slate-900">{product.current_stock ?? 0}</span>
                            </span>
                            <span className="text-xs text-slate-500">
                                Recipe cost: <span className="font-semibold text-slate-900">{currency(product.computed_cost)}</span>
                            </span>
                            <span className="text-xs text-slate-500">
                                Selling price: <span className="font-semibold text-slate-900">{currency(product.selling_price) || 'auto'}</span>
                            </span>
                        </div>
                    )}

                    <div className="mb-3">
                        <label className={ui.fieldLabel}>Notes (optional)</label>
                        <input
                            type="text"
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            className={ui.input}
                            placeholder="Batch reference, operator, etc."
                        />
                    </div>

                    <div className="flex gap-2">
                        <button
                            className={ui.button.primary}
                            onClick={submit}
                            disabled={!product || form.processing || !form.data.quantity || form.data.quantity <= 0 || form.data.quantity > maxBuild}
                        >
                            Produce
                        </button>
                        <button onClick={clearSelection} className={ui.button.secondary}>
                            Clear
                        </button>
                    </div>

                    {form.errors.produce && <p className="text-xs text-rose-600 mt-2">{form.errors.produce}</p>}
                    {form.errors.quantity && <p className="text-xs text-rose-600 mt-2">{form.errors.quantity}</p>}
                </div>

                <div className={[ui.card, 'p-4'].join(' ')}>
                    <p className={[ui.subheading, 'mb-2'].join(' ')}>Ingredient Check</p>
                    {product && product.ingredients?.length ? (
                        <div className="border border-slate-200 rounded-lg overflow-hidden">
                            <table className="w-full text-sm">
                                <thead className={ui.tableHead}>
                                    <tr className="border-b border-slate-200">
                                        <th className="py-2 px-3 text-left">Ingredient</th>
                                        <th className="py-2 px-3 text-right">Need</th>
                                        <th className="py-2 px-3 text-right">Available</th>
                                        <th className="py-2 px-3 text-left">Batch plan</th>
                                        <th className="py-2 px-3 text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {product.ingredients.map((ing) => (
                                        <tr key={ing.id} className="border-b border-slate-100 last:border-0">
                                            <td className="py-2 px-3 text-slate-800">{ing.item?.name}</td>
                                            <td className="py-2 px-3 text-right tabular-nums text-slate-600">
                                                {ingredientStatus(ing).required.toFixed(4)} {ingredientStatus(ing).unit}
                                            </td>
                                            <td className="py-2 px-3 text-right tabular-nums text-slate-600">
                                                {ingredientStatus(ing).available.toFixed(4)} {ingredientStatus(ing).unit}
                                            </td>
                                            <td className="py-2 px-3 text-left text-xs text-slate-600">
                                                {batchOrders[ing.item_id]?.length ? (
                                                    <span>
                                                        Will use:{' '}
                                                        {batchOrders[ing.item_id]
                                                            .slice()
                                                            .sort((a, b) => a.order - b.order)
                                                            .map((o) => {
                                                                const line = ing.item?.restock_batch_items?.find((l) => l.id === o.id);
                                                                return (
                                                                    (line?.batch?.batch_code ?? `#${o.id}`) +
                                                                    (line?.batch?.created_at ? ` (${fmtDate(line.batch.created_at)})` : '')
                                                                );
                                                            })
                                                            .join(', ')}
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-400">No batches</span>
                                                )}
                                            </td>
                                            <td className="py-2 px-3 text-right">
                                                <span
                                                    className={[
                                                        'inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full',
                                                        ingredientStatus(ing).ok
                                                            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                            : 'bg-amber-50 text-amber-700 border border-amber-200',
                                                    ].join(' ')}
                                                >
                                                    {ingredientStatus(ing).ok ? 'OK' : 'Insufficient'}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="text-xs text-slate-400 py-6 text-center border border-dashed border-slate-200 rounded-lg">
                            Select a product to see ingredient requirements.
                        </div>
                    )}

                    {product && product.ingredients?.length && (
                        <div className="mt-4 border border-slate-200 rounded-lg overflow-hidden">
                            <div className="px-3 py-2 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                                <p className="text-xs font-semibold text-slate-700">Batch consumption plan</p>
                                <p className="text-[11px] text-slate-500">Oldest batches are prioritized by default; adjust order per ingredient.</p>
                            </div>
                            <div className="divide-y divide-slate-200">
                                {product.ingredients.map((ing) => (
                                    <div key={ing.id} className="p-3 space-y-2">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-sm font-semibold text-slate-800">{ing.item?.name}</p>
                                                <p className="text-[11px] text-slate-500">Item batches sorted by age</p>
                                            </div>
                                            <p className="text-xs text-slate-500">
                                                Need {ingredientStatus(ing).required.toFixed(4)} {ingredientStatus(ing).unit}
                                            </p>
                                        </div>

                                        <div className="overflow-auto">
                                            <table className="min-w-full text-xs">
                                                <thead className="bg-slate-100 text-slate-600 uppercase tracking-wide">
                                                    <tr>
                                                        <th className="py-1.5 px-2 text-left w-20">Order</th>
                                                        <th className="py-1.5 px-2 text-left">Batch</th>
                                                        <th className="py-1.5 px-2 text-left">Date added</th>
                                                        <th className="py-1.5 px-2 text-right">Remaining</th>
                                                        <th className="py-1.5 px-2 text-right">Unit Cost</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {(ing.item?.restock_batch_items ?? []).map((line) => (
                                                        <tr key={line.id} className="border-b border-slate-100 last:border-0">
                                                            <td className="py-1.5 px-2">
                                                                <input
                                                                    type="number"
                                                                    value={orderFor(ing.item_id, line.id)}
                                                                    onChange={(e) =>
                                                                        setOrder(
                                                                            ing.item_id,
                                                                            line.id,
                                                                            e.target.value ? parseInt(e.target.value, 10) : '',
                                                                        )
                                                                    }
                                                                    className="w-20 border border-slate-200 rounded px-2 py-1 text-slate-800"
                                                                    placeholder="#"
                                                                />
                                                            </td>
                                                            <td className="py-1.5 px-2 font-mono text-slate-800">{line.batch?.batch_code ?? `#${line.id}`}</td>
                                                            <td className="py-1.5 px-2 text-slate-600">
                                                                {line.batch?.journal?.journal_date
                                                                    ? fmtDate(line.batch.journal.journal_date)
                                                                    : line.batch?.created_at
                                                                        ? fmtDate(line.batch.created_at)
                                                                        : line.created_at
                                                                            ? fmtDate(line.created_at)
                                                                            : '—'}
                                                            </td>
                                                            <td className="py-1.5 px-2 text-right tabular-nums text-slate-700">
                                                                {parseFloat(line.quantity_added).toFixed(4)}
                                                            </td>
                                                            <td className="py-1.5 px-2 text-right tabular-nums text-slate-600">
                                                                {parseFloat(line.cost_per_unit).toFixed(4)}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                    {!(ing.item?.restock_batch_items ?? []).length && (
                                                        <tr>
                                                            <td colSpan={5} className="py-3 text-center text-slate-400">
                                                                No batches for this ingredient.
                                                            </td>
                                                        </tr>
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <div className={[ui.card, 'mt-4 p-4'].join(' ')}>
                <div className="flex items-center justify-between mb-2">
                    <p className={ui.subheading}>Products</p>
                    <p className="text-xs text-slate-500">Select to populate the production form.</p>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    {products.map((p) => (
                        <button
                            key={p.id}
                            onClick={() => select(p)}
                            className={`text-left transition-all rounded-xl overflow-hidden border shadow-sm ${
                                selectedId === p.id ? 'border-slate-900 ring-2 ring-slate-200 shadow-md' : 'border-slate-200 hover:border-slate-400 hover:shadow'
                            }`}
                        >
                            <div className="aspect-video bg-slate-100 overflow-hidden">
                                {p.image_path ? (
                                    <img src={`/storage/${p.image_path}`} className="w-full h-full object-cover" />
                                ) : (
                                    <div className="w-full h-full flex items-center justify-center text-slate-300 text-3xl select-none">◆</div>
                                )}
                            </div>
                            <div className="p-3">
                                <p className="text-sm font-semibold text-slate-900 truncate">{p.name}</p>
                                <p className="text-xs text-slate-500">Current stock: {p.current_stock ?? 0}</p>
                                <p className="text-xs text-slate-500">
                                    Max build now:{' '}
                                    {(() => {
                                        if (!p.ingredients?.length) return '—';
                                        let min = Infinity;
                                        p.ingredients.forEach((ing) => {
                                            const need = parseFloat(ing.quantity) || 0;
                                            const avail = parseFloat(ing.item?.current_stock) || 0;
                                            if (need > 0) min = Math.min(min, avail / need);
                                        });
                                        return Number.isFinite(min) ? min.toFixed(2) : '—';
                                    })()}
                                </p>
                            </div>
                        </button>
                    ))}
                </div>
            </div>
        </>
    );
};

export default ProduceIndex;
