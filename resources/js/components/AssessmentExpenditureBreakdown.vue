<script setup lang="ts">
// ─── AssessmentExpenditureBreakdown ──────────────────────────────────────────
// Displays a colour-coded, categorised fee breakdown for a single assessment.
// Rendered inline below the assessment transaction row in Transaction History.
//
// Props:
//   feeBreakdown  — the raw fee_breakdown array from StudentAssessment
//   totalAmount   — the total_assessment value (used for the summary footer)
//
// The component groups line items by category so that:
//   • Tuition / Laboratory items are listed per-subject with unit cost
//   • Other categories (Registration, Library, Athletics, etc.) are flat rows
//   • The footer shows per-category subtotals and an overall grand total
//
// Layout mirrors the Enrolled Subjects detailed table so the UI is consistent.
// ─────────────────────────────────────────────────────────────────────────────

import { ChevronDown, FlaskConical, BookOpen, Layers } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useDataFormatting } from '@/composables/useDataFormatting';

const { formatCurrency } = useDataFormatting();

interface FeeLineItem {
    subject_id?: number;
    code?: string;
    name: string;
    category: string;
    units?: number;
    amount: number;
}

interface Props {
    feeBreakdown: FeeLineItem[];
    totalAmount: number;
    /** Default open state — assessment sections start collapsed */
    defaultOpen?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    defaultOpen: false,
});

// ── Local expand state ────────────────────────────────────────────────────────
const isOpen = ref(props.defaultOpen);
const toggle = () => { isOpen.value = !isOpen.value; };

// ── Category colour mapping ───────────────────────────────────────────────────
const CATEGORY_COLORS: Record<string, { bg: string; text: string; border: string }> = {
    Tuition:      { bg: 'bg-blue-50',   text: 'text-blue-700',   border: 'border-blue-200' },
    Laboratory:   { bg: 'bg-purple-50', text: 'text-purple-700', border: 'border-purple-200' },
    Registration: { bg: 'bg-amber-50',  text: 'text-amber-700',  border: 'border-amber-200' },
    Library:      { bg: 'bg-green-50',  text: 'text-green-700',  border: 'border-green-200' },
    Athletics:    { bg: 'bg-red-50',    text: 'text-red-700',    border: 'border-red-200' },
    Miscellaneous:{ bg: 'bg-gray-50',   text: 'text-gray-700',   border: 'border-gray-200' },
};

const categoryColor = (cat: string) =>
    CATEGORY_COLORS[cat] ?? { bg: 'bg-gray-50', text: 'text-gray-700', border: 'border-gray-200' };

// ── Group fee_breakdown by category ──────────────────────────────────────────
interface GroupedItem {
    name: string;
    code?: string;
    units?: number;
    amount: number;
    subject_id?: number;
}

interface CategoryGroup {
    category: string;
    items: GroupedItem[];
    subtotal: number;
}

const groupedCategories = computed((): CategoryGroup[] => {
    const map = new Map<string, GroupedItem[]>();

    for (const item of props.feeBreakdown) {
        const cat = item.category || 'Miscellaneous';
        if (!map.has(cat)) map.set(cat, []);
        map.get(cat)!.push({
            name:       item.name,
            code:       item.code,
            units:      item.units,
            amount:     parseFloat(String(item.amount)),
            subject_id: item.subject_id,
        });
    }

    const ORDER = ['Tuition', 'Laboratory', 'Registration', 'Library', 'Athletics', 'Miscellaneous'];

    return [...map.entries()]
        .sort(([a], [b]) => {
            const ai = ORDER.indexOf(a);
            const bi = ORDER.indexOf(b);
            if (ai === -1 && bi === -1) return a.localeCompare(b);
            if (ai === -1) return 1;
            if (bi === -1) return -1;
            return ai - bi;
        })
        .map(([category, items]) => ({
            category,
            items,
            subtotal: items.reduce((s, i) => s + i.amount, 0),
        }));
});

// Count of line items for summary header
const lineItemCount = computed(() =>
    props.feeBreakdown.length,
);

// Category icon
const categoryIcon = (cat: string) => {
    if (cat === 'Tuition')    return BookOpen;
    if (cat === 'Laboratory') return FlaskConical;
    return Layers;
};
</script>

