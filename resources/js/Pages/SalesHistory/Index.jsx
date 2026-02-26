import React, { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import { ui } from '@/theme';

const SalesHistoryIndex = ({ sales, trend, summary, window }) => {
    const PRODUCT_COLORS = ['#0f172a', '#1d4ed8', '#059669', '#7c3aed', '#ea580c', '#be123c', '#0f766e', '#334155'];

    const fmtMoney = (n) => Number(n ?? 0).toFixed(2);
    const fmtQty = (n) => Number(n ?? 0).toFixed(0);
    const prettyDate = (s) => new Date(`${s}T00:00:00`).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

    const productOptions = useMemo(() => {
        const map = new Map();

        sales.forEach((sale) => {
            const key = String(sale.product?.id ?? `unknown-${sale.product?.name ?? 'unknown'}`);
            if (!map.has(key)) {
                map.set(key, {
                    id: key,
                    name: sale.product?.name ?? 'Unknown Product',
                    image_path: sale.product?.image_path ?? null,
                });
            }
        });

        return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name));
    }, [sales]);

    const colorByProductId = useMemo(
        () => productOptions.reduce((acc, p, idx) => {
            acc[p.id] = PRODUCT_COLORS[idx % PRODUCT_COLORS.length];
            return acc;
        }, {}),
        [productOptions],
    );

    const [selectedProductIds, setSelectedProductIds] = useState(() => productOptions.map((p) => p.id));

    const activeProductIds = useMemo(() => {
        if (!selectedProductIds.length) return [];
        return selectedProductIds;
    }, [selectedProductIds]);

    const activeSet = useMemo(() => new Set(activeProductIds), [activeProductIds]);

    const saleProductKey = (sale) => String(sale.product?.id ?? `unknown-${sale.product?.name ?? 'unknown'}`);

    const filteredSales = useMemo(
        () => sales.filter((sale) => activeSet.has(saleProductKey(sale))),
        [sales, activeSet],
    );

    const filteredSummary = useMemo(() => ({
        sales_count: filteredSales.length,
        units_sold: filteredSales.reduce((sum, row) => sum + Number(row.quantity_sold ?? 0), 0),
        revenue: filteredSales.reduce((sum, row) => sum + Number(row.total_price ?? 0), 0),
    }), [filteredSales]);

    const selectedSeries = useMemo(() => {
        const dateAxis = trend.map((d) => d.date);

        const byProductDate = new Map();
        filteredSales.forEach((sale) => {
            const key = saleProductKey(sale);
            const date = sale.sold_on;
            const mapKey = `${key}|${date}`;
            byProductDate.set(mapKey, (byProductDate.get(mapKey) ?? 0) + Number(sale.total_price ?? 0));
        });

        return productOptions
            .filter((p) => activeSet.has(p.id))
            .map((p) => ({
                ...p,
                color: colorByProductId[p.id],
                values: dateAxis.map((date) => Number(byProductDate.get(`${p.id}|${date}`) ?? 0)),
            }));
    }, [trend, filteredSales, productOptions, activeSet, colorByProductId]);

    const chart = useMemo(() => {
        const width = 760;
        const height = 220;
        const padX = 24;
        const padY = 16;
        const innerW = width - padX * 2;
        const innerH = height - padY * 2;

        const flatValues = selectedSeries.flatMap((s) => s.values);
        const max = Math.max(1, ...flatValues);

        const xFor = (i) => padX + (trend.length <= 1 ? 0 : (i / (trend.length - 1)) * innerW);
        const yFor = (val) => padY + innerH - (Number(val ?? 0) / max) * innerH;

        const series = selectedSeries.map((s) => {
            const points = s.values.map((v, i) => ({ x: xFor(i), y: yFor(v), value: v, label: trend[i]?.date }));
            const path = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
            return {
                id: s.id,
                name: s.name,
                color: s.color,
                points,
                path,
            };
        });

        return { width, height, series, max, padX, padY, innerW, innerH };
    }, [trend, selectedSeries]);

    const toggleProduct = (id) => {
        setSelectedProductIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    };

    const selectAll = () => setSelectedProductIds(productOptions.map((p) => p.id));
    const clearAll = () => setSelectedProductIds([]);

    return (
        <>
            <Head title="Sale History and Statistics" />

            <div className="mb-5">
                <h1 className={ui.heading}>Sale History and Statistics</h1>
                <p className="text-xs text-slate-500 mt-1">
                    Recorded sales up to {prettyDate(window.end)} with a {trend.length}-day revenue trend window.
                </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
                <div className={[ui.card, 'p-4'].join(' ')}>
                    <p className={ui.subheading}>Total Sales</p>
                    <p className="mt-2 text-xl font-semibold text-slate-900 tabular-nums">{filteredSummary.sales_count}</p>
                </div>
                <div className={[ui.card, 'p-4'].join(' ')}>
                    <p className={ui.subheading}>Units Sold</p>
                    <p className="mt-2 text-xl font-semibold text-slate-900 tabular-nums">{fmtQty(filteredSummary.units_sold)}</p>
                </div>
                <div className={[ui.card, 'p-4'].join(' ')}>
                    <p className={ui.subheading}>Revenue</p>
                    <p className="mt-2 text-xl font-semibold text-slate-900 tabular-nums">${fmtMoney(filteredSummary.revenue)}</p>
                </div>
            </div>

            <div className={[ui.card, 'p-4 mb-5'].join(' ')}>
                <div className="flex items-center justify-between mb-3">
                    <p className={ui.subheading}>Revenue Trend</p>
                    <p className="text-xs text-slate-500">
                        {prettyDate(window.start)} → {prettyDate(window.end)}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2 mb-3">
                    <button type="button" className={ui.button.subtle} onClick={selectAll}>Select all</button>
                    <button type="button" className={ui.button.subtle} onClick={clearAll}>Clear all</button>
                    {productOptions.map((p) => {
                        const active = activeSet.has(p.id);
                        const color = colorByProductId[p.id];
                        return (
                            <button
                                key={p.id}
                                type="button"
                                onClick={() => toggleProduct(p.id)}
                                className={[
                                    'inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-xs transition-colors',
                                    active
                                        ? 'border-slate-400 bg-slate-50 text-slate-900'
                                        : 'border-slate-200 text-slate-400 bg-white',
                                ].join(' ')}
                                title={active ? 'Hide from graph/table' : 'Show in graph/table'}
                            >
                                <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: color }} />
                                {p.image_path ? (
                                    <img
                                        src={`/storage/${p.image_path}`}
                                        alt={p.name}
                                        className="h-5 w-5 rounded-full object-cover border border-slate-200"
                                    />
                                ) : (
                                    <span className="h-5 w-5 rounded-full bg-slate-100 border border-slate-200" />
                                )}
                                <span>{p.name}</span>
                            </button>
                        );
                    })}
                </div>

                <div className="overflow-x-auto">
                    <svg viewBox={`0 0 ${chart.width} ${chart.height}`} className="w-full min-w-[760px] h-[220px]">
                        <rect x="0" y="0" width={chart.width} height={chart.height} fill="#ffffff" />
                        <line x1={chart.padX} y1={chart.padY + chart.innerH} x2={chart.padX + chart.innerW} y2={chart.padY + chart.innerH} stroke="#e2e8f0" />
                        <line x1={chart.padX} y1={chart.padY} x2={chart.padX} y2={chart.padY + chart.innerH} stroke="#e2e8f0" />

                        {chart.series.map((series) => (
                            <g key={series.id}>
                                <path d={series.path} fill="none" stroke={series.color} strokeWidth="2" />
                                {series.points.map((p, i) => (
                                    <circle key={`${series.id}-${p.label}-${i}`} cx={p.x} cy={p.y} r="2.2" fill={series.color} />
                                ))}
                            </g>
                        ))}
                    </svg>
                </div>

                {!activeProductIds.length && (
                    <p className="text-xs text-slate-400 mt-2">No legend selected. Choose at least one product to plot sales trends.</p>
                )}
            </div>

            <div className={ui.table}>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm min-w-[760px]">
                        <thead className={ui.tableHead}>
                            <tr>
                                <th className="text-left px-3 py-2">Date</th>
                                <th className="text-left px-3 py-2">Item Sold</th>
                                <th className="text-right px-3 py-2">Qty</th>
                                <th className="text-right px-3 py-2">Unit Price</th>
                                <th className="text-right px-3 py-2">Total</th>
                                <th className="text-left px-3 py-2">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredSales.length ? (
                                filteredSales.map((sale) => (
                                    <tr key={sale.id} className="border-t border-slate-100">
                                        <td className={ui.tableCell}>{prettyDate(sale.sold_on)}</td>
                                        <td className={ui.tableCell}>
                                            <div className="flex items-center gap-2">
                                                <span
                                                    className="h-2.5 w-2.5 rounded-full"
                                                    style={{ backgroundColor: colorByProductId[saleProductKey(sale)] ?? '#94a3b8' }}
                                                />
                                                {sale.product?.image_path ? (
                                                    <img
                                                        src={`/storage/${sale.product.image_path}`}
                                                        alt={sale.product?.name ?? 'Product'}
                                                        className="h-6 w-6 rounded-full object-cover border border-slate-200"
                                                    />
                                                ) : (
                                                    <span className="h-6 w-6 rounded-full bg-slate-100 border border-slate-200" />
                                                )}
                                                <span>{sale.product?.name ?? 'Unknown Product'}</span>
                                            </div>
                                        </td>
                                        <td className={[ui.tableCell, 'text-right tabular-nums'].join(' ')}>{fmtQty(sale.quantity_sold)}</td>
                                        <td className={[ui.tableCell, 'text-right tabular-nums'].join(' ')}>${fmtMoney(sale.unit_price)}</td>
                                        <td className={[ui.tableCell, 'text-right tabular-nums font-medium text-slate-900'].join(' ')}>${fmtMoney(sale.total_price)}</td>
                                        <td className={ui.tableCellMuted}>{sale.notes || '—'}</td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={6} className="py-10 text-center text-xs text-slate-400">
                                        No sales match the selected legend filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
};

export default SalesHistoryIndex;
