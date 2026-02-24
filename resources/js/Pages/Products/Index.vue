<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    products: Array,
    items: Array,
});

// ── state ─────────────────────────────────────────────────────────────────
const mode     = ref(null); // null | 'add' | <product id>  (CRUD panel)
const sellMode = ref(null); // null | <product id>           (sell panel)
const imgInput = ref(null);
const imagePreview = ref(null);

// ── forms ─────────────────────────────────────────────────────────────────
const form = useForm({
    name:              '',
    image:             null,
    selling_price:     '',
    markup_percentage: 0,
    ingredients:       [],   // [{ item_id, quantity }]
});

const pricingMode = ref('auto'); // 'auto' | 'markup' | 'manual'

// Live recipe cost: sum of (ingredient qty × item cost_per_unit)
const liveComputedCost = computed(() =>
    form.ingredients.reduce((sum, ing) => {
        const item = props.items.find(i => i.id == ing.item_id);
        return sum + (parseFloat(ing.quantity) || 0) * (parseFloat(item?.cost_per_unit) || 0);
    }, 0).toFixed(2),
);

// Effective price preview while building/editing the form
const formEffectivePrice = computed(() => {
    const cost = parseFloat(liveComputedCost.value) || 0;
    if (pricingMode.value === 'manual') return parseFloat(form.selling_price) || 0;
    if (pricingMode.value === 'markup') return cost * (1 + (parseFloat(form.markup_percentage) || 0) / 100);
    return cost;
});

const sellForm = useForm({
    quantity: 1,
    notes:    '',
});

// ── CRUD helpers ──────────────────────────────────────────────────────────
function openAdd() {
    sellMode.value = null;
    mode.value = 'add';
    form.reset();
    form.ingredients = [];
    form.markup_percentage = 0;
    pricingMode.value = 'auto';
    imagePreview.value = null;
}

function openEdit(product) {
    sellMode.value = null;
    mode.value = product.id;
    form.name  = product.name;
    form.image = null;
    form.ingredients = product.ingredients.map(i => ({
        item_id:  i.item_id,
        quantity: i.quantity,
    }));
    imagePreview.value = product.image_path ? `/storage/${product.image_path}` : null;

    if (parseFloat(product.selling_price) > 0) {
        pricingMode.value      = 'manual';
        form.selling_price     = product.selling_price;
        form.markup_percentage = 0;
    } else if (parseFloat(product.markup_percentage) > 0) {
        pricingMode.value      = 'markup';
        form.selling_price     = '';
        form.markup_percentage = product.markup_percentage;
    } else {
        pricingMode.value      = 'auto';
        form.selling_price     = '';
        form.markup_percentage = 0;
    }
}

function closeForm() {
    mode.value = null;
    form.reset();
    form.ingredients = [];
    pricingMode.value = 'auto';
    imagePreview.value = null;
}

function handleImage(e) {
    const file = e.target.files[0];
    if (!file) return;
    form.image = file;
    imagePreview.value = URL.createObjectURL(file);
}

// ── recipe builder ────────────────────────────────────────────────────────
function addIngredient() {
    form.ingredients.push({ item_id: '', quantity: '' });
}

function removeIngredient(idx) {
    form.ingredients.splice(idx, 1);
}

function submitProduct() {
    if (pricingMode.value === 'auto') {
        form.selling_price     = 0;
        form.markup_percentage = 0;
    } else if (pricingMode.value === 'markup') {
        form.selling_price = 0;
    } else {
        form.markup_percentage = 0;
    }
    if (mode.value === 'add') {
        form.post('/products', { onSuccess: closeForm });
    } else {
        form.put(`/products/${mode.value}`, { onSuccess: closeForm });
    }
}

function destroyProduct(product) {
    if (confirm(`Delete "${product.name}"?\nThis will also delete its sales history.`)) {
        router.delete(`/products/${product.id}`);
    }
}

// ── sell / checkout ───────────────────────────────────────────────────────
const activeSellProduct = computed(() =>
    props.products.find(p => p.id === sellMode.value) ?? null,
);

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
    if (!activeSellProduct.value || !sellForm.quantity) return false;
    return activeSellProduct.value.ingredients.every(
        ing => ingredientStatus(ing).ok,
    );
});

function openSell(product) {
    mode.value = null;
    sellMode.value = product.id;
    sellForm.reset();
    sellForm.quantity = 1;
}

function closeSell() {
    sellMode.value = null;
    sellForm.reset();
}

function submitSell() {
    sellForm.post(`/products/${sellMode.value}/checkout`, { onSuccess: closeSell });
}

