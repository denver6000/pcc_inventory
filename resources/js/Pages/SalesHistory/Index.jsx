import React, { useMemo } from 'react';
import { Head } from '@inertiajs/react';
import { ui } from '@/theme';

const SalesHistoryIndex = ({ sales, trend, summary, window }) => {
    const fmtMoney = (n) => Number(n ?? 0).toFixed(2);
    const fmtQty = (n) => Number(n ?? 0).toFixed(0);
    const prettyDate = (s) => new Date(`${s}T00:00:00`).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

    const chart = useMemo(() => {
        const width = 760;
        const height = 220;
        const padX = 24;
        const padY = 16;
        const innerW = width - padX * 2;
        const innerH = height - padY * 2;

        const values = trend.map((d) => Number(d.revenue ?? 0));
        const max = Math.max(1, ...values);

        const points = trend.map((d, i) => {
            const x = padX + (trend.length <= 1 ? 0 : (i / (trend.length - 1)) * innerW);
            const y = padY + innerH - (Number(d.revenue ?? 0) / max) * innerH;
            return { x, y, label: d.date, value: Number(d.revenue ?? 0) };
        });

        const path = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');

        return { width, height, path, points, max, padX, padY, innerW, innerH };
    }, [trend]);

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
                    <p className="mt-2 text-xl font-semibold text-slate-900 tabular-nums">{summary.sales_count}</p>
                </div>
                <div className={[ui.card, 'p-4'].join(' ')}>
                    <p className={ui.subheading}>Units Sold</p>
                    <p className="mt-2 text-xl font-semibold text-slate-900 tabular-nums">{fmtQty(summary.units_sold)}</p>
                </div>
                <div className={[ui.card, 'p-4'].join(' ')}>
                    <p className={ui.subheading}>Revenue</p>
                    <p className="mt-2 text-xl font-semibold text-slate-900 tabular-nums">${fmtMoney(summary.revenue)}</p>
                </div>
            </div>

            <div className={[ui.card, 'p-4 mb-5'].join(' ')}>
                <div className="flex items-center justify-between mb-3">
                    <p className={ui.subheading}>Revenue Trend</p>
                    <p className="text-xs text-slate-500">
                        {prettyDate(window.start)} → {prettyDate(window.end)}
                    </p>
                </div>

                <div className="overflow-x-auto">
                    <svg viewBox={`0 0 ${chart.width} ${chart.height}`} className="w-full min-w-[760px] h-[220px]">
                        <rect x="0" y="0" width={chart.width} height={chart.height} fill="#ffffff" />
                        <line x1={chart.padX} y1={chart.padY + chart.innerH} x2={chart.padX + chart.innerW} y2={chart.padY + chart.innerH} stroke="#e2e8f0" />
                        <line x1={chart.padX} y1={chart.padY} x2={chart.padX} y2={chart.padY + chart.innerH} stroke="#e2e8f0" />

                        <path d={chart.path} fill="none" stroke="#0f172a" strokeWidth="2" />

                        {chart.points.map((p) => (
                            <g key={p.label}>
                                <circle cx={p.x} cy={p.y} r="2.5" fill="#0f172a" />
                                {p.value > 0 && (
                                    <text x={p.x} y={p.y - 8} textAnchor="middle" className="fill-slate-500 text-[10px]" fontSize="10">
                                        ${fmtMoney(p.value)}
                                    </text>
                                )}
                            </g>
                        ))}
                    </svg>
                </div>
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
                            {sales.length ? (
                                sales.map((sale) => (
                                    <tr key={sale.id} className="border-t border-slate-100">
                                        <td className={ui.tableCell}>{prettyDate(sale.sold_on)}</td>
                                        <td className={ui.tableCell}>{sale.product?.name ?? 'Unknown Product'}</td>
                                        <td className={[ui.tableCell, 'text-right tabular-nums'].join(' ')}>{fmtQty(sale.quantity_sold)}</td>
                                        <td className={[ui.tableCell, 'text-right tabular-nums'].join(' ')}>${fmtMoney(sale.unit_price)}</td>
                                        <td className={[ui.tableCell, 'text-right tabular-nums font-medium text-slate-900'].join(' ')}>${fmtMoney(sale.total_price)}</td>
                                        <td className={ui.tableCellMuted}>{sale.notes || '—'}</td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={6} className="py-10 text-center text-xs text-slate-400">
                                        No sales recorded yet.
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
