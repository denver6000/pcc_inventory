import React, { useEffect, useMemo, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

const DateNavigator = () => {
    const page = usePage();
    const currentDate = page.props.currentDate;
    const today = useMemo(() => new Date().toISOString().slice(0, 10), []);
    const isToday = currentDate === today;

    const [showCalendar, setShowCalendar] = useState(false);
    const [calendarMonth, setCalendarMonth] = useState(null);
    const calendarRef = useRef(null);
    const triggerRef = useRef(null);

    const navigate = (date) => {
        const url = new URL(window.location.href);
        if (date === today) {
            url.searchParams.delete('date');
        } else {
            url.searchParams.set('date', date);
        }
        router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true });
        setShowCalendar(false);
    };

    const prevDay = () => {
        const d = new Date(`${currentDate}T00:00:00`);
        d.setDate(d.getDate() - 1);
        navigate(d.toISOString().slice(0, 10));
    };

    const nextDay = () => {
        const d = new Date(`${currentDate}T00:00:00`);
        d.setDate(d.getDate() + 1);
        navigate(d.toISOString().slice(0, 10));
    };

    const goToday = () => navigate(today);

    const dateLabel = useMemo(() => {
        const d = new Date(`${currentDate}T00:00:00`);
        return d.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    }, [currentDate]);

    const initCalendarMonth = () => {
        setCalendarMonth(new Date(`${currentDate}T00:00:00`));
    };

    const calendarTitle = useMemo(() => {
        if (!calendarMonth) return '';
        return calendarMonth.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    }, [calendarMonth]);

    const calendarDays = useMemo(() => {
        if (!calendarMonth) return [];
        const year = calendarMonth.getFullYear();
        const month = calendarMonth.getMonth();
        const first = new Date(year, month, 1);
        const startDay = first.getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        const cells = [];
        for (let i = 0; i < startDay; i += 1) cells.push(null);
        for (let d = 1; d <= daysInMonth; d += 1) {
            const iso = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            cells.push({ day: d, iso, isToday: iso === today, isCurrent: iso === currentDate });
        }
        return cells;
    }, [calendarMonth, currentDate, today]);

    const calendarPrevMonth = () => {
        if (!calendarMonth) return;
        const d = new Date(calendarMonth);
        d.setMonth(d.getMonth() - 1);
        setCalendarMonth(d);
    };

    const calendarNextMonth = () => {
        if (!calendarMonth) return;
        const d = new Date(calendarMonth);
        d.setMonth(d.getMonth() + 1);
        setCalendarMonth(d);
    };

    const toggleCalendar = () => {
        if (!showCalendar) initCalendarMonth();
        setShowCalendar((prev) => !prev);
    };

    useEffect(() => {
        const onClickOutside = (e) => {
            if (
                showCalendar &&
                calendarRef.current &&
                !calendarRef.current.contains(e.target) &&
                triggerRef.current &&
                !triggerRef.current.contains(e.target)
            ) {
                setShowCalendar(false);
            }
        };

        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, [showCalendar]);

    return (
        <div className="flex items-center gap-1.5 relative">
            <button
                onClick={prevDay}
                className="h-7 w-7 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:border-slate-400 hover:text-slate-900 transition-colors bg-white"
                title="Previous day"
            >
                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <button
                ref={triggerRef}
                onClick={toggleCalendar}
                title={isToday ? 'Viewing today' : 'Viewing a past/future date — click to pick'}
                className={[
                    'flex items-center gap-1.5 px-2.5 py-1 rounded-lg border transition-colors text-xs font-medium',
                    isToday ? 'border-slate-200 text-slate-700 hover:border-slate-400 bg-white' : 'border-amber-300 bg-amber-50 text-amber-800 hover:border-amber-400',
                ].join(' ')}
            >
                <svg className="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>{dateLabel}</span>
            </button>

            <button
                onClick={nextDay}
                className="h-7 w-7 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:border-slate-400 hover:text-slate-900 transition-colors bg-white"
                title="Next day"
            >
                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </button>

            {!isToday && (
                <button
                    onClick={goToday}
                    className="ml-0.5 px-2 py-1 rounded-md text-[11px] font-semibold bg-slate-900 text-white hover:bg-slate-800 transition-colors"
                >
                    Today
                </button>
            )}

            {showCalendar && (
                <div
                    ref={calendarRef}
                    className="absolute top-full mt-1.5 right-0 z-50 bg-white border border-slate-200 rounded-xl shadow-lg p-3 w-64"
                >
                    <div className="flex items-center justify-between mb-2">
                        <button
                            onClick={calendarPrevMonth}
                            className="h-6 w-6 flex items-center justify-center rounded-md hover:bg-slate-100 text-slate-500 hover:text-slate-900 transition-colors"
                        >
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <span className="text-xs font-semibold text-slate-700">{calendarTitle}</span>
                        <button
                            onClick={calendarNextMonth}
                            className="h-6 w-6 flex items-center justify-center rounded-md hover:bg-slate-100 text-slate-500 hover:text-slate-900 transition-colors"
                        >
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>

                    <div className="grid grid-cols-7 text-center mb-1">
                        {['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].map((dw) => (
                            <span key={dw} className="text-[10px] font-semibold text-slate-400 uppercase">
                                {dw}
                            </span>
                        ))}
                    </div>

                    <div className="grid grid-cols-7 gap-0.5">
                        {calendarDays.map((cell, idx) =>
                            cell ? (
                                <button
                                    key={cell.iso}
                                    onClick={() => navigate(cell.iso)}
                                    className={[
                                        'h-7 w-7 flex items-center justify-center rounded-md text-xs transition-colors',
                                        cell.isCurrent
                                            ? 'bg-slate-900 text-white font-semibold'
                                            : cell.isToday
                                                ? 'border border-slate-400 text-slate-900 font-semibold hover:bg-slate-100'
                                                : 'text-slate-600 hover:bg-slate-100',
                                    ].join(' ')}
                                >
                                    {cell.day}
                                </button>
                            ) : (
                                <div key={`blank-${idx}`} />
                            ),
                        )}
                    </div>

                    {!isToday && (
                        <button
                            onClick={goToday}
                            className="mt-2 w-full py-1 text-[11px] font-semibold text-center rounded-md bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors"
                        >
                            Go to Today
                        </button>
                    )}
                </div>
            )}
        </div>
    );
};

export default DateNavigator;
