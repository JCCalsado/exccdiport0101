<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { AlertCircle, Check } from 'lucide-vue-next';

// ── Props ────────────────────────────────────────────────────────────────────
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

// ── State ────────────────────────────────────────────────────────────────────
const editing = ref<number | null>(null);
const editValues = ref<Record<number, string>>({});
const saving = ref(false);
const flashSuccess = ref('');
const flashError = ref('');

// ── Computed ─────────────────────────────────────────────────────────────────
const rateSettings = computed(() => props.settings['rate'] ?? []);
const miscSettings = computed(() => props.settings['miscellaneous'] ?? []);
const otherSettings = computed(() => props.settings['other'] ?? []);
const termSettings = computed(() => props.settings['term'] ?? []);

const termTotal = computed(() =>
    termSettings.value.reduce((sum, s) => {
        const val = editing.value !== null && editValues.value[s.id] !== undefined
            ? parseFloat(editValues.value[s.id] || '0')
            : parseFloat(s.amount);
        return sum + (isNaN(val) ? 0 : val);
    }, 0)
);

const liveMiscTotal = computed(() => {
    return [...miscSettings.value, ...otherSettings.value].reduce((sum, s) => {
        const val = editValues.value[s.id] !== undefined
            ? parseFloat(editValues.value[s.id] || '0')
            : parseFloat(s.amount);
        return sum + (isNaN(val) ? 0 : val);
    }, 0);
});

const breadcrumbs = [
    { title: 'Dashboard', href: route('accounting.dashboard') },
    { title: 'Fee Settings', href: route('accounting.fee-settings.index') },
];

// ── Methods ───────────────────────────────────────────────────────────────────
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
        onError: (errors) => {
            flashError.value = Object.values(errors).flat().join(' ');
        },
        onFinish: () => { saving.value = false; },
    });
}

function fmt(val: string | number): string {
    return '₱' + parseFloat(String(val)).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}
</script>

