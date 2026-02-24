<script setup>
import { ref, watch, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ui } from '@/theme';

const page = usePage();

const props = defineProps({
    items: Array,
    units: Array,
});

// ── form state ──────────────────────────────────────────
// null = hidden, 'add' = new item, number = editing item id
const mode = ref(null);
const imagePreview = ref(null);
const imgInput = ref(null);
const infoItem = ref(null);
const consumeOpen = ref(false);
const consumeOrder = ref([]); // [{id, order}]
const consumeForm = useForm({
    quantity: '',
    batch_order: [],
    journal_date: '',
});

const totalBatchQty = computed(() =>
    (infoItem.value?.restock_batch_items ?? []).reduce((sum, line) => sum + (parseFloat(line.quantity_added) || 0), 0),
);

watch(infoItem, (val) => {
    if (val && val.restock_batch_items) {
        consumeOrder.value = val.restock_batch_items.map((line, idx) => ({ id: line.id, order: idx + 1 }));
    } else {
        consumeOrder.value = [];
    }
    consumeForm.reset();
    consumeOpen.value = false;
});

const form = useForm({
    name: '',
    image: null,
    unit_id: '',
    cost_per_unit: 0,
    default_stock: '',
    journal_date: '',
});

function openAdd() {
    mode.value = 'add';
    form.reset();
    imagePreview.value = null;
}

function openEdit(item) {
    mode.value = item.id;
    form.name = item.name;
    form.unit_id = item.unit_id ?? '';
    form.cost_per_unit = item.cost_per_unit ?? 0;
    form.default_stock = item.default_stock;
    form.image = null;
    imagePreview.value = item.image_path ? `/storage/${item.image_path}` : null;
}

function closeForm() {
    mode.value = null;
    form.reset();
    imagePreview.value = null;
}

function handleImage(e) {
    const file = e.target.files[0];
    if (!file) return;
    form.image = file;
    imagePreview.value = URL.createObjectURL(file);
}

function submit() {
    form.journal_date = page.props.currentDate;
    if (mode.value === 'add') {
        form.post('/items', { preserveScroll: true, onSuccess: closeForm });
    } else {
        form.put(`/items/${mode.value}`, { preserveScroll: true, onSuccess: closeForm });
    }
}

function remove(item) {
    if (confirm(`Delete "${item.name}"?`)) {
        router.delete(`/items/${item.id}`);
    }
}

// stock breakdown helpers
const batchQty = (item) =>
    (item?.restock_batch_items ?? []).reduce((sum, line) => sum + (parseFloat(line.quantity_added) || 0), 0);
const batchValue = (item) =>
    (item?.restock_batch_items ?? []).reduce(
        (sum, line) => sum + (parseFloat(line.quantity_added) || 0) * (parseFloat(line.cost_per_unit) || 0),
        0,
    );
const fmtDate = (d) =>
    new Date(d).toLocaleString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });

function openInfo(item) {
    infoItem.value = item;
}

function closeInfo() {
    infoItem.value = null;
}

function openConsume() {
    consumeOpen.value = true;
    if (!consumeOrder.value.length && infoItem.value?.restock_batch_items) {
        consumeOrder.value = infoItem.value.restock_batch_items.map((line, idx) => ({ id: line.id, order: idx + 1 }));
    }
}

function sortedOrders() {
    return [...consumeOrder.value]
        .filter(o => o.order && o.id)
        .sort((a, b) => a.order - b.order)
        .map(o => o.id);
}

function orderFor(id) {
    return consumeOrder.value.find(o => o.id === id)?.order ?? '';
}

function setOrder(id, value) {
    const existing = consumeOrder.value.find(o => o.id === id);
    if (existing) {
        existing.order = value;
    } else {
        consumeOrder.value.push({ id, order: value });
    }
}

