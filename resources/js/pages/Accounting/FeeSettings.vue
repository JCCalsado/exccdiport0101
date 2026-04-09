<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    settings: Record<string, Array<{
        id: number;
        key: string;
        label: string;
        amount: string;
        category: string;
    }>>;
    miscTotal: number;
}>();

const editing = ref<number | null>(null);
const editValues = ref<Record<number, string>>({});
const saving = ref(false);
const flashSuccess = ref('');
const flashError = ref('');

const rateSettings  = computed(() => props.settings['rate']          ?? []);
const miscSettings  = computed(() => props.settings['miscellaneous'] ?? []);
const otherSettings = computed(() => props.settings['other']         ?? []);

const liveMiscTotal = computed(() =>
    [...miscSettings.value, ...otherSettings.value].reduce((sum, s) => {
        const val = editValues.value[s.id] !== undefined
            ? parseFloat(editValues.value[s.id] || '0')
            : parseFloat(s.amount);
        return sum + (isNaN(val) ? 0 : val);
    }, 0)
);

function startEdit(id: number, current: string) {
    editing.value = id;
    editValues.value[id] = current;
}

function cancelEdit(id: number) {
    editing.value = null;
    delete editValues.value[id];
}

function saveOne(setting: { id: number; label: string }) {
    saving.value = true;
    flashSuccess.value = '';
    flashError.value = '';

    router.patch(route('accounting.fee-settings.update', setting.id), {
        amount: parseFloat(editValues.value[setting.id] || '0'),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
            delete editValues.value[setting.id];
            flashSuccess.value = `${setting.label} updated.`;
            setTimeout(() => flashSuccess.value = '', 3000);
        },
        onError: (errors: Record<string, string>) => {
            flashError.value = Object.values(errors).flat().join(' ');
        },
        onFinish: () => { saving.value = false; },
    });
}

function fmt(val: string | number) {
    return '₱' + parseFloat(String(val)).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
</script>

<template>
    <AppLayout title="Fee Settings">
        <div class="max-w-3xl mx-auto px-4 py-8 space-y-8">

            <div>
                <h1 class="text-2xl font-bold text-gray-900">Fee Settings</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Changes apply to new assessments only. Existing assessments are not affected.
                </p>
            </div>

            <div v-if="flashSuccess"
                class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
                ✓ {{ flashSuccess }}
            </div>
            <div v-if="flashError"
                class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
                {{ flashError }}
            </div>

            <!-- Billing Rates -->
            <section>
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">
                    Billing Rates
                </h2>
                <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                    <div v-for="s in rateSettings" :key="s.id"
                        class="flex items-center justify-between px-5 py-4 hover:bg-gray-50">
                        <span class="text-sm text-gray-700">{{ s.label }}</span>
                        <div class="flex items-center gap-4">
                            <span v-if="editing !== s.id"
                                class="font-mono text-sm font-semibold text-gray-900 w-28 text-right">
                                {{ fmt(s.amount) }}
                            </span>
                            <div v-else class="flex items-center gap-1">
                                <span class="text-gray-400 text-sm">₱</span>
                                <input
                                    type="number" step="0.01" min="0"
                                    v-model="editValues[s.id]"
                                    class="w-28 border border-blue-400 rounded px-2 py-1 text-right text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-300"
                                    @keyup.enter="saveOne(s)"
                                    @keyup.escape="cancelEdit(s.id)"
                                />
                            </div>
                            <div class="w-20 text-right">
                                <button v-if="editing !== s.id"
                                    @click="startEdit(s.id, s.amount)"
                                    class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                    Edit
                                </button>
                                <div v-else class="flex gap-2 justify-end">
                                    <button @click="saveOne(s)" :disabled="saving"
                                        class="text-green-600 hover:text-green-800 text-xs font-medium disabled:opacity-40">
                                        Save
                                    </button>
                                    <button @click="cancelEdit(s.id)"
                                        class="text-gray-400 hover:text-gray-600 text-xs">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">
                    Tuition is charged per enrolled unit. Lab fee is charged once per subject with lab.
                </p>
            </section>

            <!-- Miscellaneous Fees -->
            <section>
                <div class="flex items-baseline justify-between mb-3">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-widest">
                        Miscellaneous Fees
                    </h2>
                    <span class="text-sm text-gray-600">
                        Total:
                        <span class="font-semibold text-gray-900 font-mono">{{ fmt(liveMiscTotal) }}</span>
                        <span class="text-gray-400 text-xs ml-1">(charged per semester)</span>
                    </span>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wide">
                            <tr>
                                <th class="text-left px-5 py-3 font-medium text-gray-500">Fee</th>
                                <th class="text-left px-5 py-3 font-medium text-gray-500">Type</th>
                                <th class="text-right px-5 py-3 font-medium text-gray-500">Amount</th>
                                <th class="w-24 px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="s in [...miscSettings, ...otherSettings]" :key="s.id"
                                class="hover:bg-gray-50">
                                <td class="px-5 py-3 text-gray-700">{{ s.label }}</td>
                                <td class="px-5 py-3">
                                    <span :class="s.category === 'other'
                                        ? 'bg-purple-50 text-purple-700 border-purple-200'
                                        : 'bg-sky-50 text-sky-700 border-sky-200'"
                                        class="text-xs px-2 py-0.5 rounded-full border font-medium">
                                        {{ s.category === 'other' ? 'Other' : 'Miscellaneous' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <span v-if="editing !== s.id"
                                        class="font-mono font-semibold text-gray-900">
                                        {{ fmt(s.amount) }}
                                    </span>
                                    <div v-else class="flex items-center justify-end gap-1">
                                        <span class="text-gray-400">₱</span>
                                        <input
                                            type="number" step="0.01" min="0"
                                            v-model="editValues[s.id]"
                                            class="w-28 border border-blue-400 rounded px-2 py-1 text-right text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-300"
                                            @keyup.enter="saveOne(s)"
                                            @keyup.escape="cancelEdit(s.id)"
                                        />
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <button v-if="editing !== s.id"
                                        @click="startEdit(s.id, s.amount)"
                                        class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                        Edit
                                    </button>
                                    <div v-else class="flex gap-2 justify-end">
                                        <button @click="saveOne(s)" :disabled="saving"
                                            class="text-green-600 hover:text-green-800 text-xs font-medium disabled:opacity-40">
                                            Save
                                        </button>
                                        <button @click="cancelEdit(s.id)"
                                            class="text-gray-400 hover:text-gray-600 text-xs">
                                            Cancel
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                            <tr>
                                <td colspan="2" class="px-5 py-3 text-sm font-semibold text-gray-700">
                                    Total Miscellaneous
                                </td>
                                <td class="px-5 py-3 text-right font-mono font-bold text-gray-900">
                                    {{ fmt(liveMiscTotal) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <!-- Info: Payment terms are computed, not configurable -->
            <section>
                <div class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 text-sm text-amber-800">
                    <p class="font-semibold mb-1">About Payment Terms</p>
                    <p class="text-amber-700">
                        Payment term amounts are automatically computed from each student's total assessment fee.
                        They are split across 5 terms — Upon Registration, Prelim, Midterm, Semi-Final, and Final.
                        The peso amount per term is shown on the student's account, not here.
                    </p>
                </div>
            </section>

        </div>
    </AppLayout>
</template>