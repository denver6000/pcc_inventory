import React from 'react';
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

    const navLink = (active) =>
        [
            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-colors whitespace-nowrap',
            active
                ? 'bg-slate-900 text-white shadow-sm'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100',
        ].join(' ');

    const navItems = [
        {
            label: 'Items',
            href: '/',
            active: url === '/' || url.startsWith('/?'),
            icon: (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M4 7h16M4 12h16M4 17h16"
                />
            ),
        },
        {
            label: 'Products',
            href: '/products',
            active: url.startsWith('/products'),
            icon: (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M3 7l9-4 9 4-9 4-9-4zm0 0v10l9 4 9-4V7"
                />
            ),
        },
        {
            label: 'POS',
            href: '/pos',
            active: url.startsWith('/pos'),
            icon: (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M4 5h16v14H4V5zm3 3h10M7 12h3m2 0h5"
                />
            ),
        },
        {
            label: 'Sales',
            href: '/sales-history',
            active: url.startsWith('/sales-history'),
            icon: (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M4 19h16M7 16V9m5 7V5m5 11v-4"
                />
            ),
        },
        {
            label: 'Produce',
            href: '/produce',
            active: url.startsWith('/produce'),
            icon: (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M12 21c4-3 7-6 7-10a7 7 0 10-14 0c0 4 3 7 7 10z"
                />
            ),
        },
        {
            label: 'Restock',
            href: '/restock',
            active: url.startsWith('/restock'),
            icon: (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M12 5v14m-5-5l5 5 5-5"
                />
            ),
        },
        {
            label: 'Units',
            href: '/units',
            active: url.startsWith('/units'),
            icon: (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M5 7h14M5 12h10M5 17h6"
                />
            ),
        },
    ];

    return (
        <div className={[ui.shell, ui.pageBg].join(' ')}>
            <div className="min-h-screen lg:flex">
                <aside className="lg:sticky lg:top-0 lg:h-screen lg:w-64 bg-white/90 backdrop-blur border-b lg:border-b-0 lg:border-r border-slate-200 shadow-sm lg:shadow-none flex flex-col">
                    <div className="h-16 px-4 flex items-center gap-3 border-b border-slate-200">
                        <span className="text-sm font-semibold tracking-tight text-slate-900">PCC Inventory</span>
                        <span className="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">Console</span>
                    </div>

                    <nav className="p-3 flex lg:block items-center gap-1.5 overflow-x-auto lg:overflow-visible">
                        {navItems.map((item) => (
                            <Link key={item.href} href={navHref(item.href)} className={navLink(item.active)}>
                                <svg className="h-4 w-4 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                                    {item.icon}
                                </svg>
                                <span>{item.label}</span>
                            </Link>
                        ))}
                    </nav>

                    <div className="px-3 pb-3 lg:pb-4 pt-2 border-t border-slate-200 mt-auto">
                        <DateNavigator />
                    </div>
                </aside>

                <main className="flex-1 max-w-5xl w-full mx-auto px-6 py-8">{children}</main>
            </div>
        </div>
    );
};

export default AppLayout;
