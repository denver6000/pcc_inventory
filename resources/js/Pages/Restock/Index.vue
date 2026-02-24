<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ui } from '@/theme';

const props = defineProps({
    batches: Array,
    items: Array,
});

// ── state ─────────────────────────────────────────────────────────────────
const mode       = ref(null);  // null | 'add'
const expandedId = ref(null);  // expanded batch id
const showRange  = ref(false); // toggle range sliders in the form

// toggleable column visibility for history table
const cols = ref({ date: true, notes: true, count: true, total: true });

// ── form ──────────────────────────────────────────────────────────────────
const form = useForm({
    notes: '',
    items: [],  // [{ item_id, quantity_added }]
});

// live batch total while filling the form
const liveTotal = computed(() =>
    form.items.reduce((sum, line) => {
        const item = getItem(line.item_id);
        return sum + (parseFloat(line.quantity_added) || 0) * (parseFloat(item?.cost_per_unit) || 0);
    }, 0),
);

// ── helpers ───────────────────────────────────────────────────────────────
function getItem(id) {
    return props.items.find(i => i.id == id) ?? null;
}

function lineSubtotal(line) {
    const item = getItem(line.item_id);
    return (parseFloat(line.quantity_added) || 0) * (parseFloat(item?.cost_per_unit) || 0);
}

// max for range slider: 2× default_stock or 100, whichever is larger
function rangeMax(line) {
    const item = getItem(line.item_id);
    return item ? Math.max((parseFloat(item.default_stock) || 0) * 2, 100) : 100;
}

const visibleColCount = computed(() => Object.values(cols.value).filter(Boolean).length);

// ── CRUD ──────────────────────────────────────────────────────────────────
function openAdd() {
    mode.value = 'add';
    form.reset();
    form.items = [{ item_id: '', quantity_added: '' }];
}

function closeForm() {
    mode.value = null;
    form.reset();
    form.items = [];
}

function addLine() {
    form.items.push({ item_id: '', quantity_added: '' });
}

function removeLine(idx) {
    form.items.splice(idx, 1);
}

function submitBatch() {
    form.post('/restock', { onSuccess: closeForm });
}

function toggleExpand(id) {
    expandedId.value = expandedId.value === id ? null : id;
}

// ── style helpers ─────────────────────────────────────────────────────────
const inputCls = ui.input;
const btnBlack = ui.button.primary;
const btnGhost = ui.button.secondary;
const pillBtn  = (active) => ui.pill(active);

const currency = (n) => parseFloat(n ?? 0).toFixed(2);
const fmt = (d) =>
    new Date(d).toLocaleString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
</script>

