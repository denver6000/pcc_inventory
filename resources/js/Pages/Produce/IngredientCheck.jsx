import React from 'react';
import { ui } from '@/theme';

/**
 * Ingredient Check table — shows need/available/sourced/status per ingredient.
 */
const IngredientCheck = ({ product, quantity, batchConfigData }) => {
    const ingredientStatus = (ing) => {
        const qty = parseFloat(quantity) || 0;
        const required = parseFloat(ing.quantity) * qty;
        const available = parseFloat(ing.item?.current_stock) || 0;
        return {
            required: Number.isFinite(required) ? required : 0,
            available,
            ok: available + 1e-9 >= required,
            unit: ing.item?.unit?.abbreviation ?? '',
        };
    };

    return (
        <div className={[ui.card, 'p-4'].join(' ')}>
            <p className={[ui.subheading, 'mb-2'].join(' ')}>Ingredient Check</p>

            {product?.ingredients?.length ? (
                <div className="border border-slate-200 rounded-lg overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className={ui.tableHead}>
                            <tr className="border-b border-slate-200">
                                <th className="py-2 px-3 text-left">Ingredient</th>
                                <th className="py-2 px-3 text-right">Need</th>
                                <th className="py-2 px-3 text-right">Available</th>
                                <th className="py-2 px-3 text-right">Sourced</th>
                                <th className="py-2 px-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {product.ingredients.map((ing) => {
                                const status = ingredientStatus(ing);
                                const cfg = batchConfigData[ing.item_id];
                                const batchCount = cfg?.batches?.filter((b) => b.take > 1e-9).length ?? 0;

                                return (
                                    <tr key={ing.id} className="border-b border-slate-100 last:border-0">
                                        <td className="py-2 px-3 text-slate-800">{ing.item?.name}</td>
                                        <td className="py-2 px-3 text-right tabular-nums text-slate-600">
                                            {status.required.toFixed(4)} {status.unit}
                                        </td>
                                        <td className="py-2 px-3 text-right tabular-nums text-slate-600">
                                            {status.available.toFixed(4)} {status.unit}
                                        </td>
                                        <td className="py-2 px-3 text-right tabular-nums text-xs">
                                            {cfg && cfg.needed > 0 ? (
                                                <span
                                                    className={
                                                        cfg.fulfilled
                                                            ? 'text-emerald-600 font-semibold'
                                                            : 'text-amber-600 font-semibold'
                                                    }
                                                >
                                                    {cfg.sourced.toFixed(4)}{' '}
                                                    <span className="text-slate-400 font-normal">
                                                        from {batchCount} batch{batchCount !== 1 ? 'es' : ''}
                                                    </span>
                                                </span>
                                            ) : (
                                                <span className="text-slate-400">—</span>
                                            )}
                                        </td>
                                        <td className="py-2 px-3 text-right">
                                            <span
                                                className={[
                                                    'inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full',
                                                    status.ok
                                                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                        : 'bg-amber-50 text-amber-700 border border-amber-200',
                                                ].join(' ')}
                                            >
                                                {status.ok ? 'OK' : 'Insufficient'}
                                            </span>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div className="text-xs text-slate-400 py-6 text-center border border-dashed border-slate-200 rounded-lg">
                    Select a product to see ingredient requirements.
                </div>
            )}
        </div>
    );
};

export default IngredientCheck;