// ── style helpers ─────────────────────────────────────────────────────────
const inputCls = 'w-full border border-gray-200 focus:border-gray-500 focus:outline-none px-2.5 py-1.5 text-sm bg-white';
const btnBlack = 'bg-black text-white text-xs px-4 py-1.5 hover:bg-gray-800 disabled:opacity-40 transition-colors';
const btnGreen = 'bg-emerald-600 text-white text-xs px-4 py-1.5 hover:bg-emerald-700 disabled:opacity-40 transition-colors';
const btnGhost = 'border border-gray-200 text-gray-500 text-xs px-4 py-1.5 hover:border-gray-400 transition-colors';
const modeBtnCls = (m) => [
    'text-xs px-3 py-1.5 transition-colors',
    pricingMode.value === m ? 'bg-black text-white' : 'text-gray-500 hover:text-black',
].join(' ');
</script>

<template>
    <AppLayout>
        <Head title="Products" />

        <!-- ── page header ──────────────────────────────────────── -->
        <div class="flex items-center justify-between mb-5">
            <h1 class="text-sm font-semibold">Products</h1>
            <button v-if="!mode" @click="openAdd" :class="btnBlack">+ New Product</button>
        </div>

        <!-- ── add / edit panel ─────────────────────────────────── -->
        <div v-if="mode" class="bg-white border border-gray-200 p-4 mb-5">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-3">
                {{ mode === 'add' ? 'New Product' : 'Edit Product' }}
            </p>

            <!-- name + image row -->
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <!-- image picker -->
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Image</label>
                    <div
                        class="h-10 w-10 border border-gray-200 flex items-center justify-center cursor-pointer overflow-hidden hover:border-gray-400 transition-colors"
                        @click="imgInput.click()"
                        title="Click to pick image"
                    >
                        <img v-if="imagePreview" :src="imagePreview" class="h-full w-full object-cover" />
                        <span v-else class="text-gray-300 text-xl select-none">+</span>
                    </div>
                    <input ref="imgInput" type="file" accept="image/*" @change="handleImage" class="hidden" />
                </div>

                <!-- name -->
                <div class="flex-1 min-w-48">
                    <label class="block text-xs text-gray-400 mb-1">Name <span class="text-red-400">*</span></label>
                    <input type="text" v-model="form.name" :class="inputCls" placeholder="Product name" />
                    <p v-if="form.errors.name" class="text-xs text-red-500 mt-0.5">{{ form.errors.name }}</p>
                </div>
            </div>

            <!-- pricing -->
            <div class="mb-4">
                <label class="block text-xs text-gray-400 mb-1">Pricing</label>
                <div class="inline-flex border border-gray-200 divide-x divide-gray-200 w-fit mb-3">
                    <button type="button" @click="pricingMode = 'auto'" :class="modeBtnCls('auto')">Auto</button>
                    <button type="button" @click="pricingMode = 'markup'" :class="modeBtnCls('markup')">Markup %</button>
                    <button type="button" @click="pricingMode = 'manual'" :class="modeBtnCls('manual')">Manual</button>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Recipe Cost</label>
                        <div class="px-2.5 py-1.5 border border-gray-100 bg-gray-50 text-sm tabular-nums w-36">
                            {{ liveComputedCost }}
                        </div>
                        <p class="text-xs text-gray-300 mt-0.5">auto-calculated</p>
                    </div>

                    <div v-if="pricingMode === 'markup'" class="w-28">
                        <label class="block text-xs text-gray-400 mb-1">Markup %</label>
                        <div class="relative">
                            <input
                                type="number"
                                v-model="form.markup_percentage"
                                min="0"
                                step="0.1"
                                :class="inputCls"
                                placeholder="0"
                            />
                            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 pointer-events-none">%</span>
                        </div>
                        <p v-if="form.errors.markup_percentage" class="text-xs text-red-500 mt-0.5">{{ form.errors.markup_percentage }}</p>
                    </div>

                    <div v-if="pricingMode === 'manual'" class="w-36">
                        <label class="block text-xs text-gray-400 mb-1">Selling Price</label>
                        <input
                            type="number"
                            v-model="form.selling_price"
                            min="0"
                            step="0.01"
                            :class="inputCls"
                            placeholder="0.00"
                        />
                        <p v-if="form.errors.selling_price" class="text-xs text-red-500 mt-0.5">{{ form.errors.selling_price }}</p>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Effective Price</label>
                        <div class="px-2.5 py-1.5 border border-gray-100 bg-gray-50 text-sm tabular-nums w-36 font-medium">
                            {{ formEffectivePrice.toFixed(2) }}
                        </div>
                        <p class="text-xs text-gray-300 mt-0.5">
                            <span v-if="pricingMode === 'auto'">= recipe cost</span>
                            <span v-else-if="pricingMode === 'markup'">cost × (1 + {{ form.markup_percentage || 0 }}%)</span>
                            <span v-else>fixed price</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- recipe builder -->
            <div class="mb-3">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Recipe</p>
                    <button type="button" @click="addIngredient" class="text-xs text-gray-400 hover:text-black transition-colors">
                        + Add ingredient
                    </button>
                </div>

                <div v-if="!form.ingredients.length" class="text-xs text-gray-300 py-3 text-center border border-dashed border-gray-200">
                    No ingredients yet — click "Add ingredient" above.
                </div>

                <div
                    v-for="(ing, idx) in form.ingredients"
                    :key="idx"
                    class="flex items-end gap-2 mb-2"
                >
                    <!-- item select -->
                    <div class="flex-1 min-w-40">
                        <label v-if="idx === 0" class="block text-xs text-gray-400 mb-1">Stock Item</label>
                        <select v-model="ing.item_id" :class="inputCls">
                            <option value="">— select item —</option>
                            <option v-for="item in items" :key="item.id" :value="item.id">
                                {{ item.name }}
                                <template v-if="item.unit"> ({{ item.unit.abbreviation }})</template>
                            </option>
                        </select>
                        <p
                            v-if="form.errors[`ingredients.${idx}.item_id`]"
                            class="text-xs text-red-500 mt-0.5"
                        >{{ form.errors[`ingredients.${idx}.item_id`] }}</p>
                    </div>

                    <!-- quantity -->
                    <div class="w-28">
                        <label v-if="idx === 0" class="block text-xs text-gray-400 mb-1">Qty per unit</label>
                        <input
                            type="number"
                            v-model="ing.quantity"
                            min="0.0001"
                            step="0.0001"
                            :class="inputCls"
                            placeholder="0"
                        />
                        <p
                            v-if="form.errors[`ingredients.${idx}.quantity`]"
                            class="text-xs text-red-500 mt-0.5"
                        >{{ form.errors[`ingredients.${idx}.quantity`] }}</p>
                    </div>

                    <!-- unit label -->
                    <div class="w-10 pb-px text-xs text-gray-400 text-center">
                        {{ items.find(i => i.id == ing.item_id)?.unit?.abbreviation ?? '' }}
                    </div>

                    <!-- remove -->
                    <button
                        type="button"
                        @click="removeIngredient(idx)"
                        class="pb-px text-xs text-gray-300 hover:text-red-500 transition-colors"
                    >✕</button>
                </div>
            </div>

            <!-- actions -->
            <div class="flex gap-2 pt-1">
                <button @click="submitProduct" :disabled="form.processing" :class="btnBlack">
                    {{ mode === 'add' ? 'Create' : 'Save' }}
                </button>
                <button @click="closeForm" :class="btnGhost">Cancel</button>
            </div>
        </div>

        <!-- ── sell panel ────────────────────────────────────────── -->
        <div v-if="sellMode && activeSellProduct" class="bg-white border border-gray-200 p-4 mb-5">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-3">
                Sell — <span class="text-black">{{ activeSellProduct.name }}</span>
            </p>

            <!-- quantity + notes -->
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <div class="w-28">
                    <label class="block text-xs text-gray-400 mb-1">Qty to sell <span class="text-red-400">*</span></label>
                    <input
                        type="number"
                        v-model="sellForm.quantity"
                        min="0.01"
                        step="0.01"
                        :class="inputCls"
                        placeholder="1"
                    />
                    <p v-if="sellForm.errors.quantity" class="text-xs text-red-500 mt-0.5">{{ sellForm.errors.quantity }}</p>
                </div>
                <div class="flex-1 min-w-48">
                    <label class="block text-xs text-gray-400 mb-1">Notes</label>
                    <input type="text" v-model="sellForm.notes" :class="inputCls" placeholder="Optional" />
                </div>
            </div>

            <!-- ingredient stock check -->
            <div v-if="activeSellProduct.ingredients.length" class="mb-4">
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-2">Stock check</p>
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left text-xs text-gray-300 font-normal pb-1">Ingredient</th>
                            <th class="text-right text-xs text-gray-300 font-normal pb-1">Required</th>
                            <th class="text-right text-xs text-gray-300 font-normal pb-1">Available</th>
                            <th class="w-6 pb-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="ing in activeSellProduct.ingredients"
                            :key="ing.id"
                            class="border-t border-gray-100"
                        >
                            <td class="py-1.5 text-sm">{{ ing.item.name }}</td>
                            <td class="py-1.5 text-sm text-right tabular-nums">
                                {{ ingredientStatus(ing).required }} {{ ingredientStatus(ing).unit }}
                            </td>
                            <td
                                class="py-1.5 text-sm text-right tabular-nums"
                                :class="ingredientStatus(ing).ok ? 'text-gray-500' : 'text-red-500'"
                            >
                                {{ ingredientStatus(ing).available }} {{ ingredientStatus(ing).unit }}
                            </td>
                            <td class="py-1.5 text-center text-xs">
                                <span v-if="ingredientStatus(ing).ok" class="text-emerald-500">✓</span>
                                <span v-else class="text-red-500">✗</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="mb-4 text-xs text-gray-300">This product has no recipe — no stock will be deducted.</div>

            <!-- server-side stock error -->
            <p v-if="sellForm.errors.checkout" class="text-xs text-red-500 mb-3">{{ sellForm.errors.checkout }}</p>

            <!-- actions -->
            <div class="flex gap-2">
                <button
                    @click="submitSell"
                    :disabled="sellForm.processing || !canSell"
                    :class="btnGreen"
                    :title="!canSell ? 'Insufficient stock for one or more ingredients' : ''"
                >
                    Confirm Sale
                </button>
                <button @click="closeSell" :class="btnGhost">Cancel</button>
            </div>
        </div>

        <!-- ── products table ────────────────────────────────────── -->
        <div class="bg-white border border-gray-200">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="w-12 py-2 px-3"></th>
                        <th class="py-2 px-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Name</th>
                        <th class="py-2 px-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Recipe</th>
                        <th class="py-2 px-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wide">Cost</th>
                        <th class="py-2 px-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wide">Price</th>
                        <th class="w-36 py-2 px-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="product in products"
                        :key="product.id"
                        class="border-b border-gray-100 last:border-0 hover:bg-gray-50 transition-colors"
                        :class="{ 'bg-gray-50': mode === product.id || sellMode === product.id }"
                    >
                        <!-- thumbnail -->
                        <td class="py-2 px-3">
                            <img
                                v-if="product.image_path"
                                :src="`/storage/${product.image_path}`"
                                class="h-9 w-9 object-cover"
                            />
                            <div v-else class="h-9 w-9 bg-gray-100"></div>
                        </td>

                        <td class="py-2 px-3 text-sm">{{ product.name }}</td>

                        <!-- recipe summary -->
                        <td class="py-2 px-3">
                            <span v-if="!product.ingredients.length" class="text-xs text-gray-200">no recipe</span>
                            <div v-else class="flex flex-wrap gap-1">
                                <span
                                    v-for="ing in product.ingredients"
                                    :key="ing.id"
                                    class="inline-flex items-center gap-1 text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5"
                                >
                                    {{ ing.quantity }}<span v-if="ing.item.unit" class="text-gray-300">{{ ing.item.unit.abbreviation }}</span>
                                    {{ ing.item.name }}
                                </span>
                            </div>
                        </td>

                        <td class="py-2 px-3 text-sm text-right tabular-nums text-gray-400">
                            <span v-if="product.computed_cost > 0">{{ parseFloat(product.computed_cost).toFixed(2) }}</span>
                            <span v-else class="text-gray-200">—</span>
                        </td>

                        <td class="py-2 px-3 text-sm text-right tabular-nums font-medium">
                            <span v-if="product.selling_price > 0">{{ parseFloat(product.selling_price).toFixed(2) }}</span>
                            <span v-else-if="product.markup_percentage > 0">
                                {{ (parseFloat(product.computed_cost) * (1 + parseFloat(product.markup_percentage) / 100)).toFixed(2) }}
                                <span class="text-xs text-gray-400 font-normal ml-0.5">+{{ product.markup_percentage }}%</span>
                            </span>
                            <span v-else class="text-gray-300 text-xs">auto</span>
                        </td>

                        <td class="py-2 px-3 text-right whitespace-nowrap">
                            <button
                                @click="openSell(product)"
                                class="text-xs text-emerald-600 hover:text-emerald-800 transition-colors mr-3"
                            >Sell</button>
                            <button
                                @click="openEdit(product)"
                                class="text-xs text-gray-400 hover:text-black transition-colors mr-3"
                            >Edit</button>
                            <button
                                @click="destroyProduct(product)"
                                class="text-xs text-gray-400 hover:text-red-600 transition-colors"
                            >Delete</button>
                        </td>
                    </tr>

                    <tr v-if="!products.length">
                        <td colspan="4" class="py-14 text-center text-xs text-gray-300">
                            No products yet.
                            <button @click="openAdd" class="underline hover:text-gray-500">Create one</button>
                            to get started.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
