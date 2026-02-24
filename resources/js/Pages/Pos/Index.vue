<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ui } from '@/theme';

const props = defineProps({
    products: Array,
});

const selectedId = ref(null);

const sellForm = useForm({
    quantity: 1,
    notes: '',
});

const product = computed(() =>
    props.products.find(p => p.id === selectedId.value) ?? null,
);

const effectivePrice = computed(() => {
    if (!product.value) return 0;
    const sp     = parseFloat(product.value.selling_price);
    if (sp > 0) return sp;
    const markup = parseFloat(product.value.markup_percentage ?? 0);
    const cost   = parseFloat(product.value.computed_cost ?? 0);
    if (markup > 0) return cost * (1 + markup / 100);
    return cost;
});

const isPriceAuto = computed(() =>
    product.value
    && !(parseFloat(product.value.selling_price) > 0)
    && !(parseFloat(product.value.markup_percentage ?? 0) > 0),
);

const isPriceMarkup = computed(() =>
    product.value
    && !(parseFloat(product.value.selling_price) > 0)
    && parseFloat(product.value.markup_percentage ?? 0) > 0,
);

const total = computed(() => {
    const qty = parseFloat(sellForm.quantity) || 0;
    return (effectivePrice.value * qty).toFixed(2);
});

function ingredientStatus(ing) {
    const qty      = parseFloat(sellForm.quantity) || 0;
    const required = parseFloat((ing.quantity * qty).toFixed(4));
    const available = parseFloat(ing.item.current_stock);
    return {
        required,
        available,
        unit: ing.item.unit?.abbreviation ?? '',
        ok:   available >= required,
    };
}

const canSell = computed(() => {
    if (!product.value || !(parseFloat(sellForm.quantity) > 0)) return false;
    return product.value.ingredients.every(ing => ingredientStatus(ing).ok);
});

function select(p) {
    if (selectedId.value === p.id) {
        selectedId.value = null;
        sellForm.reset();
        sellForm.quantity = 1;
        return;
    }
    selectedId.value = p.id;
    sellForm.reset();
    sellForm.quantity = 1;
}

function cancel() {
    selectedId.value = null;
    sellForm.reset();
}

function submit() {
    sellForm.post(`/products/${selectedId.value}/checkout`, { onSuccess: cancel });
}

const currency = (n) => parseFloat(n ?? 0).toFixed(2);

const inputCls  = ui.input;
const btnPrimary  = ui.button.primary;
const btnGhost  = ui.button.secondary;
</script>

