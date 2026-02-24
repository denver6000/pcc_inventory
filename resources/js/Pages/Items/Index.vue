<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    items: Array,
    units: Array,
});

// ── form state ──────────────────────────────────────────
// null = hidden, 'add' = new item, number = editing item id
const mode = ref(null);
const imagePreview = ref(null);
const imgInput = ref(null);

const form = useForm({
    name: '',
    image: null,
    unit_id: '',
    default_stock: '',
    current_stock: '',
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
    form.default_stock = item.default_stock;
    form.current_stock = item.current_stock;
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
    if (mode.value === 'add') {
        form.post('/items', { onSuccess: closeForm });
    } else {
        form.put(`/items/${mode.value}`, { onSuccess: closeForm });
    }
}

function remove(item) {
    if (confirm(`Delete "${item.name}"?`)) {
        router.delete(`/items/${item.id}`);
    }
}

// shared input / button class helpers
const inputClass =
    'w-full border border-gray-200 focus:border-gray-500 focus:outline-none px-2.5 py-1.5 text-sm bg-white';
const btnPrimary =
    'bg-black text-white text-xs px-4 py-1.5 hover:bg-gray-800 disabled:opacity-40 transition-colors';
const btnSecondary =
    'border border-gray-200 text-gray-500 text-xs px-4 py-1.5 hover:border-gray-400 transition-colors';
</script>

<template>
    <AppLayout>
        <Head title="Items" />

        <!-- ── Page header ───────────────────────────── -->
        <div class="flex items-center justify-between mb-5">
            <h1 class="text-sm font-semibold">Items</h1>
            <button v-if="!mode" @click="openAdd" :class="btnPrimary">
                + New Item
            </button>
        </div>

        <!-- ── Add / Edit form panel ─────────────────── -->
        <div v-if="mode" class="bg-white border border-gray-200 p-4 mb-5">
            <p class="text-xs text-gray-400 mb-3 uppercase tracking-wide font-medium">
                {{ mode === 'add' ? 'New Item' : 'Edit Item' }}
            </p>

            <div class="flex flex-wrap items-end gap-3">

                <!-- Image picker -->
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Image</label>
                    <div
                        class="h-10 w-10 border border-gray-200 flex items-center justify-center cursor-pointer overflow-hidden hover:border-gray-400 transition-colors"
                        @click="imgInput.click()"
                        title="Click to pick image"
                    >
                        <img v-if="imagePreview" :src="imagePreview" class="h-full w-full object-cover" />
                        <span v-else class="text-gray-300 text-xl leading-none select-none">+</span>
                    </div>
                    <input ref="imgInput" type="file" accept="image/*" @change="handleImage" class="hidden" />
                </div>

                <!-- Name -->
                <div class="flex-1 min-w-40">
                    <label class="block text-xs text-gray-400 mb-1">Name <span class="text-red-400">*</span></label>
                    <input type="text" v-model="form.name" :class="inputClass" placeholder="Item name" />
                    <p v-if="form.errors.name" class="text-xs text-red-500 mt-0.5">{{ form.errors.name }}</p>
                </div>

                <!-- Unit -->
                <div class="w-40">
                    <label class="block text-xs text-gray-400 mb-1">Unit</label>
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

                <!-- Default Stock -->
                <div class="w-28">
                    <label class="block text-xs text-gray-400 mb-1">Default Stock <span class="text-red-400">*</span></label>
                    <input type="number" v-model="form.default_stock" min="0" step="0.01" :class="inputClass" placeholder="0" />
                    <p v-if="form.errors.default_stock" class="text-xs text-red-500 mt-0.5">{{ form.errors.default_stock }}</p>
                </div>

                <!-- Current Stock (edit only) -->
                <div v-if="mode !== 'add'" class="w-28">
                    <label class="block text-xs text-gray-400 mb-1">Current Stock</label>
                    <input type="number" v-model="form.current_stock" min="0" step="0.01" :class="inputClass" placeholder="0" />
                </div>

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
        <div class="bg-white border border-gray-200">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="w-12 py-2 px-3"></th>
                        <th class="py-2 px-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Name</th>
                        <th class="py-2 px-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Unit</th>
                        <th class="py-2 px-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wide">Default</th>
                        <th class="py-2 px-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wide">Stock</th>
                        <th class="w-24 py-2 px-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        class="border-b border-gray-100 last:border-0 hover:bg-gray-50 transition-colors"
                        :class="{ 'bg-gray-50': mode === item.id }"
                    >
                        <!-- Thumbnail -->
                        <td class="py-2 px-3">
                            <img
                                v-if="item.image_path"
                                :src="`/storage/${item.image_path}`"
                                class="h-9 w-9 object-cover"
                            />
                            <div v-else class="h-9 w-9 bg-gray-100"></div>
                        </td>

                        <td class="py-2 px-3 text-sm">{{ item.name }}</td>

                        <td class="py-2 px-3 text-sm text-gray-400">
                            <span v-if="item.unit">{{ item.unit.abbreviation }}</span>
                            <span v-else class="text-gray-200">—</span>
                        </td>

                        <td class="py-2 px-3 text-sm text-right tabular-nums">{{ item.default_stock }}</td>
                        <td class="py-2 px-3 text-sm text-right tabular-nums">{{ item.current_stock }}</td>

                        <td class="py-2 px-3 text-right whitespace-nowrap">
                            <button
                                @click="openEdit(item)"
                                class="text-xs text-gray-400 hover:text-black transition-colors mr-3"
                            >Edit</button>
                            <button
                                @click="remove(item)"
                                class="text-xs text-gray-400 hover:text-red-600 transition-colors"
                            >Delete</button>
                        </td>
                    </tr>

                    <tr v-if="!items.length">
                        <td colspan="6" class="py-14 text-center text-xs text-gray-300">
                            No items yet. Click <button @click="openAdd" class="underline hover:text-gray-500">New Item</button> to get started.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
