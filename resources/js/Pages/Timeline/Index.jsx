import React, { useEffect, useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { ui } from '@/theme';

const offsetDate = (baseIso, deltaDays) => {
    const d = new Date(baseIso);
    d.setDate(d.getDate() + deltaDays);
    return d.toISOString().slice(0, 10);
};

const fmtDate = (d) => new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

const TimelineIndex = ({ items = [], history = null }) => {
    const page = usePage();
    const [selectedId, setSelectedId] = useState(history?.item_id ?? items?.[0]?.id ?? null);
    const [limit, setLimit] = useState(history?.limit ?? 5);
    const [selectedDate, setSelectedDate] = useState(page.props.currentDate);
    const [windowStart, setWindowStart] = useState(offsetDate(page.props.currentDate, -4));

    const historyData = useMemo(() => page.props.history ?? history ?? null, [page.props.history, history]);
    const series = historyData?.series ?? [];
    const itemName = historyData?.item_name ?? '';

    const chartPoints = useMemo(() => {
        if (!series.length) return '';
        const padding = 6;
        const width = 360;
        const height = 120;
        const values = series.map((s) => s.balance);
        const min = Math.min(...values);
        const max = Math.max(...values);
        const span = max - min || 1;
        const step = series.length > 1 ? (width - padding * 2) / (series.length - 1) : 0;
        return series
            .map((s, idx) => {
                const x = padding + idx * step;
                const y = height - padding - ((s.balance - min) / span) * (height - padding * 2);
                return `${x},${y}`;
            })
            .join(' ');
    }, [series]);

    const timelineDays = useMemo(() => {
        const days = [];
        for (let i = 0; i < 5; i += 1) {
            const iso = offsetDate(windowStart, i);
            days.push({ iso, label: fmtDate(iso), isSelected: iso === selectedDate });
        }
        return days;
    }, [windowStart, selectedDate]);

    const fetchHistory = (more = false, idOverride = null) => {
        const itemId = idOverride ?? selectedId;
        if (!itemId) return;
        const nextLimit = (historyData?.limit ?? limit ?? 5) + (more ? 5 : 0);
        setLimit(nextLimit);
        router.get(
            '/timeline',
            {
                date: selectedDate,
                item_id: itemId,
                limit: nextLimit,
            },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['history'],
            },
        );
    };

    const onItemChange = (e) => {
        const val = e?.target?.value ? parseInt(e.target.value, 10) : null;
        setSelectedId(val);
        fetchHistory(false, val);
    };

    const loadMore = () => fetchHistory(true);

    const changeDate = (iso) => {
        setSelectedDate(iso);
        const minWin = windowStart;
        const maxWin = offsetDate(windowStart, 4);
        if (iso < minWin) {
            setWindowStart(iso);
        } else if (iso > maxWin) {
            setWindowStart(offsetDate(iso, -4));
        }
        fetchHistory(false);
    };

    const shiftWindow = (delta) => {
        const today = page.props.currentDate;
        const nextStart = offsetDate(windowStart, delta);
        const maxStart = offsetDate(today, -4);
        const clampedStart = delta > 0 && nextStart > maxStart ? maxStart : nextStart;
        setWindowStart(clampedStart);
        const target = delta > 0 ? offsetDate(clampedStart, 4) : clampedStart;
        changeDate(target);
    };

    useEffect(() => {
        if (historyData?.item_id && historyData.item_id !== selectedId) {
            setSelectedId(historyData.item_id);
        }
        if (historyData?.limit) {
            setLimit(historyData.limit);
        }
    }, [historyData, selectedId]);

    useEffect(() => {
        if (page.props.currentDate && page.props.currentDate !== selectedDate) {
            setSelectedDate(page.props.currentDate);
            setWindowStart(offsetDate(page.props.currentDate, -4));
        }
    }, [page.props.currentDate, selectedDate]);

    return (
        <>
            <Head title="Stock Timeline" />

            <div className={[ui.card, 'p-4 mb-4'].join(' ')}>
                <div className="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <div>
                        <p className={ui.heading}>Stock timeline</p>
                        <p className="text-xs text-slate-500">Daily movements through {fmtDate(selectedDate)}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <label className="text-xs text-slate-600">Item</label>
                        <select value={selectedId ?? ''} onChange={onItemChange} className={ui.input}>
                            <option value="">— select —</option>
                            {items.map((opt) => (
                                <option key={opt.id} value={opt.id}>
                                    {opt.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="flex items-center gap-2 mb-3">
                    <button className={ui.button.secondary} onClick={() => shiftWindow(-1)}>
                        ◀
                    </button>
                    <div className="flex gap-1">
                        {timelineDays.map((d) => (
                            <button
                                key={d.iso}
                                className={`px-3 py-2 text-xs rounded border transition-colors ${
                                    d.isSelected
                                        ? 'bg-slate-900 text-white border-slate-900'
                                        : 'bg-white text-slate-700 border-slate-200 hover:border-slate-400'
                                }`}
                                onClick={() => changeDate(d.iso)}
                            >
                                {d.label}
                            </button>
                        ))}
                    </div>
                    <button className={ui.button.secondary} onClick={() => shiftWindow(1)}>
                        ▶
                    </button>
                    <div className="ml-auto flex items-center gap-2 text-xs text-slate-600">
                        <span>Date</span>
                        <input
                            type="date"
                            value={selectedDate}
                            onChange={(e) => changeDate(e.target.value)}
                            className="border border-slate-200 rounded px-2 py-1 text-sm"
                        />
                    </div>
                </div>

                {series.length ? (
                    <div className="space-y-4">
                        <div className="flex flex-wrap items-center gap-4">
                            <svg viewBox="0 0 360 120" className="w-full max-w-2xl h-28 bg-slate-50 border border-slate-200 rounded">
                                {chartPoints && (
                                    <polyline
                                        points={chartPoints}
                                        fill="none"
                                        stroke="rgba(8,47,73,0.9)"
                                        strokeWidth="2"
                                        strokeLinejoin="round"
                                        strokeLinecap="round"
                                    />
                                )}
                            </svg>
                            <div className="text-xs text-slate-600">
                                <p className="font-semibold text-slate-800">{itemName || 'Selected item'}</p>
                                <p>Latest {historyData?.limit ?? series.length} days with movements</p>
                            </div>
                        </div>

                        <div className="border border-slate-200 rounded-lg overflow-hidden">
                            <table className="w-full text-xs">
                                <thead className="bg-slate-100 text-slate-600 uppercase tracking-wide">
                                    <tr>
                                        <th className="py-2 px-3 text-left">Date</th>
                                        <th className="py-2 px-3 text-right">Delta</th>
                                        <th className="py-2 px-3 text-right">Balance after</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {series.map((row) => (
                                        <tr key={row.date} className="border-b border-slate-100 last:border-0">
                                            <td className="py-2 px-3 text-slate-800">{fmtDate(row.date)}</td>
                                            <td
                                                className={`py-2 px-3 text-right tabular-nums ${
                                                    row.delta >= 0 ? 'text-emerald-700' : 'text-rose-700'
                                                }`}
                                            >
                                                {row.delta >= 0 ? '+' : ''}
                                                {parseFloat(row.delta).toFixed(4)}
                                            </td>
                                            <td className="py-2 px-3 text-right tabular-nums text-slate-800">{parseFloat(row.balance).toFixed(4)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex items-center gap-2">
                            {historyData?.has_more ? (
                                <button onClick={loadMore} className={ui.button.secondary}>
                                    Load 5 more days
                                </button>
                            ) : (
                                <p className="text-[11px] text-slate-500">No earlier movements.</p>
                            )}
                        </div>
                    </div>
                ) : (
                    <div className="text-xs text-slate-500">No movements for this item before {fmtDate(page.props.currentDate)}.</div>
                )}
            </div>
        </>
    );
};

export default TimelineIndex;
