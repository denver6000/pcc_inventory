import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ui } from '@/theme';

const ItemsIndex = ({ items, units }) => {
    const { props } = usePage();

    const [mode, setMode] = useState(null); // null | 'add' | id
    const [imagePreview, setImagePreview] = useState(null);
    const imgInput = useRef(null);
    const [infoItem, setInfoItem] = useState(null);
    const [consumeOpen, setConsumeOpen] = useState(false);
    const [consumeOrder, setConsumeOrder] = useState([]); // [{id, order}]

    const consumeForm = useForm({
        quantity: '',
        batch_order: [],
        journal_date: '',
    });

    const form = useForm({
        name: '',
        image: null,
        unit_id: '',
        cost_per_unit: 0,
        default_stock: '',
        journal_date: '',
    });

    const totalBatchQty = useMemo(
        () => (infoItem?.restock_batch_items ?? []).reduce((sum, line) => sum + (parseFloat(line.quantity_added) || 0), 0),
        [infoItem],
    );

    useEffect(() => {
        if (infoItem && infoItem.restock_batch_items) {
            setConsumeOrder(infoItem.restock_batch_items.map((line, idx) => ({ id: line.id, order: idx + 1 })));
        } else {
            setConsumeOrder([]);
        }
        consumeForm.reset();
        setConsumeOpen(false);
    }, [infoItem]);

    const openAdd = () => {
        setMode('add');
        form.reset();
        setImagePreview(null);
        form.setData((data) => ({
            ...data,
            name: '',
            image: null,
            unit_id: '',
            cost_per_unit: 0,
            default_stock: '',
            journal_date: '',
        }));
    };

    const openEdit = (item) => {
        setMode(item.id);
        form.setData((data) => ({
            ...data,
            name: item.name,
            image: null,
            unit_id: item.unit_id ?? '',
            cost_per_unit: item.cost_per_unit ?? 0,
            default_stock: item.default_stock,
            journal_date: '',
        }));
        setImagePreview(item.image_path ? `/storage/${item.image_path}` : null);
    };

    const closeForm = () => {
        setMode(null);
        form.reset();
        setImagePreview(null);
    };

    const handleImage = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        form.setData('image', file);
        setImagePreview(URL.createObjectURL(file));
    };

    const submit = () => {
        form.setData('journal_date', props.currentDate);
        if (mode === 'add') {
            form.post('/items', { preserveScroll: true, onSuccess: closeForm });
        } else {
            form.put(`/items/${mode}`, { preserveScroll: true, onSuccess: closeForm });
        }
    };

    const remove = (item) => {
        if (window.confirm(`Delete "${item.name}"?`)) {
            router.delete(`/items/${item.id}`);
        }
    };

    const batchQty = (item) => (item?.restock_batch_items ?? []).reduce((sum, line) => sum + (parseFloat(line.quantity_added) || 0), 0);
    const batchValue = (item) =>
        (item?.restock_batch_items ?? []).reduce(
            (sum, line) => sum + (parseFloat(line.quantity_added) || 0) * (parseFloat(line.cost_per_unit) || 0),
            0,
        );

    const fmtDate = (d) =>
        new Date(d).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });

    const openInfo = (item) => setInfoItem(item);
    const closeInfo = () => setInfoItem(null);

    const openConsume = () => {
        setConsumeOpen(true);
        if (!consumeOrder.length && infoItem?.restock_batch_items) {
            setConsumeOrder(infoItem.restock_batch_items.map((line, idx) => ({ id: line.id, order: idx + 1 })));
        }
    };

    const sortedOrders = () =>
        [...consumeOrder]
            .filter((o) => o.order && o.id)
            .sort((a, b) => a.order - b.order)
            .map((o) => o.id);

    const orderFor = (id) => consumeOrder.find((o) => o.id === id)?.order ?? '';

    const setOrder = (id, value) => {
        setConsumeOrder((prev) => {
            const next = [...prev];
            const idx = next.findIndex((o) => o.id === id);
            if (idx >= 0) {
                next[idx] = { ...next[idx], order: value };
            } else {
                next.push({ id, order: value });
            }
            return next;
        });
    };

    const submitConsume = () => {
        consumeForm.setData('batch_order', sortedOrders());
        consumeForm.setData('journal_date', props.currentDate);
        if (!sortedOrders().length) {
            consumeForm.setError('batch_order', 'Select at least one batch to consume from.');
            return;
        }
        consumeForm.post(`/items/${infoItem.id}/consume`, {
            preserveScroll: true,
            onSuccess: () => {
                consumeForm.reset();
                setConsumeOpen(false);
            },
        });
    };

    const inputClass = ui.input;
    const btnPrimary = ui.button.primary;
    const btnSecondary = ui.button.secondary;

    return (
        <>
            <Head title="Items" />

            <div className="flex items-center justify-between mb-5">
                <div>
                    <h1 className={ui.heading}>Items</h1>
                    <p className="text-xs text-slate-500">Manage stock images, units, and counts.</p>
                </div>
                {!mode && (
                    <button onClick={openAdd} className={btnPrimary}>
                        + New Item
                    </button>
                )}
            </div>

            {mode && (
                <div className={[ui.card, 'p-5 mb-5'].join(' ')}>
                    <p className={[ui.subheading, 'mb-3'].join(' ')}>{mode === 'add' ? 'New Item' : 'Edit Item'}</p>

                    <div className="flex flex-wrap items-end gap-3">
                        <div>
                            <label className={ui.fieldLabel}>Image</label>
                            <div
                                className="h-12 w-12 border border-slate-200 rounded-lg flex items-center justify-center cursor-pointer overflow-hidden hover:border-slate-400 transition-colors bg-slate-50"
                                onClick={() => imgInput.current?.click()}
                                title="Click to pick image"
                            >
                                {imagePreview ? <img src={imagePreview} className="h-full w-full object-cover" /> : <span className="text-slate-400 text-xl leading-none select-none">+</span>}
                            </div>
                            <input ref={imgInput} type="file" accept="image/*" onChange={handleImage} className="hidden" />
                        </div>

                        <div className="flex-1 min-w-40">
                            <label className={ui.fieldLabel}>
                                Name <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                className={inputClass}
                                placeholder="Item name"
                            />
                            {form.errors.name && <p className="text-xs text-red-500 mt-0.5">{form.errors.name}</p>}
                        </div>

                        <div className="w-40">
                            <label className={ui.fieldLabel}>Unit</label>
                            <select
                                value={form.data.unit_id}
                                onChange={(e) => form.setData('unit_id', e.target.value)}
                                className={inputClass}
                            >
                                <option value="">— none —</option>
                                {units.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name} ({u.abbreviation})
                                    </option>
                                ))}
                            </select>
                            {!units.length && (
                                <p className="text-xs text-gray-300 mt-0.5">
                                    <Link href="/units" className="underline hover:text-gray-500">
                                        Add units first
                                    </Link>
                                </p>
                            )}
                        </div>

                        <div className="w-28">
                            <label className={ui.fieldLabel}>Cost / unit</label>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.cost_per_unit}
                                onChange={(e) => form.setData('cost_per_unit', e.target.value)}
                                className={inputClass}
                                placeholder="0.00"
                            />
                            {form.errors.cost_per_unit && <p className="text-xs text-red-500 mt-0.5">{form.errors.cost_per_unit}</p>}
                        </div>

                        <div className="w-28">
                            <label className={ui.fieldLabel}>
                                Default Stock <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.default_stock}
                                onChange={(e) => form.setData('default_stock', e.target.value)}
                                className={inputClass}
                                placeholder="0"
                            />
                            {form.errors.default_stock && <p className="text-xs text-red-500 mt-0.5">{form.errors.default_stock}</p>}
                        </div>

                        <div className="flex gap-2 pb-px">
                            <button onClick={submit} disabled={form.processing} className={btnPrimary}>
                                {mode === 'add' ? 'Add' : 'Save'}
                            </button>
                            <button onClick={closeForm} className={btnSecondary}>
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            )}

            <div className={ui.table}>
                <table className="w-full">
                    <thead>
                        <tr className={`border-b border-slate-200 ${ui.tableHead}`}>
                            <th className="w-12 py-3 px-3"></th>
                            <th className="py-3 px-3 text-left">Name</th>
                            <th className="py-3 px-3 text-left">Unit</th>
                            <th className="py-3 px-3 text-right">Cost/unit</th>
                            <th className="py-3 px-3 text-right">Default</th>
                            <th className="py-3 px-3 text-right">Stock</th>
                            <th className="w-24 py-3 px-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => (
                            <tr
                                key={item.id}
                                className={`border-b border-slate-100 last:border-0 hover:bg-slate-50 transition-colors ${
                                    mode === item.id ? 'bg-slate-50' : ''
                                }`}
                            >
                                <td className="py-2 px-3">
                                    {item.image_path ? (
                                        <img src={`/storage/${item.image_path}`} className="h-9 w-9 object-cover" />
                                    ) : (
                                        <div className="h-9 w-9 bg-slate-100 rounded"></div>
                                    )}
                                </td>
                                <td className="py-2 px-3 text-sm font-medium text-slate-900">{item.name}</td>
                                <td className="py-2 px-3 text-sm text-slate-500">
                                    {item.unit ? <span>{item.unit.abbreviation}</span> : <span className="text-slate-300">—</span>}
                                </td>
                                <td className="py-2 px-3 text-sm text-right tabular-nums text-slate-500">
                                    {item.cost_per_unit > 0 ? <span>{item.cost_per_unit}</span> : <span className="text-slate-300">—</span>}
                                </td>
                                <td className="py-2 px-3 text-sm text-right tabular-nums font-medium text-slate-700">{item.default_stock}</td>
                                <td className="py-2 px-3 text-sm text-right tabular-nums font-medium text-slate-700">{item.current_stock}</td>
                                <td className="py-2 px-3 text-right whitespace-nowrap">
                                    <button
                                        onClick={() => openInfo(item)}
                                        className="text-xs text-slate-500 hover:text-slate-900 transition-colors mr-3"
                                        title="View batch breakdown"
                                    >
                                        ⓘ See Info
                                    </button>
                                    <button
                                        onClick={() => openEdit(item)}
                                        className="text-xs text-slate-500 hover:text-slate-900 transition-colors mr-3"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => remove(item)}
                                        className="text-xs text-slate-500 hover:text-rose-600 transition-colors"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {!items.length && (
                            <tr>
                                <td colSpan={6} className="py-14 text-center text-xs text-slate-400">
                                    No items yet. Click{' '}
                                    <button onClick={openAdd} className="underline hover:text-slate-700">
                                        New Item
                                    </button>{' '}
                                    to get started.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {infoItem && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-start justify-center z-50 p-4">
                    <div className={[ui.card, 'w-full max-w-3xl p-5 shadow-lg border border-slate-300'].join(' ')}>
                        <div className="flex justify-between items-start mb-3">
                            <div>
                                <p className="text-xs uppercase tracking-[0.12em] text-slate-500 font-semibold mb-1">Item Info</p>
                                <h2 className="text-lg font-semibold text-slate-900">{infoItem.name}</h2>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    Unit: {infoItem.unit?.abbreviation ?? '—'} · Cost/unit: {infoItem.cost_per_unit ?? 0}
                                </p>
                            </div>
                            <div className="text-right">
                                <p className="text-sm font-semibold text-slate-900">Current stock: {infoItem.current_stock}</p>
                                <p className="text-xs text-slate-500">Default: {infoItem.default_stock}</p>
                            </div>
                        </div>

                        <div className="bg-neutral-900/40 border border-neutral-800 rounded-xl p-4 space-y-3">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-neutral-400">Current stock</p>
                                    <p className="text-2xl font-semibold text-neutral-50">
                                        {infoItem.current_stock} {infoItem.unit?.abbreviation ?? ''}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        className="px-3 py-2 rounded-lg bg-white/5 border border-neutral-800 text-sm hover:bg-white/10"
                                        onClick={openConsume}
                                    >
                                        Consume manually
                                    </button>
                                </div>
                            </div>
                            <p className="text-sm text-neutral-400">Batch-backed stock. Use manual consume to choose which batches to draw down.</p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                            <div className="p-3 rounded-lg bg-slate-50 border border-slate-200">
                                <p className="text-[11px] uppercase tracking-[0.12em] text-slate-500 font-semibold mb-1">From batches</p>
                                <p className="text-xl font-semibold text-slate-900">{batchQty(infoItem).toFixed(2)}</p>
                                <p className="text-xs text-slate-500">Sum of batch quantities</p>
                            </div>
                            <div className="p-3 rounded-lg bg-slate-50 border border-slate-200">
                                <p className="text-[11px] uppercase tracking-[0.12em] text-slate-500 font-semibold mb-1">Valuation</p>
                                <p className="text-xl font-semibold text-slate-900">{batchValue(infoItem).toFixed(2)}</p>
                                <p className="text-xs text-slate-500">Σ qty × cost_per_unit</p>
                            </div>
                            <div className="p-3 rounded-lg bg-slate-50 border border-slate-200">
                                <p className="text-[11px] uppercase tracking-[0.12em] text-slate-500 font-semibold mb-1">Variance</p>
                                <p
                                    className={`text-xl font-semibold ${
                                        Math.abs(batchQty(infoItem) - (parseFloat(infoItem.current_stock) || 0)) < 0.0001
                                            ? 'text-emerald-700'
                                            : 'text-amber-700'
                                    }`}
                                >
                                    {(batchQty(infoItem) - (parseFloat(infoItem.current_stock) || 0)).toFixed(2)}
                                </p>
                                <p className="text-xs text-slate-500">Batches − current_stock</p>
                            </div>
                        </div>

                        <div className="mb-3 flex items-center justify-between">
                            <p className={ui.subheading}>Batch breakdown</p>
                            <button onClick={closeInfo} className={ui.button.ghost}>
                                Close
                            </button>
                        </div>

                        <div className="border border-slate-200 rounded-lg overflow-hidden">
                            <table className="w-full text-sm">
                                <thead className={ui.tableHead}>
                                    <tr className="border-b border-slate-200">
                                        <th className="py-2 px-3 text-left">Batch</th>
                                        <th className="py-2 px-3 text-left">Date</th>
                                        <th className="py-2 px-3 text-right">Remaining</th>
                                        <th className="py-2 px-3 text-right">Unit Cost</th>
                                        <th className="py-2 px-3 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(infoItem.restock_batch_items ?? []).map((line) => (
                                        <tr key={line.id} className="border-b border-slate-100 last:border-0">
                                            <td className="py-2 px-3 font-mono text-xs font-semibold text-slate-800">
                                                {line.batch?.batch_code ?? '—'}
                                            </td>
                                            <td className="py-2 px-3 text-xs text-slate-500">
                                                {line.batch?.created_at ? fmtDate(line.batch.created_at) : '—'}
                                            </td>
                                            <td className="py-2 px-3 text-right tabular-nums text-slate-700">
                                                {parseFloat(line.quantity_added).toFixed(4)}
                                            </td>
                                            <td className="py-2 px-3 text-right tabular-nums text-slate-500">
                                                {parseFloat(line.cost_per_unit).toFixed(4)}
                                            </td>
                                            <td className="py-2 px-3 text-right tabular-nums font-medium text-slate-900">
                                                {parseFloat(line.subtotal).toFixed(4)}
                                            </td>
                                        </tr>
                                    ))}
                                    {!(infoItem.restock_batch_items ?? []).length && (
                                        <tr>
                                            <td colSpan={5} className="py-6 text-center text-xs text-slate-400">
                                                No batch records for this item.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {consumeOpen && (
                            <div className="mt-4 border border-slate-300 rounded-lg p-4 bg-white">
                                <div className="flex items-center justify-between mb-2">
                                    <p className={ui.subheading}>Manual consume</p>
                                    <button onClick={() => setConsumeOpen(false)} className={ui.button.ghost}>
                                        Close
                                    </button>
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                                    <div>
                                        <label className={ui.fieldLabel}>
                                            Quantity <span className="text-red-400">*</span>
                                        </label>
                                        <input
                                            type="number"
                                            min="0.0001"
                                            step="0.0001"
                                            value={consumeForm.data.quantity}
                                            onChange={(e) => consumeForm.setData('quantity', e.target.value)}
                                            className={ui.input}
                                            placeholder="0"
                                        />
                                        {consumeForm.errors.quantity && (
                                            <p className="text-xs text-red-500 mt-0.5">{consumeForm.errors.quantity}</p>
                                        )}
                                    </div>
                                    <div>
                                        <label className={ui.fieldLabel}>Batch order</label>
                                        <p className="text-xs text-slate-500">
                                            Set the order in which batches will be consumed.
                                        </p>
                                    </div>
                                </div>

                                <div className="border border-slate-200 rounded-lg overflow-hidden">
                                    <table className="w-full text-sm">
                                        <thead className={ui.tableHead}>
                                            <tr className="border-b border-slate-200">
                                                <th className="py-2 px-3 text-left">Batch</th>
                                                <th className="py-2 px-3 text-left">Date</th>
                                                <th className="py-2 px-3 text-right">Remaining</th>
                                                <th className="py-2 px-3 text-right">Order</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {(infoItem.restock_batch_items ?? []).map((line) => (
                                                <tr key={line.id} className="border-b border-slate-100 last:border-0">
                                                    <td className="py-2 px-3 font-mono text-xs font-semibold text-slate-800">
                                                        {line.batch?.batch_code ?? '—'}
                                                    </td>
                                                    <td className="py-2 px-3 text-xs text-slate-500">
                                                        {line.batch?.created_at ? fmtDate(line.batch.created_at) : '—'}
                                                    </td>
                                                    <td className="py-2 px-3 text-right tabular-nums text-slate-700">
                                                        {parseFloat(line.quantity_added).toFixed(4)}
                                                    </td>
                                                    <td className="py-2 px-3 text-right tabular-nums text-slate-700">
                                                        <input
                                                            type="number"
                                                            value={orderFor(line.id)}
                                                            onChange={(e) => setOrder(line.id, e.target.value ? parseInt(e.target.value, 10) : '')}
                                                            className="w-20 border border-slate-200 rounded px-2 py-1 text-sm"
                                                            placeholder="#"
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                            {consumeForm.errors.batch_order && (
                                                <tr>
                                                    <td colSpan={4} className="py-2 px-3 text-xs text-red-500">
                                                        {consumeForm.errors.batch_order}
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                <div className="flex gap-2 pt-3">
                                    <button
                                        onClick={submitConsume}
                                        disabled={consumeForm.processing}
                                        className={ui.button.primary}
                                    >
                                        Consume
                                    </button>
                                    <button onClick={() => setConsumeOpen(false)} className={ui.button.secondary}>
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </>
    );
};

export default ItemsIndex;
