import React from 'react';
import { ui } from '@/theme';

/**
 * Product selection card grid for the Produce page.
 */
const ProductCards = ({ products, selectedId, onSelect }) => (
    <div className={[ui.card, 'mt-4 p-4'].join(' ')}>
        <div className="flex items-center justify-between mb-2">
            <p className={ui.subheading}>Products</p>
            <p className="text-xs text-slate-500">Select to populate the production form.</p>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            {products.map((p) => (
                <button
                    key={p.id}
                    onClick={() => onSelect(p)}
                    className={`text-left transition-all rounded-xl overflow-hidden border shadow-sm ${
                        selectedId === p.id
                            ? 'border-slate-900 ring-2 ring-slate-200 shadow-md'
                            : 'border-slate-200 hover:border-slate-400 hover:shadow'
                    }`}
                >
                    <div className="aspect-video bg-slate-100 overflow-hidden">
                        {p.image_path ? (
                            <img
                                src={`/storage/${p.image_path}`}
                                className="w-full h-full object-cover"
                            />
                        ) : (
                            <div className="w-full h-full flex items-center justify-center text-slate-300 text-3xl select-none">
                                ◆
                            </div>
                        )}
                    </div>
                    <div className="p-3">
                        <p className="text-sm font-semibold text-slate-900 truncate">{p.name}</p>
                        <p className="text-xs text-slate-500">
                            Current stock: {p.current_stock ?? 0}
                        </p>
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
);

export default ProductCards;