<template>
    <!-- Outer container — sits below the transaction row, inside the term group -->
    <div class="border-t border-gray-100 bg-white">

        <!-- ── Trigger bar ────────────────────────────────────────────────── -->
        <button
            type="button"
            class="flex w-full items-center justify-between px-5 py-3 text-left text-sm transition-colors select-none hover:bg-amber-50"
            @click="toggle"
            :aria-expanded="isOpen"
        >
            <div class="flex items-center gap-2">
                <Layers class="h-4 w-4 text-amber-500" />
                <span class="font-semibold text-amber-800">Fee Breakdown</span>
                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                    {{ lineItemCount }} line item{{ lineItemCount !== 1 ? 's' : '' }}
                </span>
            </div>

            <div class="flex items-center gap-3">
                <span class="font-semibold text-amber-700">
                    Total: ₱{{ formatCurrency(totalAmount) }}
                </span>
                <ChevronDown
                    class="h-4 w-4 text-amber-600 transition-transform duration-200"
                    :class="{ 'rotate-180': isOpen }"
                />
            </div>
        </button>

        <!-- ── Expandable body ────────────────────────────────────────────── -->
        <div v-show="isOpen" class="border-t border-amber-100">

            <!-- ── Empty state ── -->
            <div v-if="groupedCategories.length === 0" class="px-5 py-6 text-center text-sm text-gray-400">
                No fee breakdown available for this assessment.
            </div>

            <!-- ── Category sections ── -->
            <div v-else>
                <div
                    v-for="group in groupedCategories"
                    :key="group.category"
                    class="border-b border-gray-100 last:border-b-0"
                >
                    <!-- Category header -->
                    <div
                        class="flex items-center justify-between px-5 py-2"
                        :class="categoryColor(group.category).bg"
                    >
                        <div class="flex items-center gap-1.5">
                            <component
                                :is="categoryIcon(group.category)"
                                class="h-3.5 w-3.5"
                                :class="categoryColor(group.category).text"
                            />
                            <span
                                class="text-xs font-bold uppercase tracking-wide"
                                :class="categoryColor(group.category).text"
                            >
                                {{ group.category }}
                            </span>
                            <span
                                class="ml-1 rounded-full border px-1.5 py-px text-xs font-medium"
                                :class="[categoryColor(group.category).border, categoryColor(group.category).text]"
                            >
                                {{ group.items.length }}
                            </span>
                        </div>
                        <span class="text-xs font-semibold" :class="categoryColor(group.category).text">
                            ₱{{ formatCurrency(group.subtotal) }}
                        </span>
                    </div>

                    <!-- Line items table -->
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-gray-50">
                            <tr
                                v-for="(item, idx) in group.items"
                                :key="`${group.category}-${idx}`"
                                class="transition-colors hover:bg-gray-50/70"
                            >
                                <!-- Code (Tuition/Lab only) -->
                                <td class="w-24 px-5 py-2.5">
                                    <span
                                        v-if="item.code"
                                        class="rounded bg-indigo-50 px-1.5 py-0.5 font-mono text-xs font-semibold text-indigo-700"
                                    >
                                        {{ item.code }}
                                    </span>
                                    <span v-else class="text-xs text-gray-300">—</span>
                                </td>

                                <!-- Name -->
                                <td class="px-5 py-2.5 text-gray-800">
                                    <div class="flex items-center gap-1.5">
                                        <span>{{ item.name }}</span>
                                        <FlaskConical
                                            v-if="group.category === 'Laboratory'"
                                            class="h-3 w-3 flex-shrink-0 text-purple-400"
                                            title="Laboratory component"
                                        />
                                    </div>
                                </td>

                                <!-- Units (Tuition/Lab only) -->
                                <td class="w-20 px-5 py-2.5 text-center">
                                    <span
                                        v-if="item.units != null && item.units > 0"
                                        class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700"
                                    >
                                        {{ item.units }} unit{{ item.units !== 1 ? 's' : '' }}
                                    </span>
                                    <span v-else class="text-xs text-gray-300">—</span>
                                </td>

                                <!-- Per-unit cost hint (Tuition only) -->
                                <td class="w-32 px-5 py-2.5 text-right text-xs text-gray-400">
                                    <template v-if="group.category === 'Tuition' && item.units && item.units > 0">
                                        {{ item.units }} × ₱{{ formatCurrency(item.amount / item.units) }}
                                    </template>
                                </td>

                                <!-- Amount -->
                                <td class="w-28 px-5 py-2.5 text-right font-semibold text-gray-900">
                                    ₱{{ formatCurrency(item.amount) }}
                                </td>
                            </tr>
                        </tbody>

                        <!-- Category subtotal row -->
                        <tfoot>
                            <tr class="border-t border-gray-100" :class="categoryColor(group.category).bg">
                                <td colspan="4" class="px-5 py-2 text-right text-xs font-semibold" :class="categoryColor(group.category).text">
                                    {{ group.category }} Subtotal
                                </td>
                                <td class="px-5 py-2 text-right text-xs font-semibold" :class="categoryColor(group.category).text">
                                    ₱{{ formatCurrency(group.subtotal) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- ── Grand total footer ── -->
                <div class="flex items-center justify-between border-t-2 border-amber-200 bg-amber-50 px-5 py-3">
                    <span class="text-sm font-bold text-amber-900">Total Assessment</span>
                    <span class="text-lg font-bold text-amber-700">
                        ₱{{ formatCurrency(totalAmount) }}
                    </span>
                </div>

                <!-- Footnote -->
                <div class="border-t border-amber-100 bg-white px-5 py-2 text-xs text-gray-400">
                    Fee breakdown reflects the original assessment charges. Payments are tracked separately in the transaction list above.
                </div>
            </div>
        </div>
    </div>
</template>