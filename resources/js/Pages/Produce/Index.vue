<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ui } from '@/theme';

const page = usePage();

const props = defineProps({ products: Array });

const selectedId = ref(null);
const form = useForm({
    product_id: '',
    quantity: 1,
    notes: '',
    journal_date: '',
    batch_orders: {},
});

const batchOrders = ref({}); // { [itemId]: [{id, order}] }

const product = computed(() => props.products.find(p => p.id === selectedId.value) ?? null);

const maxBuild = computed(() => {
    if (!product.value || !product.value.ingredients?.length) return 0;
    let min = Infinity;
    product.value.ingredients.forEach(ing => {
        const needPerUnit = parseFloat(ing.quantity) || 0;
        const available = parseFloat(ing.item?.current_stock) || 0;
        if (needPerUnit <= 0) return;
        min = Math.min(min, available / needPerUnit);
    });
    return Number.isFinite(min) ? min : 0;
});

const ingredientStatus = (ing) => {
    const qty = parseFloat(form.quantity) || 0;
    const required = parseFloat(ing.quantity) * qty;
    const available = parseFloat(ing.item?.current_stock) || 0;
    return {
        required: Number.isFinite(required) ? required : 0,
        available,
        ok: available + 1e-9 >= required,
        unit: ing.item?.unit?.abbreviation ?? '',
    };
};

function select(p) {
    selectedId.value = p.id;
    form.product_id = p.id;
    form.quantity = 1;
    form.notes = '';
    form.clearErrors();
    primeBatchOrders(p);
}

function submit() {
    form.product_id = selectedId.value;
    form.journal_date = page.props.currentDate;
    form.batch_orders = normalisedOrders();
    form.post('/produce', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            selectedId.value = null;
            batchOrders.value = {};
        },
    });
}

function clearSelection() {
    form.reset();
    selectedId.value = null;
    batchOrders.value = {};
}

const currency = (n) => parseFloat(n ?? 0).toFixed(2);
const fmtDate = (d) => new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

function primeBatchOrders(p) {
    const next = {};
    if (p?.ingredients?.length) {
        p.ingredients.forEach((ing) => {
            const lines = [...(ing.item?.restock_batch_items ?? [])].sort((a, b) => {
                const da = new Date(a.batch?.created_at ?? a.created_at ?? 0).getTime();
                const db = new Date(b.batch?.created_at ?? b.created_at ?? 0).getTime();
                return da - db; // oldest first
            });
            next[ing.item_id] = lines.map((line, idx) => ({ id: line.id, order: idx + 1 }));
        });
    }
    batchOrders.value = next;
}

function orderFor(itemId, lineId) {
    return batchOrders.value[itemId]?.find(o => o.id === lineId)?.order ?? '';
}

function setOrder(itemId, lineId, val) {
    const list = batchOrders.value[itemId] ?? [];
    const existing = list.find(o => o.id === lineId);
    if (existing) {
        existing.order = val;
    } else {
        list.push({ id: lineId, order: val });
    }
    batchOrders.value = { ...batchOrders.value, [itemId]: list };
}

function normalisedOrders() {
    const out = {};
    Object.entries(batchOrders.value || {}).forEach(([itemId, orders]) => {
        const seq = [...orders]
            .filter(o => o.order)
            .sort((a, b) => a.order - b.order)
            .map(o => o.id);
        if (seq.length) out[itemId] = seq;
    });
    return out;
}
</script>

