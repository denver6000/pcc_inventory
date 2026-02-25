import React, { useMemo, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { ui } from '@/theme';

const PosIndex = ({ products }) => {
    const [selectedId, setSelectedId] = useState(null);
    const sellForm = useForm({ quantity: 1, notes: '' });

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

    const ingredientStatus = (ing) => {
        const qty = parseFloat(sellForm.data.quantity) || 0;
        const required = parseFloat((ing.quantity * qty).toFixed(4));
        const available = parseFloat(ing.item.current_stock);
        return {
            required,
            available,
            unit: ing.item.unit?.abbreviation ?? '',
            ok: available >= required,
        };
    };

    const canSell = useMemo(() => {
        if (!product || !(parseFloat(sellForm.data.quantity) > 0)) return false;
        return product.ingredients.every((ing) => ingredientStatus(ing).ok);
    }, [product, sellForm.data.quantity]);

    const select = (p) => {
        if (selectedId === p.id) {
            setSelectedId(null);
            sellForm.reset();
            sellForm.setData('quantity', 1);
            return;
        }
        setSelectedId(p.id);
        sellForm.reset();
        sellForm.setData('quantity', 1);
    };

    const cancel = () => {
        setSelectedId(null);
        sellForm.reset();
    };

    const submit = () => {
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
                    <p className="text-xs text-slate-500">Click a product to stage a sale; stock guards show per-ingredient availability.</p>
                </div>
                <p className="text-xs text-slate-500">Live recipe-based pricing</p>
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
                            </div>
                        </div>

                        <div className="w-24">
                            <label className={ui.fieldLabel}>
                                Qty <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="number"
                                value={sellForm.data.quantity}
                                min="0.01"
                                step="1"
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
                                title={!canSell ? 'Insufficient stock for one or more ingredients' : ''}
                            >
                                Confirm Sale
                            </button>
                            <button onClick={cancel} className={btnGhost}>
                                Cancel
                            </button>
                        </div>
                    </div>

                    {product.ingredients.length > 0 && (
                        <div className="mt-4 pt-3 border-t border-slate-100 flex flex-wrap gap-2">
                            {product.ingredients.map((ing) => (
                                <div
                                    key={ing.id}
                                    className={`flex items-center gap-1 text-xs px-3 py-1 rounded-full border ${
                                        ingredientStatus(ing).ok
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                            : 'bg-rose-50 text-rose-700 border-rose-200'
                                    }`}
                                >
                                    <span>{ingredientStatus(ing).ok ? '✓' : '!'}</span>
                                    {ing.item.name}
                                    <span className="opacity-50">
                                        {ingredientStatus(ing).required}/{ingredientStatus(ing).available}
                                        {ingredientStatus(ing).unit}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}

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
