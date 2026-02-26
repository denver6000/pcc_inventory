import React, { useMemo, useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ui } from '@/theme';

const PosIndex = ({ products }) => {
    const { props } = usePage();
    const [selectedIds, setSelectedIds] = useState([]);
    const [quantities, setQuantities] = useState({});
    const sellForm = useForm({ notes: '', journal_date: props.currentDate, items: [] });

    const effectivePrice = (product) => {
        const sp = parseFloat(product.selling_price);
        if (sp > 0) return sp;
        const markup = parseFloat(product.markup_percentage ?? 0);
        const cost = parseFloat(product.computed_cost ?? 0);
        if (markup > 0) return cost * (1 + markup / 100);
        return cost;
    };

    const selectedProducts = useMemo(
        () => products.filter((p) => selectedIds.includes(p.id)),
        [products, selectedIds],
    );

    const lineRows = useMemo(
        () => selectedProducts.map((p) => {
            const qty = Number(quantities[p.id] ?? 1);
            const price = effectivePrice(p);
            const stock = parseFloat(p.current_stock ?? 0);
            const validQty = Number.isInteger(qty) && qty >= 1;
            return {
                product: p,
                qty,
                stock,
                price,
                subtotal: price * (validQty ? qty : 0),
                canSell: validQty && stock >= qty,
            };
        }),
        [selectedProducts, quantities],
    );

    const canCheckout = useMemo(() => lineRows.length > 0 && lineRows.every((r) => r.canSell), [lineRows]);

    const grandTotal = useMemo(
        () => lineRows.reduce((sum, row) => sum + row.subtotal, 0).toFixed(2),
        [lineRows],
    );

    const toggleSelect = (product) => {
        setSelectedIds((prev) => {
            if (prev.includes(product.id)) {
                const next = prev.filter((id) => id !== product.id);
                setQuantities((qPrev) => {
                    const nextQ = { ...qPrev };
                    delete nextQ[product.id];
                    return nextQ;
                });
                return next;
            }
            setQuantities((qPrev) => ({ ...qPrev, [product.id]: qPrev[product.id] ?? 1 }));
            return [...prev, product.id];
        });
    };

    const clearSelection = () => {
        setSelectedIds([]);
        setQuantities({});
        sellForm.reset();
        sellForm.setData((data) => ({ ...data, notes: '', journal_date: props.currentDate, items: [] }));
    };

    const submit = () => {
        const itemsPayload = lineRows.map((row) => ({
            product_id: row.product.id,
            quantity: row.qty,
        }));

        sellForm
            .transform((data) => ({
                ...data,
                journal_date: props.currentDate,
                items: itemsPayload,
            }))
            .post('/checkout/bulk', {
                preserveScroll: true,
                onSuccess: clearSelection,
            });
    };

    const currency = (n) => parseFloat(n ?? 0).toFixed(2);
    const inputCls = ui.input;
    const btnPrimary = ui.button.primary;
    const btnGhost = ui.button.secondary;

    return (
        <>
            <Head title="POS" />

            <div className="flex items-center justify-between mb-5">
                <div>
                    <h1 className={ui.heading}>Point of Sale</h1>
                    <p className="text-xs text-slate-500">Multi-select products, assign quantities, then checkout in one action.</p>
                </div>
                <p className="text-xs text-slate-500">Date: {props.currentDate}</p>
            </div>

            {selectedProducts.length > 0 && (
                <div className={[ui.card, 'p-5 mb-5'].join(' ')}>
                    <div className="flex items-center justify-between mb-3">
                        <p className={ui.subheading}>Checkout Basket ({selectedProducts.length})</p>
                        <p className="text-xs text-slate-500">Grand total: <span className="font-semibold text-slate-900 tabular-nums">${grandTotal}</span></p>
                    </div>

                    <div className="space-y-2">
                        {lineRows.map((row) => (
                            <div key={row.product.id} className="grid grid-cols-[1fr_92px_120px_120px] gap-2 items-center border border-slate-100 rounded-lg p-2.5">
                                <div className="flex items-center gap-3 min-w-0">
                                    {row.product.image_path ? (
                                        <img
                                            src={`/storage/${row.product.image_path}`}
                                            className="h-10 w-10 object-cover flex-shrink-0 rounded-lg border border-slate-200"
                                        />
                                    ) : (
                                        <div className="h-10 w-10 bg-slate-100 rounded-lg flex items-center justify-center text-slate-400 text-sm flex-shrink-0">
                                            ◆
                                        </div>
                                    )}
                                    <div className="min-w-0">
                                        <p className="text-sm font-semibold text-slate-900 truncate">{row.product.name}</p>
                                        <p className="text-xs text-slate-500">
                                            Unit price: <span className="font-semibold tabular-nums text-slate-900">${currency(row.price)}</span>
                                            <span className="ml-2">Stock: <span className="font-semibold tabular-nums text-slate-900">{currency(row.stock)}</span></span>
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <label className={ui.fieldLabel}>Qty</label>
                                    <input
                                        type="number"
                                        min="1"
                                        step="1"
                                        inputMode="numeric"
                                        value={quantities[row.product.id] ?? 1}
                                        onChange={(e) => {
                                            const next = e.target.value;
                                            setQuantities((prev) => ({ ...prev, [row.product.id]: next }));
                                        }}
                                        className={inputCls}
                                    />
                                </div>

                                <div>
                                    <label className={ui.fieldLabel}>Subtotal</label>
                                    <div className="px-3 py-2 border border-slate-200 bg-slate-50 text-sm font-semibold tabular-nums rounded-lg">
                                        ${currency(row.subtotal)}
                                    </div>
                                </div>

                                <div className="text-right">
                                    <button type="button" onClick={() => toggleSelect(row.product)} className={ui.button.ghost}>
                                        Remove
                                    </button>
                                    {!row.canSell && (
                                        <p className="text-[11px] text-rose-600 mt-1">Insufficient stock / invalid qty</p>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="mt-4 grid grid-cols-1 md:grid-cols-[1fr_auto] gap-2 items-end">
                        <div>
                            <label className={ui.fieldLabel}>Notes</label>
                            <input
                                type="text"
                                value={sellForm.data.notes}
                                onChange={(e) => sellForm.setData('notes', e.target.value)}
                                className={inputCls}
                                placeholder="Optional"
                            />
                        </div>
                        <div className="flex gap-2">
                            <button
                                onClick={submit}
                                disabled={sellForm.processing || !canCheckout}
                                className={btnPrimary}
                                title={!canCheckout ? 'Fix quantities or stock issues first' : ''}
                            >
                                Confirm All Sales
                            </button>
                            <button onClick={clearSelection} className={btnGhost}>
                                Clear
                            </button>
                        </div>
                    </div>

                    {sellForm.errors.checkout && (
                        <p className="text-xs text-red-500 mt-2">{sellForm.errors.checkout}</p>
                    )}
                    {sellForm.errors.items && <p className="text-xs text-red-500 mt-2">{sellForm.errors.items}</p>}
                </div>
            )}

            {products.length ? (
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                    {products.map((p) => (
                        <button
                            key={p.id}
                            onClick={() => toggleSelect(p)}
                            className={`text-left transition-all rounded-xl overflow-hidden border shadow-sm ${
                                selectedIds.includes(p.id) ? 'border-slate-900 ring-2 ring-slate-200 shadow-md' : 'border-slate-200 hover:border-slate-400 hover:shadow'
                            }`}
                        >
                            <div className="aspect-square bg-slate-100 overflow-hidden">
                                {p.image_path ? (
                                    <img src={`/storage/${p.image_path}`} className="w-full h-full object-cover" />
                                ) : (
                                    <div className="w-full h-full flex items-center justify-center text-slate-300 text-4xl select-none">◆</div>
                                )}
                            </div>

                            <div className="p-2.5">
                                <p className="text-sm font-semibold truncate leading-tight text-slate-900">{p.name}</p>
                                <p className="text-xs tabular-nums mt-0.5 text-slate-600">
                                    {parseFloat(p.selling_price) > 0 ? (
                                        <>${currency(p.selling_price)}</>
                                    ) : parseFloat(p.markup_percentage ?? 0) > 0 ? (
                                        <>
                                            ${currency(parseFloat(p.computed_cost) * (1 + parseFloat(p.markup_percentage) / 100))}
                                            <span className="text-slate-400 text-xs"> +{p.markup_percentage}%</span>
                                        </>
                                    ) : (
                                        <>
                                            ${currency(p.computed_cost)}
                                            <span className="text-slate-400 text-xs"> auto</span>
                                        </>
                                    )}
                                </p>
                                <p className="text-[11px] text-slate-500 mt-1">
                                    Stock: <span className="font-semibold tabular-nums">{currency(p.current_stock)}</span>
                                </p>
                            </div>
                        </button>
                    ))}
                </div>
            ) : (
                <div className="py-20 text-center text-xs text-slate-400">
                    No products yet.
                    <a href="/products" className="underline hover:text-slate-700">
                        Create products
                    </a>{' '}
                    to use the POS.
                </div>
            )}
        </>
    );
};

export default PosIndex;