<template>
    <Head title="Produce" />

        <div class="flex items-center justify-between mb-5">
            <div>
                <h1 :class="ui.heading">Produce</h1>
                <p class="text-xs text-slate-500">Convert raw ingredients into finished products without exceeding ingredient stock.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1.2fr_1fr] gap-4">
            <!-- product selection and form -->
            <div :class="[ui.card, 'p-4']">
                <div class="flex items-center justify-between mb-3">
                    <p :class="ui.subheading">Production Plan</p>
                    <p class="text-xs text-slate-500">Max build: {{ maxBuild.toFixed(2) }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                    <div>
                        <label :class="ui.fieldLabel">Product</label>
                        <select v-model.number="selectedId" :class="ui.input">
                            <option :value="null">— select product —</option>
                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label :class="ui.fieldLabel">Quantity to produce (whole units)</label>
                        <input
                            type="number"
                            min="1"
                            step="1"
                            v-model.number="form.quantity"
                            :class="ui.input"
                            :max="maxBuild > 0 ? maxBuild : null"
                        />
                        <p class="text-[11px] text-slate-500 mt-1">Must be a whole number and cannot exceed max build based on ingredients.</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 mb-3" v-if="product">
                    <span class="text-xs text-slate-500">Current stock: <span class="font-semibold text-slate-900">{{ product.current_stock ?? 0 }}</span></span>
                    <span class="text-xs text-slate-500">Recipe cost: <span class="font-semibold text-slate-900">{{ currency(product.computed_cost) }}</span></span>
                    <span class="text-xs text-slate-500">Selling price: <span class="font-semibold text-slate-900">{{ currency(product.selling_price) || 'auto' }}</span></span>
                </div>

                <div class="mb-3">
                    <label :class="ui.fieldLabel">Notes (optional)</label>
                    <input type="text" v-model="form.notes" :class="ui.input" placeholder="Batch reference, operator, etc." />
                </div>

                <div class="flex gap-2">
                    <button
                        :class="ui.button.primary"
                        @click="submit"
                        :disabled="!product || form.processing || !form.quantity || form.quantity <= 0 || form.quantity > maxBuild"
                    >
                        Produce
                    </button>
                    <button @click="clearSelection" :class="ui.button.secondary">Clear</button>
                </div>

                <p v-if="form.errors.produce" class="text-xs text-rose-600 mt-2">{{ form.errors.produce }}</p>
                <p v-if="form.errors.quantity" class="text-xs text-rose-600 mt-2">{{ form.errors.quantity }}</p>
            </div>

            <!-- ingredient breakdown -->
            <div :class="[ui.card, 'p-4']">
                <p :class="[ui.subheading, 'mb-2']">Ingredient Check</p>
                <div v-if="product && product.ingredients?.length" class="border border-slate-200 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead :class="ui.tableHead">
                            <tr class="border-b border-slate-200">
                                <th class="py-2 px-3 text-left">Ingredient</th>
                                <th class="py-2 px-3 text-right">Need</th>
                                <th class="py-2 px-3 text-right">Available</th>
                                <th class="py-2 px-3 text-left">Batch plan</th>
                                <th class="py-2 px-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ing in product.ingredients" :key="ing.id" class="border-b border-slate-100 last:border-0">
                                <td class="py-2 px-3 text-slate-800">{{ ing.item?.name }}</td>
                                <td class="py-2 px-3 text-right tabular-nums text-slate-600">
                                    {{ ingredientStatus(ing).required.toFixed(4) }} {{ ingredientStatus(ing).unit }}
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums text-slate-600">
                                    {{ ingredientStatus(ing).available.toFixed(4) }} {{ ingredientStatus(ing).unit }}
                                </td>
                                <td class="py-2 px-3 text-left text-xs text-slate-600">
                                    <span v-if="(batchOrders[ing.item_id] ?? []).length">
                                        Will use:
                                        {{ (batchOrders[ing.item_id] ?? [])
                                            .slice()
                                            .sort((a,b) => a.order - b.order)
                                            .map(o => {
                                                const line = ing.item?.restock_batch_items?.find(l => l.id === o.id);
                                                return (line?.batch?.batch_code ?? ('#' + o.id)) + (line?.batch?.created_at ? ` (${fmtDate(line.batch.created_at)})` : '');
                                            })
                                            .join(', ')
                                        }}
                                    </span>
                                    <span v-else class="text-slate-400">No batches</span>
                                </td>
                                <td class="py-2 px-3 text-right">
                                    <span
                                        class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full"
                                        :class="ingredientStatus(ing).ok ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'"
                                    >
                                        {{ ingredientStatus(ing).ok ? 'OK' : 'Insufficient' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="text-xs text-slate-400 py-6 text-center border border-dashed border-slate-200 rounded-lg">
                    Select a product to see ingredient requirements.
                </div>

                <div v-if="product && product.ingredients?.length" class="mt-4 border border-slate-200 rounded-lg overflow-hidden">
                    <div class="px-3 py-2 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                        <p class="text-xs font-semibold text-slate-700">Batch consumption plan</p>
                        <p class="text-[11px] text-slate-500">Oldest batches are prioritized by default; adjust order per ingredient.</p>
                    </div>
                    <div class="divide-y divide-slate-200">
                        <div v-for="ing in product.ingredients" :key="ing.id" class="p-3 space-y-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">{{ ing.item?.name }}</p>
                                    <p class="text-[11px] text-slate-500">Item batches sorted by age</p>
                                </div>
                                <p class="text-xs text-slate-500">Need {{ ingredientStatus(ing).required.toFixed(4) }} {{ ingredientStatus(ing).unit }}</p>
                            </div>

                            <div class="overflow-auto">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-slate-100 text-slate-600 uppercase tracking-wide">
                                        <tr>
                                            <th class="py-1.5 px-2 text-left w-20">Order</th>
                                            <th class="py-1.5 px-2 text-left">Batch</th>
                                            <th class="py-1.5 px-2 text-left">Date added</th>
                                            <th class="py-1.5 px-2 text-right">Remaining</th>
                                            <th class="py-1.5 px-2 text-right">Unit Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="line in ing.item?.restock_batch_items ?? []" :key="line.id" class="border-b border-slate-100 last:border-0">
                                            <td class="py-1.5 px-2">
                                                <input
                                                    type="number"
                                                    :value="orderFor(ing.item_id, line.id)"
                                                    @input="setOrder(ing.item_id, line.id, $event.target.value ? parseInt($event.target.value) : '')"
                                                    class="w-20 border border-slate-200 rounded px-2 py-1 text-slate-800"
                                                    placeholder="#"
                                                />
                                            </td>
                                            <td class="py-1.5 px-2 font-mono text-slate-800">{{ line.batch?.batch_code ?? ('#' + line.id) }}</td>
                                            <td class="py-1.5 px-2 text-slate-600">{{ line.batch?.created_at ? fmtDate(line.batch.created_at) : (line.created_at ? fmtDate(line.created_at) : '—') }}</td>
                                            <td class="py-1.5 px-2 text-right tabular-nums text-slate-700">{{ parseFloat(line.quantity_added).toFixed(4) }}</td>
                                            <td class="py-1.5 px-2 text-right tabular-nums text-slate-600">{{ parseFloat(line.cost_per_unit).toFixed(4) }}</td>
                                        </tr>
                                        <tr v-if="!(ing.item?.restock_batch_items ?? []).length">
                                            <td colspan="5" class="py-3 text-center text-slate-400">No batches for this ingredient.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- product list -->
        <div :class="[ui.card, 'mt-4 p-4']">
            <div class="flex items-center justify-between mb-2">
                <p :class="ui.subheading">Products</p>
                <p class="text-xs text-slate-500">Select to populate the production form.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                <button
                    v-for="p in products"
                    :key="p.id"
                    @click="select(p)"
                    class="text-left transition-all rounded-xl overflow-hidden border shadow-sm"
                    :class="selectedId === p.id
                        ? 'border-slate-900 ring-2 ring-slate-200 shadow-md'
                        : 'border-slate-200 hover:border-slate-400 hover:shadow'
                    "
                >
                    <div class="aspect-video bg-slate-100 overflow-hidden">
                        <img v-if="p.image_path" :src="`/storage/${p.image_path}`" class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex items-center justify-center text-slate-300 text-3xl select-none">◆</div>
                    </div>
                    <div class="p-3">
                        <p class="text-sm font-semibold text-slate-900 truncate">{{ p.name }}</p>
                        <p class="text-xs text-slate-500">Current stock: {{ p.current_stock ?? 0 }}</p>
                        <p class="text-xs text-slate-500">Max build now: {{ (() => {
                            if (!p.ingredients?.length) return '—';
                            let min = Infinity;
                            p.ingredients.forEach(ing => {
                                const need = parseFloat(ing.quantity) || 0;
                                const avail = parseFloat(ing.item?.current_stock) || 0;
                                if (need > 0) min = Math.min(min, avail / need);
                            });
                            return Number.isFinite(min) ? min.toFixed(2) : '—';
                        })() }}</p>
                    </div>
                </button>
            </div>
        </div>
</template>
