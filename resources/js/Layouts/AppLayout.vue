<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ui } from '@/theme';
import DateNavigator from '@/Components/DateNavigator.vue';

const page = usePage();
const url = computed(() => page.url);
const currentDate = computed(() => page.props.currentDate);
const today = new Date().toISOString().slice(0, 10);

/** Append ?date= to nav hrefs when viewing a non-today date */
const navHref = (base) => {
    if (!currentDate.value || currentDate.value === today) return base;
    return `${base}?date=${currentDate.value}`;
};

const navLink = (active) => [
    'text-sm px-3 py-1.5 rounded-full transition-colors flex items-center gap-1',
    active
        ? 'bg-slate-900 text-white shadow-sm'
        : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100',
].join(' ');
</script>

<template>
    <div :class="[ui.shell, ui.pageBg]">
        <nav class="sticky top-0 z-10 bg-white/90 backdrop-blur border-b border-slate-200 shadow-sm">
            <div class="max-w-5xl mx-auto px-6 h-14 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-semibold tracking-tight text-slate-900">PCC Inventory</span>
                    <span class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">Console</span>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <Link :href="navHref('/')" :class="navLink(url === '/' || url.startsWith('/?'))">
                            <span>Items</span>
                        </Link>
                        <Link :href="navHref('/products')" :class="navLink(url.startsWith('/products'))">
                            <span>Products</span>
                        </Link>
                        <Link :href="navHref('/produce')" :class="navLink(url.startsWith('/produce'))">
                            <span>Produce</span>
                        </Link>
                        <Link :href="navHref('/restock')" :class="navLink(url.startsWith('/restock'))">
                            <span>Restock</span>
                        </Link>
                        <Link :href="navHref('/units')" :class="navLink(url.startsWith('/units'))">
                            <span>Units</span>
                        </Link>
                    </div>
                    <div class="h-5 w-px bg-slate-200" />
                    <DateNavigator />
                </div>
            </div>
        </nav>
        <main class="max-w-5xl mx-auto px-6 py-8">
            <slot />
        </main>
    </div>
</template>