<template>
    <AppLayout>
        <Head title="POS" />

        <div class="flex items-center justify-between mb-5">
            <div>
                <h1 :class="ui.heading">Point of Sale</h1>
                <p class="text-xs text-slate-500">Click a product to stage a sale; stock guards show per-ingredient availability.</p>
            </div>
            <p class="text-xs text-slate-500">Live recipe-based pricing</p>
        </div>

        <!-- ── sell panel ─────────────────────────────────────── -->
        <div v-if="selectedId && product" :class="[ui.card, 'p-5 mb-5']">
            <div class="flex flex-wrap items-end gap-4">

                <!-- product info -->
                <div class="flex items-center gap-3 min-w-48">
                    <img
                        v-if="product.image_path"
                        :src="`/storage/${product.image_path}`"
                        class="h-12 w-12 object-cover flex-shrink-0 rounded-lg border border-slate-200"
                    />
                    <div v-else class="h-12 w-12 bg-slate-100 rounded-lg flex items-center justify-center text-slate-400 text-lg flex-shrink-0">◆</div>
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ product.name }}</p>
                        <p class="text-xs text-slate-500">
                            Unit price:
                            <span class="tabular-nums text-slate-900 font-semibold">${{ currency(effectivePrice) }}</span>
                            <span v-if="isPriceAuto" class="text-slate-400 ml-1">(recipe cost)</span>
                            <span v-else-if="isPriceMarkup" class="text-slate-400 ml-1">(+{{ product.markup_percentage }}% markup)</span>
                        </p>
                    </div>
                </div>

                <!-- qty -->
                <div class="w-24">
                    <label :class="ui.fieldLabel">Qty <span class="text-red-400">*</span></label>
                    <input
                        type="number"
                        v-model="sellForm.quantity"
                        min="0.01"
                        step="1"
                        :class="inputCls"
                    />
                    <p v-if="sellForm.errors.quantity" class="text-xs text-red-500 mt-0.5">{{ sellForm.errors.quantity }}</p>
                </div>

                <!-- total -->
                <div>
                    <label :class="ui.fieldLabel">Total</label>
                    <div class="px-3 py-2 border border-slate-200 bg-slate-50 text-sm font-semibold tabular-nums w-28 rounded-lg">
                        ${{ total }}
                    </div>
                </div>

                <!-- notes -->
                <div class="flex-1 min-w-36">
                    <label :class="ui.fieldLabel">Notes</label>
                    <input type="text" v-model="sellForm.notes" :class="inputCls" placeholder="Optional" />
                </div>

                <!-- actions -->
                <div class="flex gap-2 pb-px">
                    <button
                        @click="submit"
                        :disabled="sellForm.processing || !canSell"
                        :class="btnPrimary"
                        :title="!canSell ? 'Insufficient stock for one or more ingredients' : ''"
                    >
                        Confirm Sale
                    </button>
                    <button @click="cancel" :class="btnGhost">Cancel</button>
                </div>
            </div>

            <!-- ingredient stock pills -->
            <div v-if="product.ingredients.length" class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap gap-2">
                <div
                    v-for="ing in product.ingredients"
                    :key="ing.id"
                    class="flex items-center gap-1 text-xs px-3 py-1 rounded-full border"
                    :class="ingredientStatus(ing).ok ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
                >
                    <span>{{ ingredientStatus(ing).ok ? '✓' : '!' }}</span>
                    {{ ing.item.name }}
                    <span class="opacity-50">
                        {{ ingredientStatus(ing).required }}/{{ ingredientStatus(ing).available }}{{ ingredientStatus(ing).unit }}
                    </span>
                </div>
            </div>

            <p v-if="sellForm.errors.checkout" class="text-xs text-red-500 mt-2">
                {{ sellForm.errors.checkout }}
            </p>
        </div>

        <!-- ── product grid ────────────────────────────────────── -->
        <div
            v-if="products.length"
            class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3"
        >
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
                <!-- image / placeholder -->
                <div class="aspect-square bg-slate-100 overflow-hidden">
                    <img
                        v-if="p.image_path"
                        :src="`/storage/${p.image_path}`"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center text-slate-300 text-4xl select-none">
                        ◆
                    </div>
                </div>

                <!-- name + price -->
                <div class="p-2.5">
                    <p class="text-sm font-semibold truncate leading-tight text-slate-900">{{ p.name }}</p>
                    <p class="text-xs tabular-nums mt-0.5 text-slate-600">
                        <template v-if="parseFloat(p.selling_price) > 0">
                            ${{ currency(p.selling_price) }}
                        </template>
                        <template v-else-if="parseFloat(p.markup_percentage ?? 0) > 0">
                            ${{ currency(parseFloat(p.computed_cost) * (1 + parseFloat(p.markup_percentage) / 100)) }}
                            <span class="text-slate-400 text-xs"> +{{ p.markup_percentage }}%</span>
                        </template>
                        <template v-else>
                            ${{ currency(p.computed_cost) }}
                            <span class="text-slate-400 text-xs"> auto</span>
                        </template>
                    </p>
                </div>
            </button>
        </div>

        <div v-else class="py-20 text-center text-xs text-slate-400">
            No products yet.
            <a href="/products" class="underline hover:text-slate-700">Create products</a>
            to use the POS.
        </div>
    </AppLayout>
</template>