function submitConsume() {
    consumeForm.batch_order = sortedOrders();
    consumeForm.journal_date = page.props.currentDate;
    if (!consumeForm.batch_order.length) {
        consumeForm.setError('batch_order', 'Select at least one batch to consume from.');
        return;
    }
    consumeForm.post(`/items/${infoItem.value.id}/consume`, {
        preserveScroll: true,
        onSuccess: () => {
            consumeForm.reset();
            consumeOpen.value = false;
        },
    });
}

// shared input / button class helpers
const inputClass = ui.input;
const btnPrimary = ui.button.primary;
const btnSecondary = ui.button.secondary;
</script>

<template>
    <AppLayout>
        <Head title="Items" />

        <!-- ── Page header ───────────────────────────── -->
        <div class="flex items-center justify-between mb-5">
            <div>
                <h1 :class="ui.heading">Items</h1>
                <p class="text-xs text-slate-500">Manage stock images, units, and counts.</p>
            </div>
            <button v-if="!mode" @click="openAdd" :class="btnPrimary">
                + New Item
            </button>
        </div>

        <!-- ── Add / Edit form panel ─────────────────── -->
        <div v-if="mode" :class="[ui.card, 'p-5 mb-5']">
            <p :class="[ui.subheading, 'mb-3']">
                {{ mode === 'add' ? 'New Item' : 'Edit Item' }}
            </p>

            <div class="flex flex-wrap items-end gap-3">

                <!-- Image picker -->
                <div>
                    <label :class="ui.fieldLabel">Image</label>
                    <div
                        class="h-12 w-12 border border-slate-200 rounded-lg flex items-center justify-center cursor-pointer overflow-hidden hover:border-slate-400 transition-colors bg-slate-50"
                        @click="imgInput.click()"
                        title="Click to pick image"
                    >
                        <img v-if="imagePreview" :src="imagePreview" class="h-full w-full object-cover" />
                        <span v-else class="text-slate-400 text-xl leading-none select-none">+</span>
                    </div>
                    <input ref="imgInput" type="file" accept="image/*" @change="handleImage" class="hidden" />
                </div>

                <!-- Name -->
                <div class="flex-1 min-w-40">
                    <label :class="ui.fieldLabel">Name <span class="text-red-400">*</span></label>
                    <input type="text" v-model="form.name" :class="inputClass" placeholder="Item name" />
                    <p v-if="form.errors.name" class="text-xs text-red-500 mt-0.5">{{ form.errors.name }}</p>
                </div>

                <!-- Unit -->
                <div class="w-40">
                    <label :class="ui.fieldLabel">Unit</label>
                    <select v-model="form.unit_id" :class="inputClass">
                        <option value="">— none —</option>
                        <option v-for="u in units" :key="u.id" :value="u.id">
                            {{ u.name }} ({{ u.abbreviation }})
                        </option>
                    </select>
                    <p v-if="!units.length" class="text-xs text-gray-300 mt-0.5">
                        <a href="/units" class="underline hover:text-gray-500">Add units first</a>
                    </p>
                </div>

                <!-- Cost per unit -->
                <div class="w-28">
                    <label :class="ui.fieldLabel">Cost / unit</label>
                    <input type="number" v-model="form.cost_per_unit" min="0" step="0.01" :class="inputClass" placeholder="0.00" />
                    <p v-if="form.errors.cost_per_unit" class="text-xs text-red-500 mt-0.5">{{ form.errors.cost_per_unit }}</p>
                </div>

                <!-- Default Stock -->
                <div class="w-28">
                    <label :class="ui.fieldLabel">Default Stock <span class="text-red-400">*</span></label>
                    <input type="number" v-model="form.default_stock" min="0" step="0.01" :class="inputClass" placeholder="0" />
                    <p v-if="form.errors.default_stock" class="text-xs text-red-500 mt-0.5">{{ form.errors.default_stock }}</p>
                </div>

                <!-- Current Stock (edit only) -->
                    <!-- Current Stock has been removed -->

                <!-- Actions -->
                <div class="flex gap-2 pb-px">
                    <button @click="submit" :disabled="form.processing" :class="btnPrimary">
                        {{ mode === 'add' ? 'Add' : 'Save' }}
                    </button>
                    <button @click="closeForm" :class="btnSecondary">Cancel</button>
                </div>
            </div>
        </div>

        <!-- ── Items table ───────────────────────────── -->
        <div :class="ui.table">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-slate-200" :class="ui.tableHead">
                        <th class="w-12 py-3 px-3"></th>
                        <th class="py-3 px-3 text-left">Name</th>
                        <th class="py-3 px-3 text-left">Unit</th>
                        <th class="py-3 px-3 text-right">Cost/unit</th>
                        <th class="py-3 px-3 text-right">Default</th>
                        <th class="py-3 px-3 text-right">Stock</th>
                        <th class="w-24 py-3 px-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        class="border-b border-slate-100 last:border-0 hover:bg-slate-50 transition-colors"
                        :class="{ 'bg-slate-50': mode === item.id }"
                    >
                        <!-- Thumbnail -->
                        <td class="py-2 px-3">
                            <img
                                v-if="item.image_path"
                                :src="`/storage/${item.image_path}`"
                                class="h-9 w-9 object-cover"
                            />
                            <div v-else class="h-9 w-9 bg-slate-100 rounded"></div>
                        </td>

                        <td class="py-2 px-3 text-sm font-medium text-slate-900">{{ item.name }}</td>

                        <td class="py-2 px-3 text-sm text-slate-500">
                            <span v-if="item.unit">{{ item.unit.abbreviation }}</span>
                            <span v-else class="text-slate-300">—</span>
                        </td>

                        <td class="py-2 px-3 text-sm text-right tabular-nums text-slate-500">
                            <span v-if="item.cost_per_unit > 0">{{ item.cost_per_unit }}</span>
                            <span v-else class="text-slate-300">—</span>
                        </td>
                        <td class="py-2 px-3 text-sm text-right tabular-nums font-medium text-slate-700">{{ item.default_stock }}</td>
                        <td class="py-2 px-3 text-sm text-right tabular-nums font-medium text-slate-700">{{ item.current_stock }}</td>

                        <td class="py-2 px-3 text-right whitespace-nowrap">
                            <button
                                @click="openInfo(item)"
                                class="text-xs text-slate-500 hover:text-slate-900 transition-colors mr-3"
                                title="View batch breakdown"
                            >ⓘ See Info</button>
                            <button
                                @click="openEdit(item)"
                                class="text-xs text-slate-500 hover:text-slate-900 transition-colors mr-3"
                            >Edit</button>
                            <button
                                @click="remove(item)"
                                class="text-xs text-slate-500 hover:text-rose-600 transition-colors"
                            >Delete</button>
                        </td>
                    </tr>

                    <tr v-if="!items.length">
                        <td colspan="6" class="py-14 text-center text-xs text-slate-400">
                            No items yet. Click <button @click="openAdd" class="underline hover:text-slate-700">New Item</button> to get started.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Info modal -->
        <div v-if="infoItem" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-start justify-center z-50 p-4">
            <div :class="[ui.card, 'w-full max-w-3xl p-5 shadow-lg border border-slate-300']">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.12em] text-slate-500 font-semibold mb-1">Item Info</p>
                        <h2 class="text-lg font-semibold text-slate-900">{{ infoItem.name }}</h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Unit: {{ infoItem.unit?.abbreviation ?? '—' }} · Cost/unit: {{ infoItem.cost_per_unit ?? 0 }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-slate-900">Current stock: {{ infoItem.current_stock }}</p>
                        <p class="text-xs text-slate-500">Default: {{ infoItem.default_stock }}</p>
                    </div>
                </div>

                <div class="bg-neutral-900/40 border border-neutral-800 rounded-xl p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-neutral-400">Current stock</p>
                            <p class="text-2xl font-semibold text-neutral-50">{{ infoItem.current_stock }} {{ infoItem.unit?.abbreviation ?? '' }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="px-3 py-2 rounded-lg bg-white/5 border border-neutral-800 text-sm hover:bg-white/10" @click="openConsume">Consume manually</button>
                        </div>
                    </div>
                    <p class="text-sm text-neutral-400">Batch-backed stock. Use manual consume to choose which batches to draw down.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                    <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                        <p class="text-[11px] uppercase tracking-[0.12em] text-slate-500 font-semibold mb-1">From batches</p>
                        <p class="text-xl font-semibold text-slate-900">{{ batchQty(infoItem).toFixed(2) }}</p>
                        <p class="text-xs text-slate-500">Sum of batch quantities</p>
                    </div>
                    <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                        <p class="text-[11px] uppercase tracking-[0.12em] text-slate-500 font-semibold mb-1">Valuation</p>
                        <p class="text-xl font-semibold text-slate-900">{{ batchValue(infoItem).toFixed(2) }}</p>
                        <p class="text-xs text-slate-500">Σ qty × cost_per_unit</p>
                    </div>
                    <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                        <p class="text-[11px] uppercase tracking-[0.12em] text-slate-500 font-semibold mb-1">Variance</p>
                        <p class="text-xl font-semibold" :class="Math.abs(batchQty(infoItem) - (parseFloat(infoItem.current_stock) || 0)) < 0.0001 ? 'text-emerald-700' : 'text-amber-700'">
                            {{ (batchQty(infoItem) - (parseFloat(infoItem.current_stock) || 0)).toFixed(2) }}
                        </p>
                        <p class="text-xs text-slate-500">Batches − current_stock</p>
                    </div>
                </div>

                <div class="mb-3 flex items-center justify-between">
                    <p :class="ui.subheading">Batch breakdown</p>
                    <button @click="closeInfo" :class="ui.button.ghost">Close</button>
                </div>

                <div class="border border-slate-200 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead :class="ui.tableHead">
                            <tr class="border-b border-slate-200">
                                <th class="py-2 px-3 text-left">Batch</th>
                                <th class="py-2 px-3 text-left">Date</th>
                                <th class="py-2 px-3 text-right">Remaining</th>
                                <th class="py-2 px-3 text-right">Unit Cost</th>
                                <th class="py-2 px-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in infoItem.restock_batch_items ?? []" :key="line.id" class="border-b border-slate-100 last:border-0">
                                <td class="py-2 px-3 font-mono text-xs font-semibold text-slate-800">{{ line.batch?.batch_code ?? '—' }}</td>
                                <td class="py-2 px-3 text-xs text-slate-500">{{ line.batch?.created_at ? fmtDate(line.batch.created_at) : '—' }}</td>
                                <td class="py-2 px-3 text-right tabular-nums text-slate-700">{{ parseFloat(line.quantity_added).toFixed(4) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums text-slate-500">{{ parseFloat(line.cost_per_unit).toFixed(4) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums font-medium text-slate-900">{{ parseFloat(line.subtotal).toFixed(4) }}</td>
                            </tr>
                            <tr v-if="!(infoItem.restock_batch_items ?? []).length">
                                <td colspan="5" class="py-6 text-center text-xs text-slate-400">No batch records for this item.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Manual consume modal -->
        <div
            v-if="consumeOpen"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-start justify-center z-50 p-4"
        >
            <div class="bg-neutral-950 border border-neutral-800 rounded-2xl shadow-2xl w-full max-w-2xl p-5 space-y-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.14em] text-neutral-500 font-semibold mb-1">Manual consume</p>
                        <h3 class="text-lg font-semibold text-neutral-50">{{ infoItem?.name }}</h3>
                        <p class="text-xs text-neutral-400">Select batch order and enter how much to consume.</p>
                    </div>
                    <button type="button" class="text-neutral-400 hover:text-neutral-200 text-sm" @click="consumeOpen = false">Close</button>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-neutral-900/60 border border-neutral-800 rounded-xl p-3">
                        <p class="text-[11px] uppercase tracking-[0.12em] text-neutral-500 font-semibold mb-1">Current stock</p>
                        <p class="text-xl font-semibold text-neutral-50">{{ infoItem?.current_stock }} {{ infoItem?.unit?.abbreviation ?? '' }}</p>
                    </div>
                    <div class="bg-neutral-900/60 border border-neutral-800 rounded-xl p-3">
                        <p class="text-[11px] uppercase tracking-[0.12em] text-neutral-500 font-semibold mb-1">Selected batches</p>
                        <p class="text-xl font-semibold text-neutral-50">{{ totalBatchQty.toFixed(4) }}</p>
                    </div>
                    <div class="bg-neutral-900/60 border border-neutral-800 rounded-xl p-3">
                        <p class="text-[11px] uppercase tracking-[0.12em] text-neutral-500 font-semibold mb-1">Unit</p>
                        <p class="text-xl font-semibold text-neutral-50">{{ infoItem?.unit?.abbreviation ?? '—' }}</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-sm text-neutral-300">Quantity to consume</label>
                    <input
                        type="number"
                        v-model="consumeForm.quantity"
                        min="0"
                        step="0.0001"
                        class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2 text-neutral-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="0.0000"
                    />
                    <p v-if="consumeForm.errors.quantity" class="text-xs text-rose-400">{{ consumeForm.errors.quantity }}</p>
                </div>

                <div class="border border-neutral-800 rounded-xl overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-neutral-900 text-neutral-400 uppercase text-[11px] tracking-wide">
                            <tr>
                                <th class="py-2 px-3 text-left w-24">Order</th>
                                <th class="py-2 px-3 text-left">Batch</th>
                                <th class="py-2 px-3 text-right">Remaining</th>
                                <th class="py-2 px-3 text-right">Unit Cost</th>
                                <th class="py-2 px-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-900">
                            <tr v-for="line in infoItem?.restock_batch_items ?? []" :key="line.id">
                                <td class="py-2 px-3">
                                    <input
                                        type="number"
                                        :value="orderFor(line.id)"
                                        @input="setOrder(line.id, $event.target.value ? parseInt($event.target.value) : '')"
                                        class="w-20 bg-neutral-900 border border-neutral-800 rounded px-2 py-1 text-neutral-50 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                        placeholder="#"
                                    />
                                </td>
                                <td class="py-2 px-3">
                                    <div class="font-mono text-xs text-neutral-200">{{ line.batch?.batch_code ?? '—' }}</div>
                                    <div class="text-[11px] text-neutral-500">{{ line.batch?.created_at ? fmtDate(line.batch.created_at) : '—' }}</div>
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums text-neutral-100">{{ parseFloat(line.quantity_added).toFixed(4) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums text-neutral-400">{{ parseFloat(line.cost_per_unit).toFixed(4) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums text-neutral-200">{{ parseFloat(line.subtotal).toFixed(4) }}</td>
                            </tr>
                            <tr v-if="!(infoItem?.restock_batch_items ?? []).length">
                                <td colspan="5" class="py-6 text-center text-xs text-neutral-500">No batches available.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="consumeForm.errors.batch_order" class="text-xs text-rose-400">{{ consumeForm.errors.batch_order }}</p>

                <div class="flex items-center justify-between">
                    <p class="text-xs text-neutral-500">Set order numbers to control which batches are consumed first.</p>
                    <div class="flex gap-2">
                        <button type="button" class="px-3 py-2 rounded-lg border border-neutral-800 text-neutral-200 hover:bg-neutral-900" @click="consumeOpen = false">Cancel</button>
                        <button type="button" class="px-3 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-500" :disabled="consumeForm.processing" @click="submitConsume">
                            Consume
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