<template>
    <Head title="Fee Settings" />
    <AppLayout>
        <div class="w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <div class="max-w-4xl mx-auto space-y-8 mt-6">

                <!-- Header -->
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Fee Settings</h1>
                    <p class="text-sm text-gray-500 mt-2">
                        Manage tuition rates, miscellaneous fees, and payment term percentages.
                        Changes take effect immediately on the next assessment created.
                    </p>
                </div>

                <!-- Flash messages -->
                <div v-if="flashSuccess" class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
                    <Check :size="18" />
                    {{ flashSuccess }}
                </div>
                <div v-if="flashError" class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
                    <AlertCircle :size="18" />
                    {{ flashError }}
                </div>

                <!-- ── Billing Rates ──────────────────────────────────────────── -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Billing Rates</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="text-left px-4 py-3 font-medium text-gray-600">Fee</th>
                                        <th class="text-right px-4 py-3 font-medium text-gray-600">Amount</th>
                                        <th class="w-24 px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="s in rateSettings" :key="s.id" class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-gray-700">{{ s.label }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <span v-if="editing !== s.id" class="font-mono text-gray-900">{{ fmt(s.amount) }}</span>
                                            <div v-else class="flex items-center justify-end gap-2">
                                                <span class="text-gray-500">₱</span>
                                                <input
                                                    type="number" step="0.01" min="0"
                                                    v-model="editValues[s.id]"
                                                    class="w-32 border border-blue-400 rounded px-2 py-1 text-right font-mono focus:outline-none focus:ring-2 focus:ring-blue-300"
                                                    @keyup.enter="saveOne(s)"
                                                    @keyup.escape="cancelEdit(s.id)"
                                                />
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button v-if="editing !== s.id"
                                                @click="startEdit(s.id, s.amount)"
                                                class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                                Edit
                                            </button>
                                            <div v-else class="flex gap-2 justify-end">
                                                <button @click="saveOne(s)" :disabled="saving"
                                                    class="text-green-600 hover:text-green-800 text-xs font-medium disabled:opacity-50">
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
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <!-- ── Miscellaneous Fees ─────────────────────────────────────── -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-lg">Miscellaneous Fees</CardTitle>
                            <span class="text-sm text-gray-600">
                                Total: <span class="font-semibold text-gray-900">{{ fmt(liveMiscTotal) }}</span>
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="text-left px-4 py-3 font-medium text-gray-600">Fee</th>
                                        <th class="text-left px-4 py-3 font-medium text-gray-600">Category</th>
                                        <th class="text-right px-4 py-3 font-medium text-gray-600">Amount</th>
                                        <th class="w-24 px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template v-for="group in [miscSettings, otherSettings]">
                                        <tr v-for="s in group" :key="s.id" class="hover:bg-gray-50">
                                            <td class="px-4 py-3 text-gray-700">{{ s.label }}</td>
                                            <td class="px-4 py-3">
                                                <span :class="s.category === 'other'
                                                    ? 'bg-purple-50 text-purple-700 border border-purple-200'
                                                    : 'bg-blue-50 text-blue-700 border border-blue-200'"
                                                    class="text-xs px-2 py-0.5 rounded-full font-medium">
                                                    {{ s.category === 'other' ? 'Other' : 'Miscellaneous' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <span v-if="editing !== s.id" class="font-mono text-gray-900">{{ fmt(s.amount) }}</span>
                                                <div v-else class="flex items-center justify-end gap-2">
                                                    <span class="text-gray-500">₱</span>
                                                    <input
                                                        type="number" step="0.01" min="0"
                                                        v-model="editValues[s.id]"
                                                        class="w-28 border border-blue-400 rounded px-2 py-1 text-right font-mono focus:outline-none focus:ring-2 focus:ring-blue-300"
                                                        @keyup.enter="saveOne(s)"
                                                        @keyup.escape="cancelEdit(s.id)"
                                                    />
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <button v-if="editing !== s.id"
                                                    @click="startEdit(s.id, s.amount)"
                                                    class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                                    Edit
                                                </button>
                                                <div v-else class="flex gap-2 justify-end">
                                                    <button @click="saveOne(s)" :disabled="saving"
                                                        class="text-green-600 hover:text-green-800 text-xs font-medium disabled:opacity-50">
                                                        Save
                                                    </button>
                                                    <button @click="cancelEdit(s.id)"
                                                        class="text-gray-400 hover:text-gray-600 text-xs">
                                                        Cancel
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <!-- ── Payment Terms ──────────────────────────────────────────── -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-lg">Payment Term Percentages</CardTitle>
                            <span :class="Math.abs(termTotal - 100) > 0.01 ? 'text-red-600 font-semibold' : 'text-gray-600'" class="text-sm">
                                Total: {{ termTotal.toFixed(2) }}%
                                <span v-if="Math.abs(termTotal - 100) > 0.01" class="ml-1">⚠ Must equal 100%</span>
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="text-left px-4 py-3 font-medium text-gray-600">Term</th>
                                        <th class="text-right px-4 py-3 font-medium text-gray-600">Percentage</th>
                                        <th class="w-24 px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="s in termSettings" :key="s.id" class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-gray-700">{{ s.label }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <span v-if="editing !== s.id" class="font-mono text-gray-900">
                                                {{ parseFloat(s.amount).toFixed(2) }}%
                                            </span>
                                            <div v-else class="flex items-center justify-end gap-2">
                                                <input
                                                    type="number" step="0.01" min="0" max="100"
                                                    v-model="editValues[s.id]"
                                                    class="w-24 border border-blue-400 rounded px-2 py-1 text-right font-mono focus:outline-none focus:ring-2 focus:ring-blue-300"
                                                    @keyup.enter="saveOne(s)"
                                                    @keyup.escape="cancelEdit(s.id)"
                                                />
                                                <span class="text-gray-500">%</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button v-if="editing !== s.id"
                                                @click="startEdit(s.id, s.amount)"
                                                class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                                Edit
                                            </button>
                                            <div v-else class="flex gap-2 justify-end">
                                                <button @click="saveOne(s)" :disabled="saving || Math.abs(termTotal - 100) > 0.01"
                                                    class="text-green-600 hover:text-green-800 text-xs font-medium disabled:opacity-40 disabled:cursor-not-allowed">
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
                            </table>
                        </div>
                        <p class="text-xs text-gray-400 mt-4">
                            Percentages must sum to exactly 100%. Changes only affect new assessments.
                        </p>
                    </CardContent>
                </Card>

            </div>
        </div>
    </AppLayout>
</template>
