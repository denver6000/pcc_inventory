<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const currentDate = computed(() => page.props.currentDate);

const today = new Date().toISOString().slice(0, 10);
const isToday = computed(() => currentDate.value === today);

// Calendar popup state
const showCalendar = ref(false);
const calendarRef = ref(null);
const triggerRef = ref(null);

function navigate(date) {
    // Build URL with the date param while preserving other query params
    const url = new URL(window.location.href);
    if (date === today) {
        url.searchParams.delete('date');
    } else {
        url.searchParams.set('date', date);
    }
    router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true });
    showCalendar.value = false;
}

function prevDay() {
    const d = new Date(currentDate.value + 'T00:00:00');
    d.setDate(d.getDate() - 1);
    navigate(d.toISOString().slice(0, 10));
}

function nextDay() {
    const d = new Date(currentDate.value + 'T00:00:00');
    d.setDate(d.getDate() + 1);
    navigate(d.toISOString().slice(0, 10));
}

function goToday() {
    navigate(today);
}

// Human-readable label: "Mon, Feb 24, 2026"
const dateLabel = computed(() => {
    const d = new Date(currentDate.value + 'T00:00:00');
    return d.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
});

// ── Calendar grid logic ──────────────────────────────────
const calendarMonth = ref(null); // Date object for the month being viewed

function initCalendarMonth() {
    calendarMonth.value = new Date(currentDate.value + 'T00:00:00');
}

const calendarTitle = computed(() => {
    if (!calendarMonth.value) return '';
    return calendarMonth.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
});

const calendarDays = computed(() => {
    if (!calendarMonth.value) return [];
    const year = calendarMonth.value.getFullYear();
    const month = calendarMonth.value.getMonth();
    const first = new Date(year, month, 1);
    const startDay = first.getDay(); // 0=Sun
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const cells = [];
    // Fill blanks for days before the 1st
    for (let i = 0; i < startDay; i++) cells.push(null);
    for (let d = 1; d <= daysInMonth; d++) {
        const iso = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        cells.push({ day: d, iso, isToday: iso === today, isCurrent: iso === currentDate.value });
    }
    return cells;
});

function calendarPrevMonth() {
    const d = new Date(calendarMonth.value);
    d.setMonth(d.getMonth() - 1);
    calendarMonth.value = d;
}

function calendarNextMonth() {
    const d = new Date(calendarMonth.value);
    d.setMonth(d.getMonth() + 1);
    calendarMonth.value = d;
}

function toggleCalendar() {
    if (!showCalendar.value) initCalendarMonth();
    showCalendar.value = !showCalendar.value;
}

// Close on outside click
function onClickOutside(e) {
    if (
        showCalendar.value &&
        calendarRef.value &&
        !calendarRef.value.contains(e.target) &&
        triggerRef.value &&
        !triggerRef.value.contains(e.target)
    ) {
        showCalendar.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside));
</script>

<template>
    <div class="flex items-center gap-1.5 relative">
        <!-- Previous day -->
        <button
            @click="prevDay"
            class="h-7 w-7 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:border-slate-400 hover:text-slate-900 transition-colors bg-white"
            title="Previous day"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </button>

        <!-- Date display / toggle calendar -->
        <button
            ref="triggerRef"
            @click="toggleCalendar"
            class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg border transition-colors text-xs font-medium"
            :class="isToday
                ? 'border-slate-200 text-slate-700 hover:border-slate-400 bg-white'
                : 'border-amber-300 bg-amber-50 text-amber-800 hover:border-amber-400'"
            :title="isToday ? 'Viewing today' : 'Viewing a past/future date — click to pick'"
        >
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span>{{ dateLabel }}</span>
        </button>

        <!-- Next day -->
        <button
            @click="nextDay"
            class="h-7 w-7 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:border-slate-400 hover:text-slate-900 transition-colors bg-white"
            title="Next day"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>

        <!-- Today button (only when not on today) -->
        <button
            v-if="!isToday"
            @click="goToday"
            class="ml-0.5 px-2 py-1 rounded-md text-[11px] font-semibold bg-slate-900 text-white hover:bg-slate-800 transition-colors"
        >
            Today
        </button>

        <!-- Calendar popup -->
        <div
            v-if="showCalendar"
            ref="calendarRef"
            class="absolute top-full mt-1.5 right-0 z-50 bg-white border border-slate-200 rounded-xl shadow-lg p-3 w-64"
        >
            <!-- Month header -->
            <div class="flex items-center justify-between mb-2">
                <button @click="calendarPrevMonth" class="h-6 w-6 flex items-center justify-center rounded-md hover:bg-slate-100 text-slate-500 hover:text-slate-900 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <span class="text-xs font-semibold text-slate-700">{{ calendarTitle }}</span>
                <button @click="calendarNextMonth" class="h-6 w-6 flex items-center justify-center rounded-md hover:bg-slate-100 text-slate-500 hover:text-slate-900 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>

            <!-- Day-of-week headers -->
            <div class="grid grid-cols-7 text-center mb-1">
                <span v-for="dw in ['Su','Mo','Tu','We','Th','Fr','Sa']" :key="dw" class="text-[10px] font-semibold text-slate-400 uppercase">{{ dw }}</span>
            </div>

            <!-- Day cells -->
            <div class="grid grid-cols-7 gap-0.5">
                <template v-for="(cell, i) in calendarDays" :key="i">
                    <div v-if="!cell" />
                    <button
                        v-else
                        @click="navigate(cell.iso)"
                        class="h-7 w-7 flex items-center justify-center rounded-md text-xs transition-colors"
                        :class="[
                            cell.isCurrent
                                ? 'bg-slate-900 text-white font-semibold'
                                : cell.isToday
                                    ? 'border border-slate-400 text-slate-900 font-semibold hover:bg-slate-100'
                                    : 'text-slate-600 hover:bg-slate-100',
                        ]"
                    >
                        {{ cell.day }}
                    </button>
                </template>
            </div>

            <!-- Quick Today link in calendar -->
            <button
                v-if="!isToday"
                @click="goToday"
                class="mt-2 w-full py-1 text-[11px] font-semibold text-center rounded-md bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors"
            >
                Go to Today
            </button>
        </div>
    </div>
</template>
