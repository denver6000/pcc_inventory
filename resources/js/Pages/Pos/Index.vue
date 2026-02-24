<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

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

const inputCls  = 'w-full border border-gray-200 focus:border-gray-500 focus:outline-none px-2.5 py-1.5 text-sm bg-white';
const btnGreen  = 'bg-emerald-600 text-white text-xs px-4 py-1.5 hover:bg-emerald-700 disabled:opacity-40 transition-colors';
const btnGhost  = 'border border-gray-200 text-gray-500 text-xs px-4 py-1.5 hover:border-gray-400 transition-colors';
</script>

<template>
    <AppLayout>
        <Head title="POS" />

        <div class="flex items-center justify-between mb-5">
            <h1 class="text-sm font-semibold">Point of Sale</h1>
            <p class="text-xs text-gray-400">Click a product card to sell it</p>
        </div>

        <!-- ── sell panel ─────────────────────────────────────── -->
        <div v-if="selectedId && product" class="bg-white border border-gray-200 p-4 mb-5">
            <div class="flex flex-wrap items-end gap-4">

                <!-- product info -->
                <div class="flex items-center gap-3 min-w-48">
                    <img
                        v-if="product.image_path"
                        :src="`/storage/${product.image_path}`"
                        class="h-10 w-10 object-cover flex-shrink-0"
                    />
                    <div v-else class="h-10 w-10 bg-gray-100 flex-shrink-0"></div>
                    <div>
                        <p class="text-sm font-medium">{{ product.name }}</p>
                        <p class="text-xs text-gray-400">
                            Unit price:
                            <span class="tabular-nums text-gray-700 font-medium">${{ currency(effectivePrice) }}</span>
                            <span v-if="isPriceAuto" class="text-gray-300 ml-1">(recipe cost)</span>
                            <span v-else-if="isPriceMarkup" class="text-gray-300 ml-1">(+{{ product.markup_percentage }}% markup)</span>
                        </p>
                    </div>
                </div>

                <!-- qty -->
                <div class="w-24">
                    <label class="block text-xs text-gray-400 mb-1">Qty <span class="text-red-400">*</span></label>
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
                    <label class="block text-xs text-gray-400 mb-1">Total</label>
                    <div class="px-2.5 py-1.5 border border-gray-100 bg-gray-50 text-sm font-semibold tabular-nums w-28">
                        ${{ total }}
                    </div>
                </div>

                <!-- notes -->
                <div class="flex-1 min-w-36">
                    <label class="block text-xs text-gray-400 mb-1">Notes</label>
                    <input type="text" v-model="sellForm.notes" :class="inputCls" placeholder="Optional" />
                </div>

                <!-- actions -->
                <div class="flex gap-2 pb-px">
                    <button
                        @click="submit"
                        :disabled="sellForm.processing || !canSell"
                        :class="btnGreen"
                        :title="!canSell ? 'Insufficient stock for one or more ingredients' : ''"
                    >
                        Confirm Sale
                    </button>
                    <button @click="cancel" :class="btnGhost">Cancel</button>
                </div>
            </div>

            <!-- ingredient stock pills -->
            <div v-if="product.ingredients.length" class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap gap-2">
                <div
                    v-for="ing in product.ingredients"
                    :key="ing.id"
                    class="flex items-center gap-1 text-xs px-2 py-0.5"
                    :class="ingredientStatus(ing).ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'"
                >
                    <span>{{ ingredientStatus(ing).ok ? '✓' : '✗' }}</span>
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
                class="text-left border transition-all"
                :class="selectedId === p.id
                    ? 'border-black ring-1 ring-black'
                    : 'border-gray-200 hover:border-gray-400'"
            >
                <!-- image / placeholder -->
                <div class="aspect-square bg-gray-100 overflow-hidden">
                    <img
                        v-if="p.image_path"
                        :src="`/storage/${p.image_path}`"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center text-gray-200 text-4xl select-none">
                        ▪
                    </div>
                </div>

                <!-- name + price -->
                <div class="p-2.5">
                    <p class="text-xs font-medium truncate leading-tight">{{ p.name }}</p>
                    <p class="text-xs tabular-nums mt-0.5 text-gray-600">
                        <template v-if="parseFloat(p.selling_price) > 0">
                            ${{ currency(p.selling_price) }}
                        </template>
                        <template v-else-if="parseFloat(p.markup_percentage ?? 0) > 0">
                            ${{ currency(parseFloat(p.computed_cost) * (1 + parseFloat(p.markup_percentage) / 100)) }}
                            <span class="text-gray-300 text-xs"> +{{ p.markup_percentage }}%</span>
                        </template>
                        <template v-else>
                            ${{ currency(p.computed_cost) }}
                            <span class="text-gray-300 text-xs"> auto</span>
                        </template>
                    </p>
                </div>
            </button>
        </div>

        <div v-else class="py-20 text-center text-xs text-gray-300">
            No products yet.
            <a href="/products" class="underline hover:text-gray-500">Create products</a>
            to use the POS.
        </div>
    </AppLayout>
</template>