<template>
    <AppLayout>
        <Head title="Restock" />

        <!-- ── header ──────────────────────────────────────────── -->
        <div class="flex items-center justify-between mb-5">
            <div>
                <h1 :class="ui.heading">Restock</h1>
                <p class="text-xs text-slate-500">Record batches with snapshot costs and live totals.</p>
            </div>
            <button v-if="!mode" @click="openAdd" :class="btnBlack">+ New Batch</button>
        </div>

        <!-- ── new batch form ──────────────────────────────────── -->
        <div v-if="mode === 'add'" :class="[ui.card, 'p-5 mb-5']">
            <p :class="[ui.subheading, 'mb-4']">New Restock Batch</p>

            <!-- notes -->
            <div class="mb-4">
                <label :class="ui.fieldLabel">Notes</label>
                <input
                    type="text"
                    v-model="form.notes"
                    :class="inputCls"
                    placeholder="Optional — e.g. supplier name, PO number"
                />
            </div>

            <!-- items to restock -->
            <div class="mb-4">
                <div class="flex items-center justify-between mb-2">
                    <p :class="ui.subheading">Items to Restock</p>
                    <div class="flex items-center gap-2">
                        <!-- range slider toggle -->
                        <button
                            type="button"
                            @click="showRange = !showRange"
                            :class="pillBtn(showRange)"
                            title="Toggle quantity range sliders"
                        >
                            {{ showRange ? '⊟ Hide sliders' : '⊞ Show sliders' }}
                        </button>
                        <button
                            type="button"
                            @click="addLine"
                            class="text-xs text-slate-500 hover:text-slate-900 transition-colors"
                        >+ Add item</button>
                    </div>
                </div>

                <div
                    v-if="!form.items.length"
                    class="text-xs text-slate-400 py-3 text-center border border-dashed border-slate-200 rounded-lg bg-slate-50"
                >
                    No items — click "+ Add item" above.
                </div>

                <template v-else>
                    <!-- column headers (legends) -->
                    <div
                        class="grid gap-2 mb-1 text-xs text-gray-300"
                        :class="showRange ? 'grid-cols-[1fr_11rem_7rem_7rem_1.5rem]' : 'grid-cols-[1fr_7rem_7rem_7rem_1.5rem]'"
                    >
                        <span>Stock Item</span>
                        <span>Qty Added</span>
                        <span>Unit Cost</span>
                        <span class="text-right">Subtotal</span>
                        <span></span>
                    </div>

                    <!-- item rows -->
                    <div
                        v-for="(line, idx) in form.items"
                        :key="idx"
                        class="grid gap-2 mb-2 items-center"
                        :class="showRange ? 'grid-cols-[1fr_11rem_7rem_7rem_1.5rem]' : 'grid-cols-[1fr_7rem_7rem_7rem_1.5rem]'"
                    >
                        <!-- item select -->
                        <div>
                            <select v-model="line.item_id" :class="inputCls">
                                <option value="">— select item —</option>
                                <option v-for="item in items" :key="item.id" :value="item.id">
                                    {{ item.name }}{{ item.unit ? ` (${item.unit.abbreviation})` : '' }}
                                </option>
                            </select>
                            <p
                                v-if="form.errors[`items.${idx}.item_id`]"
                                class="text-xs text-red-500 mt-0.5"
                            >{{ form.errors[`items.${idx}.item_id`] }}</p>
                        </div>

                        <!-- qty: range slider + number, or just number -->
                        <div>
                            <div v-if="showRange" class="flex items-center gap-1.5">
                                <input
                                    type="range"
                                    v-model="line.quantity_added"
                                    min="0"
                                    :max="rangeMax(line)"
                                    step="0.01"
                                    class="flex-1 accent-black h-1"
                                />
                                <input
                                    type="number"
                                    v-model="line.quantity_added"
                                    min="0.0001"
                                    step="0.001"
                                    class="w-16 border border-gray-200 focus:border-gray-500 focus:outline-none px-2 py-1.5 text-sm bg-white"
                                    placeholder="0"
                                />
                            </div>
                            <input
                                v-else
                                type="number"
                                v-model="line.quantity_added"
                                min="0.0001"
                                step="0.001"
                                :class="inputCls"
                                placeholder="0"
                            />
                            <p
                                v-if="form.errors[`items.${idx}.quantity_added`]"
                                class="text-xs text-red-500 mt-0.5"
                            >{{ form.errors[`items.${idx}.quantity_added`] }}</p>
                        </div>

                        <!-- unit cost snapshot (read-only) -->
                        <div class="px-2.5 py-1.5 border border-gray-100 bg-gray-50 text-sm tabular-nums text-gray-400">
                            {{ currency(getItem(line.item_id)?.cost_per_unit ?? 0) }}
                        </div>

                        <!-- subtotal -->
                        <div class="px-2.5 py-1.5 text-sm tabular-nums text-right font-medium">
                            {{ currency(lineSubtotal(line)) }}
                        </div>

                        <!-- remove -->
                        <button
                            type="button"
                            @click="removeLine(idx)"
                            class="text-xs text-gray-300 hover:text-red-500 transition-colors text-center"
                        >✕</button>
                    </div>

                    <!-- total row -->
                    <div
                        class="grid gap-2 pt-2 mt-1 border-t border-gray-100"
                        :class="showRange ? 'grid-cols-[1fr_11rem_7rem_7rem_1.5rem]' : 'grid-cols-[1fr_7rem_7rem_7rem_1.5rem]'"
                    >
                        <span></span>
                        <span></span>
                        <p class="text-xs text-gray-400 self-center text-right pr-2">Batch Total</p>
                        <p class="text-sm font-semibold tabular-nums text-right">{{ currency(liveTotal) }}</p>
                        <span></span>
                    </div>
                </template>
            </div>

            <!-- actions -->
            <div class="flex gap-2 pt-1">
                <button
                    @click="submitBatch"
                    :disabled="form.processing || !form.items.length"
                    :class="btnBlack"
                >
                    Commit Batch
                </button>
                <button @click="closeForm" :class="btnGhost">Cancel</button>
            </div>
        </div>

        <!-- ── column toggles (toggleable legends) ─────────────── -->
        <div class="flex items-center gap-1.5 mb-2 flex-wrap">
            <span class="text-xs text-slate-400 mr-0.5">Columns:</span>
            <button @click="cols.date  = !cols.date"  :class="pillBtn(cols.date)">Date</button>
            <button @click="cols.notes = !cols.notes" :class="pillBtn(cols.notes)">Notes</button>
            <button @click="cols.count = !cols.count" :class="pillBtn(cols.count)">Items</button>
            <button @click="cols.total = !cols.total" :class="pillBtn(cols.total)">Total Cost</button>
        </div>

        <!-- ── batch history ───────────────────────────────────── -->
        <div :class="ui.table">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-slate-200" :class="ui.tableHead">
                        <th class="py-3 px-3 text-left whitespace-nowrap">Batch ID</th>
                        <th v-if="cols.date"  class="py-3 px-3 text-left whitespace-nowrap">Date</th>
                        <th v-if="cols.notes" class="py-3 px-3 text-left">Notes</th>
                        <th v-if="cols.count" class="py-3 px-3 text-right">Items</th>
                        <th v-if="cols.total" class="py-3 px-3 text-right">Total Cost</th>
                        <th class="w-8 py-3 px-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="batch in batches" :key="batch.id">
                        <!-- summary row -->
                        <tr
                            class="border-b border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer"
                            :class="expandedId === batch.id ? 'bg-slate-50' : ''"
                            @click="toggleExpand(batch.id)"
                        >
                            <td class="py-2 px-3">
                                <span class="font-mono text-xs font-semibold tracking-wide text-slate-800 bg-slate-100 px-1.5 py-0.5 rounded">{{ batch.batch_code }}</span>
                            </td>
                            <td v-if="cols.date"  class="py-2 px-3 text-sm tabular-nums text-slate-500 whitespace-nowrap">{{ fmt(batch.created_at) }}</td>
                            <td v-if="cols.notes" class="py-2 px-3 text-sm text-slate-600">{{ batch.notes || '—' }}</td>
                            <td v-if="cols.count" class="py-2 px-3 text-sm text-right tabular-nums text-slate-600">{{ batch.items.length }}</td>
                            <td v-if="cols.total" class="py-2 px-3 text-sm text-right tabular-nums font-semibold text-slate-900">{{ currency(batch.total_cost) }}</td>
                            <td class="py-2 px-3 text-center">
                                <span class="text-xs text-slate-400 select-none">{{ expandedId === batch.id ? '▲' : '▼' }}</span>
                            </td>
                        </tr>

                        <!-- expanded line-item detail -->
                        <tr v-if="expandedId === batch.id">
                            <td :colspan="visibleColCount + 2" class="px-6 pb-3 pt-1 bg-slate-50 border-b border-slate-100">
                                <p class="text-xs text-slate-500 mb-2">
                                    Batch <span class="font-mono font-semibold text-slate-800">{{ batch.batch_code }}</span>
                                    <span v-if="batch.notes" class="ml-2 text-slate-400">— {{ batch.notes }}</span>
                                </p>
                                <table class="w-full">
                                    <thead>
                                        <tr>
                                            <th class="text-left text-xs text-slate-400 font-normal pb-1">Item</th>
                                            <th class="text-right text-xs text-slate-400 font-normal pb-1">Qty Added</th>
                                            <th class="text-right text-xs text-slate-400 font-normal pb-1">Unit Cost</th>
                                            <th class="text-right text-xs text-slate-400 font-normal pb-1">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="bi in batch.items"
                                            :key="bi.id"
                                            class="border-t border-slate-100"
                                        >
                                            <td class="py-1.5 text-sm text-slate-800">
                                                {{ bi.item.name }}
                                                <span v-if="bi.item.unit" class="text-xs text-slate-400 ml-0.5">{{ bi.item.unit.abbreviation }}</span>
                                            </td>
                                            <td class="py-1.5 text-sm text-right tabular-nums text-emerald-700 font-semibold">+{{ bi.quantity_added }}</td>
                                            <td class="py-1.5 text-sm text-right tabular-nums text-slate-500">{{ currency(bi.cost_per_unit) }}</td>
                                            <td class="py-1.5 text-sm text-right tabular-nums font-semibold text-slate-900">{{ currency(bi.subtotal) }}</td>
                                        </tr>
                                        <tr class="border-t border-slate-200">
                                            <td colspan="3" class="pt-2 pb-0.5 text-xs text-slate-500 text-right pr-3 font-medium">Batch Total</td>
                                            <td class="pt-2 pb-0.5 text-sm text-right tabular-nums font-semibold text-slate-900">{{ currency(batch.total_cost) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </template>

                    <tr v-if="!batches.length">
                        <td :colspan="visibleColCount + 1" class="py-14 text-center text-xs text-gray-300">
                            No restock batches yet.
                            <button @click="openAdd" class="underline hover:text-gray-500">Create the first one</button>.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
