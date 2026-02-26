import React, { useMemo, useState } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { ui } from '@/theme';
import { currency } from './helpers';
import { useBatchConfig } from './useBatchConfig';
import BatchConfigPanel from './BatchConfigPanel';
import IngredientCheck from './IngredientCheck';
import ProductCards from './ProductCards';

const ProduceIndex = ({ products }) => {
    const { props } = usePage();
    const [selectedId, setSelectedId] = useState(null);

    const form = useForm({
        product_id: '',
        quantity: 1,
        notes: '',
        journal_date: '',
        batch_orders: {},
    });

    const product = useMemo(
        () => products.find((p) => p.id === selectedId) ?? null,
        [products, selectedId],
    );

    const batchConfig = useBatchConfig(product, form.data.quantity);

    const maxBuild = useMemo(() => {
        if (!product?.ingredients?.length) return 0;
        let min = Infinity;
        product.ingredients.forEach((ing) => {
            const needPerUnit = parseFloat(ing.quantity) || 0;
            const available = parseFloat(ing.item?.current_stock) || 0;
            if (needPerUnit <= 0) return;
            min = Math.min(min, available / needPerUnit);
        });
        return Number.isFinite(min) ? min : 0;
    }, [product]);

    // ── Selection / submission ───────────────────────────────────────────

    const select = (p) => {
        setSelectedId(p.id);
        form.setData({
            product_id: p.id,
            quantity: 1,
            notes: '',
            journal_date: props.currentDate,
            batch_orders: {},
        });
        form.clearErrors();
        batchConfig.initForProduct(p);
    };

    const submit = () => {
        const orders = batchConfig.normalisedOrders();
        router.post('/produce', {
            ...form.data,
            product_id: selectedId,
            journal_date: props.currentDate,
            batch_orders: orders,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setSelectedId(null);
                batchConfig.resetAll();
            },
            onError: (errors) => {
                Object.keys(errors).forEach((key) => form.setError(key, errors[key]));
            },
        });
    };

    const clearSelection = () => {
        form.reset();
        setSelectedId(null);
        batchConfig.resetAll();
    };

    // ── Render ──────────────────────────────────────────────────────────

    return (
        <>
            <Head title="Produce" />

            <div className="flex items-center justify-between mb-5">
                <div>
                    <h1 className={ui.heading}>Produce</h1>
                    <p className="text-xs text-slate-500">
                        Convert raw ingredients into finished products without exceeding ingredient stock.
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-[1.2fr_1fr] gap-4">
                {/* ── Production Plan ─────────────────────────────────── */}
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
                                onChange={(e) => {
                                    const id = e.target.value ? Number(e.target.value) : null;
                                    if (id) {
                                        const p = products.find((pr) => pr.id === id);
                                        if (p) select(p);
                                    } else {
                                        clearSelection();
                                    }
                                }}
                                className={ui.input}
                            >
                                <option value="">— select product —</option>
                                {products.map((p) => (
                                    <option key={p.id} value={p.id}>{p.name}</option>
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
                            <p className="text-[11px] text-slate-500 mt-1">
                                Must be a whole number and cannot exceed max build based on ingredients.
                            </p>
                        </div>
                    </div>

                    {product && (
                        <div className="flex flex-wrap items-center gap-3 mb-3">
                            <span className="text-xs text-slate-500">
                                Current stock:{' '}
                                <span className="font-semibold text-slate-900">
                                    {product.current_stock ?? 0}
                                </span>
                            </span>
                            <span className="text-xs text-slate-500">
                                Recipe cost:{' '}
                                <span className="font-semibold text-slate-900">
                                    {currency(product.computed_cost)}
                                </span>
                            </span>
                            <span className="text-xs text-slate-500">
                                Selling price:{' '}
                                <span className="font-semibold text-slate-900">
                                    {currency(product.selling_price) || 'auto'}
                                </span>
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
                            disabled={
                                !product ||
                                form.processing ||
                                !form.data.quantity ||
                                form.data.quantity <= 0 ||
                                form.data.quantity > maxBuild
                            }
                        >
                            Produce
                        </button>
                        <button onClick={clearSelection} className={ui.button.secondary}>
                            Clear
                        </button>
                    </div>

                    {form.errors.produce && (
                        <p className="text-xs text-rose-600 mt-2">{form.errors.produce}</p>
                    )}
                    {form.errors.quantity && (
                        <p className="text-xs text-rose-600 mt-2">{form.errors.quantity}</p>
                    )}
                </div>

                {/* ── Ingredient Check ────────────────────────────────── */}
                <IngredientCheck
                    product={product}
                    quantity={form.data.quantity}
                    batchConfigData={batchConfig.batchConfigData}
                />
            </div>

            {/* ── Batch Configuration ────────────────────────────────── */}
            {product && <BatchConfigPanel product={product} batchConfig={batchConfig} />}

            {/* ── Product Cards ──────────────────────────────────────── */}
            <ProductCards products={products} selectedId={selectedId} onSelect={select} />
        </>
    );
};

export default ProduceIndex;
