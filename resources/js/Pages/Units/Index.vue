<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ units: Array });

const form = useForm({ name: '', abbreviation: '' });

function submit() {
    form.post('/units', { onSuccess: () => form.reset() });
}

function remove(unit) {
    if (confirm(`Delete "${unit.name}"?\n\nItems using this unit will have their unit cleared.`)) {
        router.delete(`/units/${unit.id}`);
    }
}

const inputClass =
    'w-full border border-gray-200 focus:border-gray-500 focus:outline-none px-2.5 py-1.5 text-sm bg-white';
const btnPrimary =
    'bg-black text-white text-xs px-4 py-1.5 hover:bg-gray-800 disabled:opacity-40 transition-colors';
</script>

<template>
    <AppLayout>
        <Head title="Units" />

        <!-- Page header -->
        <div class="flex items-center justify-between mb-5">
            <h1 class="text-sm font-semibold">Units</h1>
        </div>

        <!-- Add form -->
        <div class="bg-white border border-gray-200 p-4 mb-5">
            <p class="text-xs text-gray-400 mb-3 uppercase tracking-wide font-medium">New Unit</p>
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-48">
                    <label class="block text-xs text-gray-400 mb-1">Name <span class="text-red-400">*</span></label>
                    <input
                        type="text"
                        v-model="form.name"
                        :class="inputClass"
                        placeholder="Kilogram"
                        @keydown.enter="submit"
                    />
                    <p v-if="form.errors.name" class="text-xs text-red-500 mt-0.5">{{ form.errors.name }}</p>
                </div>
                <div class="w-28">
                    <label class="block text-xs text-gray-400 mb-1">Abbreviation <span class="text-red-400">*</span></label>
                    <input
                        type="text"
                        v-model="form.abbreviation"
                        :class="inputClass"
                        placeholder="kg"
                        @keydown.enter="submit"
                    />
                    <p v-if="form.errors.abbreviation" class="text-xs text-red-500 mt-0.5">{{ form.errors.abbreviation }}</p>
                </div>
                <div class="pb-px">
                    <button @click="submit" :disabled="form.processing" :class="btnPrimary">
                        Add Unit
                    </button>
                </div>
            </div>
        </div>

        <!-- Units table -->
        <div class="bg-white border border-gray-200">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="py-2 px-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Name</th>
                        <th class="py-2 px-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Abbreviation</th>
                        <th class="py-2 px-4 text-right text-xs font-medium text-gray-400 uppercase tracking-wide">Items</th>
                        <th class="w-20 py-2 px-4"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="unit in units"
                        :key="unit.id"
                        class="border-b border-gray-100 last:border-0 hover:bg-gray-50 transition-colors"
                    >
                        <td class="py-2.5 px-4 text-sm">{{ unit.name }}</td>
                        <td class="py-2.5 px-4 text-sm text-gray-400">{{ unit.abbreviation }}</td>
                        <td class="py-2.5 px-4 text-sm text-right tabular-nums text-gray-400">{{ unit.items_count }}</td>
                        <td class="py-2.5 px-4 text-right">
                            <button
                                @click="remove(unit)"
                                class="text-xs text-gray-400 hover:text-red-600 transition-colors"
                                :disabled="unit.items_count > 0"
                                :title="unit.items_count > 0 ? 'Unlink items first' : ''"
                                :class="{ 'opacity-30 cursor-not-allowed': unit.items_count > 0 }"
                            >Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!units.length">
                        <td colspan="4" class="py-14 text-center text-xs text-gray-300">
                            No units yet. Use the form above to add one.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
