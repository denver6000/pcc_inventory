import React, { useMemo, useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ui } from '@/theme';

const PosIndex = ({ products }) => {
    const { props } = usePage();
    const [selectedId, setSelectedId] = useState(null);
    const sellForm = useForm({ quantity: 1, notes: '', journal_date: props.currentDate });

    const product = useMemo(() => products.find((p) => p.id === selectedId) ?? null, [products, selectedId]);

    const effectivePrice = useMemo(() => {
        if (!product) return 0;
        const sp = parseFloat(product.selling_price);
        if (sp > 0) return sp;
        const markup = parseFloat(product.markup_percentage ?? 0);
        const cost = parseFloat(product.computed_cost ?? 0);
        if (markup > 0) return cost * (1 + markup / 100);
        return cost;
    }, [product]);

    const isPriceAuto = useMemo(
        () => product && !(parseFloat(product.selling_price) > 0) && !(parseFloat(product.markup_percentage ?? 0) > 0),
        [product],
    );

    const isPriceMarkup = useMemo(
        () => product && !(parseFloat(product.selling_price) > 0) && parseFloat(product.markup_percentage ?? 0) > 0,
        [product],
    );

    const total = useMemo(() => {
        const qty = parseFloat(sellForm.data.quantity) || 0;
        return (effectivePrice * qty).toFixed(2);
    }, [effectivePrice, sellForm.data.quantity]);

    const quantity = useMemo(() => Number(sellForm.data.quantity) || 0, [sellForm.data.quantity]);
    const availableStock = useMemo(() => parseFloat(product?.current_stock ?? 0), [product]);

    const canSell = useMemo(() => {
        if (!product || !(quantity > 0) || !Number.isInteger(quantity)) return false;
        return availableStock >= quantity;
    }, [product, quantity, availableStock]);

    const projectedRemaining = useMemo(
        () => Math.max(0, availableStock - quantity).toFixed(4),
        [availableStock, quantity],
    );

    const select = (p) => {
        if (selectedId === p.id) {
            setSelectedId(null);
            sellForm.reset();
            sellForm.setData((data) => ({ ...data, quantity: 1, journal_date: props.currentDate }));
            return;
        }
        setSelectedId(p.id);
        sellForm.reset();
        sellForm.setData((data) => ({ ...data, quantity: 1, journal_date: props.currentDate }));
    };

    const cancel = () => {
        setSelectedId(null);
        sellForm.reset();
        sellForm.setData((data) => ({ ...data, quantity: 1, journal_date: props.currentDate }));
    };

    const submit = () => {
        sellForm.setData('journal_date', props.currentDate);
        sellForm.post(`/products/${selectedId}/checkout`, { onSuccess: cancel });
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
                    <p className="text-xs text-slate-500">Select a product, enter quantity, and confirm checkout.</p>
                </div>
                <p className="text-xs text-slate-500">Date: {props.currentDate}</p>
            </div>

            {selectedId && product && (
                <div className={[ui.card, 'p-5 mb-5'].join(' ')}>
                    <div className="flex flex-wrap items-end gap-4">
                        <div className="flex items-center gap-3 min-w-48">
                            {product.image_path ? (
                                <img
                                    src={`/storage/${product.image_path}`}
                                    className="h-12 w-12 object-cover flex-shrink-0 rounded-lg border border-slate-200"
                                />
                            ) : (
                                <div className="h-12 w-12 bg-slate-100 rounded-lg flex items-center justify-center text-slate-400 text-lg flex-shrink-0">
                                    ◆
                                </div>
                            )}
                            <div>
                                <p className="text-sm font-semibold text-slate-900">{product.name}</p>
                                <p className="text-xs text-slate-500">
                                    Unit price:
                                    <span className="tabular-nums text-slate-900 font-semibold">${currency(effectivePrice)}</span>
                                    {isPriceAuto && <span className="text-slate-400 ml-1">(recipe cost)</span>}
                                    {isPriceMarkup && <span className="text-slate-400 ml-1">(+{product.markup_percentage}% markup)</span>}
                                </p>
                                <p className="text-xs text-slate-500">
                                    Stock:
                                    <span className="tabular-nums text-slate-900 font-semibold ml-1">{currency(product.current_stock)}</span>
                                </p>
                            </div>
                        </div>

                        <div className="w-24">
                            <label className={ui.fieldLabel}>
                                Qty <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="number"
                                value={sellForm.data.quantity}
                                min="1"
                                step="1"
                                inputMode="numeric"
                                onChange={(e) => sellForm.setData('quantity', e.target.value)}
                                className={inputCls}
                            />
                            {sellForm.errors.quantity && <p className="text-xs text-red-500 mt-0.5">{sellForm.errors.quantity}</p>}
                        </div>

                        <div>
                            <label className={ui.fieldLabel}>Total</label>
                            <div className="px-3 py-2 border border-slate-200 bg-slate-50 text-sm font-semibold tabular-nums w-28 rounded-lg">${total}</div>
                        </div>

                        <div className="flex-1 min-w-36">
                            <label className={ui.fieldLabel}>Notes</label>
                            <input
                                type="text"
                                value={sellForm.data.notes}
                                onChange={(e) => sellForm.setData('notes', e.target.value)}
                                className={inputCls}
                                placeholder="Optional"
                            />
                        </div>

                        <div className="flex gap-2 pb-px">
                            <button
                                onClick={submit}
                                disabled={sellForm.processing || !canSell}
                                className={btnPrimary}
                                title={!canSell ? 'Insufficient product stock' : ''}
                            >
                                Confirm Sale
                            </button>
                            <button onClick={cancel} className={btnGhost}>
                                Cancel
                            </button>
                        </div>
                    </div>

                    <div className="mt-4 pt-3 border-t border-slate-100 flex flex-wrap gap-4 text-xs text-slate-600">
                        <p>
                            Available: <span className="font-semibold text-slate-900 tabular-nums">{currency(availableStock)}</span>
                        </p>
                        <p>
                            After checkout: <span className="font-semibold text-slate-900 tabular-nums">{projectedRemaining}</span>
                        </p>
                    </div>

                    {sellForm.errors.checkout && (
                        <p className="text-xs text-red-500 mt-2">{sellForm.errors.checkout}</p>
                    )}
                </div>
            )}

            {products.length ? (
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                    {products.map((p) => (
                        <button
                            key={p.id}
                            onClick={() => select(p)}
                            className={`text-left transition-all rounded-xl overflow-hidden border shadow-sm ${
                                selectedId === p.id ? 'border-slate-900 ring-2 ring-slate-200 shadow-md' : 'border-slate-200 hover:border-slate-400 hover:shadow'
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
