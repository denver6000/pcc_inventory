import React, { useMemo } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ui } from '@/theme';
import DateNavigator from '@/Components/DateNavigator';

const AppLayout = ({ children }) => {
    const page = usePage();
    const url = page.url;
    const currentDate = page.props.currentDate;
    const today = new Date().toISOString().slice(0, 10);

    const navHref = (base) => {
        if (!currentDate || currentDate === today) return base;
        return `${base}?date=${currentDate}`;
    };

    const navLink = useMemo(
        () =>
            (active) =>
                [
                    'text-sm px-3 py-1.5 rounded-full transition-colors flex items-center gap-1',
                    active ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100',
                ].join(' '),
        [],
    );

    return (
        <div className={[ui.shell, ui.pageBg].join(' ')}>
            <nav className="sticky top-0 z-10 bg-white/90 backdrop-blur border-b border-slate-200 shadow-sm">
                <div className="max-w-5xl mx-auto px-6 h-14 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <span className="text-sm font-semibold tracking-tight text-slate-900">PCC Inventory</span>
                        <span className="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">Console</span>
                    </div>
                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                            <Link href={navHref('/')} className={navLink(url === '/' || url.startsWith('/?'))}>
                                <span>Items</span>
                            </Link>
                            <Link href={navHref('/products')} className={navLink(url.startsWith('/products'))}>
                                <span>Products</span>
                            </Link>
                            <Link href={navHref('/pos')} className={navLink(url.startsWith('/pos'))}>
                                <span>POS</span>
                            </Link>
                            <Link href={navHref('/produce')} className={navLink(url.startsWith('/produce'))}>
                                <span>Produce</span>
                            </Link>
                            <Link href={navHref('/restock')} className={navLink(url.startsWith('/restock'))}>
                                <span>Restock</span>
                            </Link>
                            <Link href={navHref('/units')} className={navLink(url.startsWith('/units'))}>
                                <span>Units</span>
                            </Link>
                        </div>
                        <div className="h-5 w-px bg-slate-200" />
                        <DateNavigator />
                    </div>
                </div>
            </nav>
            <main className="max-w-5xl mx-auto px-6 py-8">{children}</main>
        </div>
    );
};

export default AppLayout;
