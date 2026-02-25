import React, { useMemo, useRef, useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { ui } from '@/theme';

const ProductsIndex = ({ products, items }) => {
    const [mode, setMode] = useState(null); // null | 'add' | product id
    const imgInput = useRef(null);
    const [imagePreview, setImagePreview] = useState(null);
    const [pricingMode, setPricingMode] = useState('auto'); // auto | markup | manual

    const form = useForm({
        name: '',
        image: null,
        selling_price: '',
        markup_percentage: 0,
        ingredients: [],
    });

    const liveComputedCost = useMemo(
        () =>
            form.data.ingredients
                .reduce((sum, ing) => {
                    const item = items.find((i) => i.id === Number(ing.item_id));
                    return sum + (parseFloat(ing.quantity) || 0) * (parseFloat(item?.cost_per_unit) || 0);
                }, 0)
                .toFixed(2),
        [form.data.ingredients, items],
    );

    const formEffectivePrice = useMemo(() => {
        const cost = parseFloat(liveComputedCost) || 0;
        if (pricingMode === 'manual') return parseFloat(form.data.selling_price) || 0;
        if (pricingMode === 'markup') return cost * (1 + (parseFloat(form.data.markup_percentage) || 0) / 100);
        return cost;
    }, [liveComputedCost, pricingMode, form.data.selling_price, form.data.markup_percentage]);

    const openAdd = () => {
        setMode('add');
        form.reset();
        form.setData((data) => ({ ...data, name: '', image: null, selling_price: '', markup_percentage: 0, ingredients: [] }));
        setPricingMode('auto');
        setImagePreview(null);
    };

    const openEdit = (product) => {
        setMode(product.id);
        form.setData((data) => ({
            ...data,
            name: product.name,
            image: null,
            selling_price: product.selling_price ?? '',
            markup_percentage: product.markup_percentage ?? 0,
            ingredients: product.ingredients.map((i) => ({ item_id: i.item_id, quantity: i.quantity })),
        }));
        setImagePreview(product.image_path ? `/storage/${product.image_path}` : null);

        if (parseFloat(product.selling_price) > 0) {
            setPricingMode('manual');
        } else if (parseFloat(product.markup_percentage) > 0) {
            setPricingMode('markup');
        } else {
            setPricingMode('auto');
        }
    };

    const closeForm = () => {
        setMode(null);
        form.reset();
        form.setData((data) => ({ ...data, name: '', image: null, selling_price: '', markup_percentage: 0, ingredients: [] }));
        setPricingMode('auto');
        setImagePreview(null);
    };

    const handleImage = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        form.setData('image', file);
        setImagePreview(URL.createObjectURL(file));
    };

    const addIngredient = () => {
        form.setData('ingredients', [...form.data.ingredients, { item_id: '', quantity: '' }]);
    };

    const removeIngredient = (idx) => {
        const next = [...form.data.ingredients];
        next.splice(idx, 1);
        form.setData('ingredients', next);
    };

    const submitProduct = () => {
        if (pricingMode === 'auto') {
            form.setData('selling_price', 0);
            form.setData('markup_percentage', 0);
        } else if (pricingMode === 'markup') {
            form.setData('selling_price', 0);
        } else {
            form.setData('markup_percentage', 0);
        }

        if (mode === 'add') {
            form.post('/products', { onSuccess: closeForm });
        } else {
            form.put(`/products/${mode}`, { onSuccess: closeForm });
        }
    };

    const destroyProduct = (product) => {
        if (window.confirm(`Delete "${product.name}"?\nThis will also delete its sales history.`)) {
            router.delete(`/products/${product.id}`);
        }
    };

    const inputCls = ui.input;
    const btnPrimary = ui.button.primary;
    const btnSecondary = ui.button.secondary;
    const modeBtnCls = (m) =>
        [
            'text-xs px-3 py-1.5 transition-colors rounded-lg',
            pricingMode === m
                ? 'bg-slate-900 text-white shadow-sm'
                : 'border border-slate-200 text-slate-600 hover:text-slate-900 hover:border-slate-400',
        ].join(' ');

    return (
        <>
            <Head title="Products" />

            <div className="flex items-center justify-between mb-5">
                <div>
                    <h1 className={ui.heading}>Products</h1>
                    <p className="text-xs text-slate-500">Define recipes, pricing, and images for sellable items.</p>
                </div>
                {!mode && (
                    <button onClick={openAdd} className={btnPrimary}>
                        + New Product
                    </button>
                )}
            </div>

            {mode && (
                <div className={[ui.card, 'p-5 mb-5'].join(' ')}>
                    <p className={[ui.subheading, 'mb-3'].join(' ')}>{mode === 'add' ? 'New Product' : 'Edit Product'}</p>

                    <div className="flex flex-wrap items-end gap-3 mb-4">
                        <div>
                            <label className={ui.fieldLabel}>Image</label>
                            <div
                                className="h-12 w-12 border border-slate-200 rounded-lg flex items-center justify-center cursor-pointer overflow-hidden hover:border-slate-400 transition-colors bg-slate-50"
                                onClick={() => imgInput.current?.click()}
                                title="Click to pick image"
                            >
                                {imagePreview ? <img src={imagePreview} className="h-full w-full object-cover" /> : <span className="text-slate-400 text-xl select-none">+</span>}
                            </div>
                            <input ref={imgInput} type="file" accept="image/*" onChange={handleImage} className="hidden" />
                        </div>

                        <div className="flex-1 min-w-48">
                            <label className={ui.fieldLabel}>
                                Name <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                className={inputCls}
                                placeholder="Product name"
                            />
                            {form.errors.name && <p className="text-xs text-red-500 mt-0.5">{form.errors.name}</p>}
                        </div>
                    </div>

                    <div className="mb-4">
                        <label className={ui.fieldLabel}>Pricing</label>
                        <div className="inline-flex border border-slate-200 divide-x divide-slate-200 rounded-lg overflow-hidden w-fit mb-3 bg-white shadow-[0_1px_0_rgba(15,23,42,0.04)]">
                            <button type="button" onClick={() => setPricingMode('auto')} className={modeBtnCls('auto')}>
                                Auto
                            </button>
                            <button type="button" onClick={() => setPricingMode('markup')} className={modeBtnCls('markup')}>
                                Markup %
                            </button>
                            <button type="button" onClick={() => setPricingMode('manual')} className={modeBtnCls('manual')}>
                                Manual
                            </button>
                        </div>
                        <div className="flex flex-wrap items-end gap-3">
                            <div>
                                <label className={ui.fieldLabel}>Recipe Cost</label>
                                <div className="px-2.5 py-1.5 border border-slate-100 bg-slate-50 text-sm tabular-nums w-36 rounded-lg">{liveComputedCost}</div>
                                <p className="text-xs text-gray-300 mt-0.5">auto-calculated</p>
                            </div>

                            {pricingMode === 'markup' && (
                                <div className="w-28">
                                    <label className={ui.fieldLabel}>Markup %</label>
                                    <div className="relative">
                                        <input
                                            type="number"
                                            value={form.data.markup_percentage}
                                            min="0"
                                            step="0.1"
                                            onChange={(e) => form.setData('markup_percentage', e.target.value)}
                                            className={inputCls}
                                            placeholder="0"
                                        />
                                        <span className="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 pointer-events-none">%</span>
                                    </div>
                                    {form.errors.markup_percentage && (
                                        <p className="text-xs text-red-500 mt-0.5">{form.errors.markup_percentage}</p>
                                    )}
                                </div>
                            )}

                            {pricingMode === 'manual' && (
                                <div className="w-36">
                                    <label className={ui.fieldLabel}>Selling Price</label>
                                    <input
                                        type="number"
                                        value={form.data.selling_price}
                                        min="0"
                                        step="0.01"
                                        onChange={(e) => form.setData('selling_price', e.target.value)}
                                        className={inputCls}
                                        placeholder="0.00"
                                    />
                                    {form.errors.selling_price && (
                                        <p className="text-xs text-red-500 mt-0.5">{form.errors.selling_price}</p>
                                    )}
                                </div>
                            )}

                            <div>
                                <label className={ui.fieldLabel}>Effective Price</label>
                                <div className="px-2.5 py-1.5 border border-slate-100 bg-slate-50 text-sm tabular-nums w-36 font-medium rounded-lg">
                                    {formEffectivePrice.toFixed(2)}
                                </div>
                                <p className="text-xs text-gray-300 mt-0.5">
                                    {pricingMode === 'auto' && '= recipe cost'}
                                    {pricingMode === 'markup' && `cost × (1 + ${form.data.markup_percentage || 0}%)`}
                                    {pricingMode === 'manual' && 'fixed price'}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="mb-3">
                        <div className="flex items-center justify-between mb-2">
                            <p className={ui.subheading}>Recipe</p>
                            <button type="button" onClick={addIngredient} className="text-xs text-slate-500 hover:text-slate-900 transition-colors">
                                + Add ingredient
                            </button>
                        </div>

                        {!form.data.ingredients.length && (
                            <div className="text-xs text-slate-400 py-3 text-center border border-dashed border-slate-200 rounded-lg bg-slate-50">
                                No ingredients yet — click "Add ingredient" above.
                            </div>
                        )}

                        {form.data.ingredients.map((ing, idx) => (
                            <div key={idx} className="flex items-end gap-2 mb-2">
                                <div className="flex-1 min-w-40">
                                    {idx === 0 && <label className="block text-xs text-gray-400 mb-1">Stock Item</label>}
                                    <select
                                        value={ing.item_id}
                                        onChange={(e) => {
                                            const next = [...form.data.ingredients];
                                            next[idx] = { ...next[idx], item_id: e.target.value };
                                            form.setData('ingredients', next);
                                        }}
                                        className={inputCls}
                                    >
                                        <option value="">— select item —</option>
                                        {items.map((item) => (
                                            <option key={item.id} value={item.id}>
                                                {item.name}
                                                {item.unit ? ` (${item.unit.abbreviation})` : ''}
                                            </option>
                                        ))}
                                    </select>
                                    {form.errors[`ingredients.${idx}.item_id`] && (
                                        <p className="text-xs text-red-500 mt-0.5">{form.errors[`ingredients.${idx}.item_id`]}</p>
                                    )}
                                </div>

                                <div className="w-28">
                                    {idx === 0 && <label className="block text-xs text-gray-400 mb-1">Qty per unit</label>}
                                    <input
                                        type="number"
                                        value={ing.quantity}
                                        min="0.0001"
                                        step="0.0001"
                                        onChange={(e) => {
                                            const next = [...form.data.ingredients];
                                            next[idx] = { ...next[idx], quantity: e.target.value };
                                            form.setData('ingredients', next);
                                        }}
                                        className={inputCls}
                                        placeholder="0"
                                    />
                                    {form.errors[`ingredients.${idx}.quantity`] && (
                                        <p className="text-xs text-red-500 mt-0.5">{form.errors[`ingredients.${idx}.quantity`]}</p>
                                    )}
                                </div>

                                <div className="w-10 pb-px text-xs text-gray-400 text-center">
                                    {items.find((i) => i.id === Number(ing.item_id))?.unit?.abbreviation ?? ''}
                                </div>

                                <button
                                    type="button"
                                    onClick={() => removeIngredient(idx)}
                                    className="pb-px text-xs text-gray-300 hover:text-red-500 transition-colors"
                                >
                                    ✕
                                </button>
                            </div>
                        ))}
                    </div>

                    <div className="flex gap-2 pt-1">
                        <button onClick={submitProduct} disabled={form.processing} className={btnPrimary}>
                            {mode === 'add' ? 'Create' : 'Save'}
                        </button>
                        <button onClick={closeForm} className={btnSecondary}>
                            Cancel
                        </button>
                    </div>
                </div>
            )}

            <div className={ui.table}>
                <table className="w-full">
                    <thead>
                        <tr className={`border-b border-slate-200 ${ui.tableHead}`}>
                            <th className="w-12 py-3 px-3"></th>
                            <th className="py-3 px-3 text-left">Name</th>
                            <th className="py-3 px-3 text-left">Recipe</th>
                            <th className="py-3 px-3 text-right">Cost</th>
                            <th className="py-3 px-3 text-right">Stock</th>
                            <th className="py-3 px-3 text-right">Price</th>
                            <th className="w-36 py-3 px-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {products.map((product) => (
                            <tr
                                key={product.id}
                                className={`border-b border-slate-100 last:border-0 hover:bg-slate-50 transition-colors ${
                                    mode === product.id ? 'bg-slate-50' : ''
                                }`}
                            >
                                <td className="py-2 px-3">
                                    {product.image_path ? (
                                        <img src={`/storage/${product.image_path}`} className="h-9 w-9 object-cover" />
                                    ) : (
                                        <div className="h-9 w-9 bg-slate-100 rounded"></div>
                                    )}
                                </td>
                                <td className="py-2 px-3 text-sm font-medium text-slate-900">{product.name}</td>
                                <td className="py-2 px-3">
                                    {!product.ingredients.length ? (
                                        <span className="text-xs text-gray-200">no recipe</span>
                                    ) : (
                                        <div className="flex flex-wrap gap-1">
                                            {product.ingredients.map((ing) => (
                                                <span
                                                    key={ing.id}
                                                    className="inline-flex items-center gap-1 text-xs text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded"
                                                >
                                                    {ing.quantity}
                                                    {ing.item.unit && <span className="text-slate-400">{ing.item.unit.abbreviation}</span>}
                                                    {ing.item.name}
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                </td>
                                <td className="py-2 px-3 text-sm text-right tabular-nums text-slate-500">
                                    {product.computed_cost > 0 ? (
                                        <span>{parseFloat(product.computed_cost).toFixed(2)}</span>
                                    ) : (
                                        <span className="text-slate-300">—</span>
                                    )}
                                </td>
                                <td className="py-2 px-3 text-sm text-right tabular-nums font-medium text-slate-800">
                                    {product.current_stock ?? 0}
                                </td>
                                <td className="py-2 px-3 text-sm text-right tabular-nums font-medium text-slate-800">
                                    {product.selling_price > 0 ? (
                                        <span>{parseFloat(product.selling_price).toFixed(2)}</span>
                                    ) : parseFloat(product.markup_percentage) > 0 ? (
                                        <span>
                                            {(parseFloat(product.computed_cost) * (1 + parseFloat(product.markup_percentage) / 100)).toFixed(2)}
                                            <span className="text-xs text-gray-400 font-normal ml-0.5">+{product.markup_percentage}%</span>
                                        </span>
                                    ) : (
                                        <span className="text-slate-400 text-xs">auto</span>
                                    )}
                                </td>
                                <td className="py-2 px-3 text-right whitespace-nowrap">
                                    <button
                                        onClick={() => openEdit(product)}
                                        className="text-xs text-slate-500 hover:text-slate-900 transition-colors mr-3"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => destroyProduct(product)}
                                        className="text-xs text-slate-500 hover:text-rose-600 transition-colors"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {!products.length && (
                            <tr>
                                <td colSpan={4} className="py-14 text-center text-xs text-slate-400">
                                    No products yet.
                                    <button onClick={openAdd} className="underline hover:text-slate-700">
                                        Create one
                                    </button>{' '}
                                    to get started.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
};

export default ProductsIndex;
