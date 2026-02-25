<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ui } from '@/theme';

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

const inputClass = ui.input;
const btnPrimary = ui.button.primary;
</script>

<template>
    <Head title="Units" />

        <!-- Page header -->
        <div class="flex items-center justify-between mb-5">
            <div>
                <h1 :class="ui.heading">Units</h1>
                <p class="text-xs text-slate-500">Create abbreviations used across items.</p>
            </div>
        </div>

        <!-- Add form -->
        <div :class="[ui.card, 'p-5 mb-5']">
            <p :class="[ui.subheading, 'mb-3']">New Unit</p>
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-48">
                    <label :class="ui.fieldLabel">Name <span class="text-red-400">*</span></label>
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
                    <label :class="ui.fieldLabel">Abbreviation <span class="text-red-400">*</span></label>
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
        <div :class="ui.table">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-slate-200" :class="ui.tableHead">
                        <th class="py-3 px-4 text-left">Name</th>
                        <th class="py-3 px-4 text-left">Abbreviation</th>
                        <th class="py-3 px-4 text-right">Items</th>
                        <th class="w-20 py-3 px-4"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="unit in units"
                        :key="unit.id"
                        class="border-b border-slate-100 last:border-0 hover:bg-slate-50 transition-colors"
                    >
                        <td class="py-2.5 px-4 text-sm font-medium text-slate-900">{{ unit.name }}</td>
                        <td class="py-2.5 px-4 text-sm text-slate-500">{{ unit.abbreviation }}</td>
                        <td class="py-2.5 px-4 text-sm text-right tabular-nums text-slate-600">{{ unit.items_count }}</td>
                        <td class="py-2.5 px-4 text-right">
                            <button
                                @click="remove(unit)"
                                class="text-xs text-slate-500 hover:text-rose-600 transition-colors"
                                :disabled="unit.items_count > 0"
                                :title="unit.items_count > 0 ? 'Unlink items first' : ''"
                                :class="{ 'opacity-30 cursor-not-allowed': unit.items_count > 0 }"
                            >Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!units.length">
                        <td colspan="4" class="py-14 text-center text-xs text-slate-400">
                            No units yet. Use the form above to add one.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
</template>
