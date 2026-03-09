<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// ─── Types ────────────────────────────────────────────────────────────────────
interface Transaction {
    id: number;
    reference: string;
    user?: {
        id: number;
        name: string;
        account_id: string;
        email: string;
    };
    kind: 'charge' | 'payment';
    type: string;
    year: string | null;
    semester: string | null;
    amount: number;
    status: string;
    payment_channel?: string;
    paid_at?: string;
    created_at: string;
    meta?: Record<string, any>;
}

interface TermSummary {
    total_assessment: number;
    total_paid: number;
    current_balance: number;
}

interface Props {
    auth: {
        user: {
            id: number;
            name: string;
            role: string;
        };
    };
    transactionsByTerm: Record<string, Transaction[]>;
    account: {
        balance: number;
    } | null;
    currentTerm: string;
}

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps<Props>();

// ─── State ────────────────────────────────────────────────────────────────────
const breadcrumbs = [{ title: 'Dashboard', href: route('dashboard') }, { title: 'Transaction History' }];

const search              = ref('');
const expanded            = ref<Record<string, boolean>>({});
const showPastSemesters   = ref(false);
const selectedTransaction = ref<Transaction | null>(null);
const showDetailsDialog   = ref(false);

// ─── Role helpers ─────────────────────────────────────────────────────────────
const isStaff = computed(() => ['admin', 'accounting', 'super_admin'].includes(props.auth.user.role));

// ─── Auto-expand current term ─────────────────────────────────────────────────
if (props.currentTerm && props.transactionsByTerm?.[props.currentTerm]) {
    expanded.value[props.currentTerm] = true;
}

// ─── Counts ───────────────────────────────────────────────────────────────────
const totalTermsCount = computed(() => Object.keys(props.transactionsByTerm ?? {}).length);

const toggle = (key: string) => {
    expanded.value[key] = !expanded.value[key];
};

// ─── Summary per term ─────────────────────────────────────────────────────────
const calculateTermSummary = (transactions: Transaction[]): TermSummary => {
    if (!transactions?.length) return { total_assessment: 0, total_paid: 0, current_balance: 0 };

    const charges  = transactions.filter((t) => t.kind === 'charge').reduce((s, t) => s + parseFloat(String(t.amount || 0)), 0);
    const payments = transactions.filter((t) => t.kind === 'payment' && t.status === 'paid').reduce((s, t) => s + parseFloat(String(t.amount || 0)), 0);

    return { total_assessment: charges, total_paid: payments, current_balance: charges - payments };
};

// ─── Filtering ────────────────────────────────────────────────────────────────
const filteredTransactionsByTerm = computed(() => {
    if (!props.transactionsByTerm) return {};

    let terms = props.transactionsByTerm;

    if (!showPastSemesters.value && props.currentTerm && terms[props.currentTerm]) {
        terms = { [props.currentTerm]: terms[props.currentTerm] };
    }

    if (!search.value) return terms;

    const q = search.value.toLowerCase();
    const result: Record<string, Transaction[]> = {};

    Object.entries(terms).forEach(([term, txns]) => {
        const matched = txns.filter(
            (t) =>
                t.reference?.toLowerCase().includes(q) ||
                t.type?.toLowerCase().includes(q) ||
                t.user?.name?.toLowerCase().includes(q) ||
                t.user?.account_id?.toLowerCase().includes(q),
        );
        if (matched.length) result[term] = matched;
    });

    return result;
});

// ─── Balance ──────────────────────────────────────────────────────────────────
const accountBalance = computed(() => parseFloat(String(props.account?.balance ?? 0)));
const hasCredit      = computed(() => accountBalance.value < 0);
const displayBalance = computed(() => Math.abs(accountBalance.value));
const canMakePayment = computed(() => accountBalance.value > 0);

// ─── Helpers ──────────────────────────────────────────────────────────────────
const formatCurrency = (amount: number) =>
    new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount);

const formatDate = (date: string) =>
    new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

// ─── Receipt helpers ──────────────────────────────────────────────────────────

/**
 * Returns true only if the term has at least one confirmed paid payment.
 * A term with only awaiting_approval payments cannot produce a valid PDF.
 */
const canDownloadTermSummary = (transactions: Transaction[]): boolean => {
    return transactions.some((t) => t.kind === 'payment' && t.status === 'paid');
};

/**
 * Download a SINGLE-PAYMENT receipt PDF for one specific transaction.
 *
 * Calls GET /transactions/{id}/receipt — the PDF shows only that payment:
 * what it was for (term name), amount, method, date, and remaining balance.
 *
 * Used by the 📄 Receipt button on each individual payment row.
 */
const downloadReceipt = (transactionId: number) => {
    const url = route('transactions.receipt', { transaction: transactionId });
    window.open(url, '_blank');
};

/**
 * Download a FULL-TERM summary PDF for all transactions in a given term.
 *
 * Calls GET /transactions/download?term=2026+1st+Sem — the PDF shows all
 * confirmed charges and paid payments for the term with balance totals.
 * Terms with only awaiting_approval payments cannot be downloaded.
 *
 * Used by the 📄 Term Summary button on the term-group header.
 */
const downloadTermSummary = (termKey: string) => {
    const url = route('transactions.download') + '?term=' + encodeURIComponent(termKey);
    window.open(url, '_blank');
};

const viewTransaction = (transaction: Transaction) => {
    selectedTransaction.value = transaction;
    showDetailsDialog.value   = true;
};

const closeDetailsDialog = () => {
    showDetailsDialog.value   = false;
    selectedTransaction.value = null;
};

const payNow = () => {
    if (!canMakePayment.value) return;
    router.visit(route('student.account', { tab: 'payment' }));
};
</script>

<template>
    <Head title="Transaction History" />

    <AppLayout>
        <div class="w-full space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- ── Header ── -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Transaction History</h1>
                    <p class="text-gray-500">View all your financial transactions by term</p>
                </div>
                <Button v-if="totalTermsCount > 1" variant="outline" @click="showPastSemesters = !showPastSemesters">
                    {{ showPastSemesters ? 'Hide Past Semesters' : 'Show Past Semesters' }}
                </Button>
            </div>

            <!-- ── Balance Card (students only) ── -->
            <div v-if="!isStaff && account" class="rounded-xl border p-6 shadow-sm" :class="hasCredit ? 'bg-green-50' : 'bg-blue-50'">
                <h2 class="text-lg font-semibold">Current Balance</h2>
                <p class="text-gray-500">{{ hasCredit ? 'You have a credit balance' : 'Your outstanding balance' }}</p>
                <p class="mt-2 text-4xl font-bold" :class="hasCredit ? 'text-green-600' : accountBalance > 0 ? 'text-red-600' : 'text-green-600'">
                    {{ hasCredit ? '−' : '' }}₱{{ formatCurrency(displayBalance) }}
                </p>
                <p v-if="hasCredit" class="mt-1 text-sm text-green-600">Credit will be applied to your next assessment.</p>
            </div>

            <!-- ── Search Bar (staff only) ── -->
            <div v-if="isStaff" class="rounded-xl border bg-white p-4 shadow-sm">
                <input
                    v-model="search"
                    type="text"
                    class="w-full rounded-lg border p-3 outline-none focus:border-transparent focus:ring-2 focus:ring-blue-500"
                    placeholder="Search by reference, type, or student…"
                />
            </div>

            <!-- ── No Results ── -->
            <div v-if="Object.keys(filteredTransactionsByTerm).length === 0" class="py-12 text-center">
                <p class="text-lg text-gray-500">No transactions found</p>
                <p class="mt-2 text-sm text-gray-400">Try adjusting your search or show past semesters</p>
            </div>

            <!-- ── Term Groups ── -->
            <div
                v-for="(transactions, termKey) in filteredTransactionsByTerm"
                :key="termKey"
                class="overflow-hidden rounded-xl border bg-white shadow-sm"
            >
                <!-- Collapsible header -->
                <div
                    class="flex cursor-pointer items-center justify-between p-5 transition-colors select-none hover:bg-gray-50"
                    @click="toggle(termKey)"
                >
                    <div class="flex-1">
                        <div class="flex items-center gap-3">
                            <h2 class="text-xl font-bold">{{ termKey }}</h2>
                            <span v-if="termKey === currentTerm" class="rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800">
                                Current Term
                            </span>
                        </div>
                        <p class="mt-1 text-gray-500">
                            {{ transactions.length }} transaction{{ transactions.length !== 1 ? 's' : '' }}
                        </p>
                    </div>

                    <!-- Summary numbers -->
                    <div class="flex items-center gap-10 text-right">
                        <div>
                            <p class="text-xs text-gray-500">Total Assessed</p>
                            <p class="font-bold text-red-600">₱{{ formatCurrency(calculateTermSummary(transactions).total_assessment) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Total Paid</p>
                            <p class="font-bold text-green-600">₱{{ formatCurrency(calculateTermSummary(transactions).total_paid) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Balance</p>
                            <p class="font-bold" :class="calculateTermSummary(transactions).current_balance > 0 ? 'text-red-600' : 'text-green-600'">
                                ₱{{ formatCurrency(Math.abs(calculateTermSummary(transactions).current_balance)) }}
                            </p>
                        </div>

                        <!--
                            Term-level Receipt button: downloads the FULL TERM SUMMARY PDF.
                            Only enabled when the term has at least one confirmed (paid) payment.
                            Awaiting-verification payments are excluded from the PDF.
                        -->
                        <button
                            :disabled="!canDownloadTermSummary(transactions)"
                            :class="[
                                'rounded-lg px-4 py-2 text-sm font-medium transition-colors',
                                canDownloadTermSummary(transactions)
                                    ? 'bg-blue-600 text-white hover:bg-blue-700'
                                    : 'cursor-not-allowed bg-gray-300 text-gray-500',
                            ]"
                            @click.stop="canDownloadTermSummary(transactions) && downloadTermSummary(termKey)"
                            :title="canDownloadTermSummary(transactions)
                                ? 'Download full term summary'
                                : 'Not available — payments are still awaiting verification'"
                        >
                            📄 Term Summary
                        </button>

                        <!-- Chevron -->
                        <svg
                            :class="expanded[termKey] ? 'rotate-180' : ''"
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-6 w-6 transition-transform"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>

                <!-- Expanded rows -->
                <div v-if="expanded[termKey]" class="border-t p-5">
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr class="bg-gray-100 text-xs text-gray-600 uppercase">
                                    <th class="p-3 font-semibold">Reference</th>
                                    <th v-if="isStaff" class="p-3 font-semibold">Student</th>
                                    <th class="p-3 font-semibold">Kind</th>
                                    <th class="p-3 font-semibold">Category</th>
                                    <th class="p-3 font-semibold">Year & Semester</th>
                                    <th class="p-3 font-semibold">Amount</th>
                                    <th class="p-3 font-semibold">Status</th>
                                    <th class="p-3 font-semibold">Date</th>
                                    <th class="p-3 font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="t in transactions" :key="t.id" class="border-b transition-colors hover:bg-gray-50">
                                    <td class="p-3 font-mono text-xs text-gray-700">{{ t.reference }}</td>
                                    <td v-if="isStaff" class="p-3 text-sm">
                                        <div>
                                            <p class="font-medium">{{ t.user?.name }}</p>
                                            <p class="text-xs text-gray-500">{{ t.user?.account_id }}</p>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <span
                                            class="rounded-full px-2 py-1 text-xs font-semibold"
                                            :class="t.kind === 'charge' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'"
                                        >
                                            {{ t.kind }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-sm">
                                        <!-- Show term_name from meta if available (e.g. "Prelim"), otherwise type -->
                                        {{ t.meta?.term_name ?? t.type }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        <div v-if="t.year || t.semester" class="space-y-0.5">
                                            <p v-if="t.year" class="font-medium">{{ t.year }}</p>
                                            <p v-if="t.semester" class="text-xs text-gray-500">{{ t.semester }}</p>
                                        </div>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                    <td class="p-3 font-semibold" :class="t.kind === 'charge' ? 'text-red-600' : 'text-green-600'">
                                        {{ t.kind === 'charge' ? '+' : '−' }}₱{{ formatCurrency(t.amount) }}
                                    </td>
                                    <td class="p-3">
                                        <span
                                            class="rounded-full px-2 py-1 text-xs font-semibold"
                                            :class="{
                                                'bg-green-100 text-green-800': t.status === 'paid',
                                                'bg-yellow-100 text-yellow-800': t.status === 'pending',
                                                'bg-blue-100 text-blue-800': t.status === 'awaiting_approval',
                                                'bg-red-100 text-red-800': t.status === 'failed',
                                                'bg-gray-100 text-gray-800': t.status === 'cancelled',
                                            }"
                                        >
                                            {{ t.status === 'awaiting_approval' ? 'Awaiting Verification' : t.status }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-xs text-gray-500">{{ formatDate(t.created_at) }}</td>
                                    <td class="p-3">
                                        <div class="flex gap-2">
                                            <button
                                                @click="viewTransaction(t)"
                                                class="rounded-lg bg-blue-600 px-3 py-1 text-xs text-white transition-colors hover:bg-blue-700"
                                            >
                                                View
                                            </button>
                                            <!--
                                                Row-level Receipt: downloads a SINGLE-PAYMENT receipt PDF
                                                for this specific payment transaction only.
                                                ONLY available for confirmed paid payments.
                                                Awaiting-verification payments cannot be downloaded.
                                            -->
                                            <button
                                                v-if="t.kind === 'payment' && t.status === 'paid'"
                                                @click="downloadReceipt(t.id)"
                                                class="rounded-lg bg-green-600 px-3 py-1 text-xs text-white transition-colors hover:bg-green-700"
                                                title="Download payment receipt"
                                            >
                                                📄 Receipt
                                            </button>
                                            <span
                                                v-if="t.kind === 'payment' && t.status === 'awaiting_approval'"
                                                class="cursor-not-allowed rounded-lg bg-gray-200 px-3 py-1 text-xs text-gray-500"
                                                title="Receipt not available — payment is awaiting accounting verification"
                                            >
                                                ⏳ Pending
                                            </span>
                                            <button
                                                v-if="t.status === 'pending' && t.kind === 'charge' && !isStaff"
                                                @click="payNow"
                                                :disabled="!canMakePayment"
                                                class="rounded-lg px-3 py-1 text-xs transition-colors"
                                                :class="canMakePayment ? 'bg-red-600 text-white hover:bg-red-700' : 'cursor-not-allowed bg-gray-400 text-gray-200'"
                                            >
                                                Pay Now
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ── Transaction Detail Dialog ── -->
            <Dialog v-model:open="showDetailsDialog">
                <DialogContent class="max-h-[80vh] max-w-2xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Transaction Details</DialogTitle>
                        <DialogDescription>Complete information about this transaction</DialogDescription>
                    </DialogHeader>

                    <div v-if="selectedTransaction" class="space-y-5">
                        <!-- Basic Info -->
                        <div>
                            <h3 class="mb-3 border-b pb-2 text-base font-semibold">Basic Information</h3>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-xs text-gray-500">Reference</p>
                                    <p class="font-mono text-sm font-medium">{{ selectedTransaction.reference }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Date</p>
                                    <p class="text-sm font-medium">{{ formatDate(selectedTransaction.created_at) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Term</p>
                                    <p class="text-sm font-medium">
                                        {{ [selectedTransaction.year, selectedTransaction.semester].filter(Boolean).join(' ') || '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Kind</p>
                                    <span
                                        class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="selectedTransaction.kind === 'charge' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'"
                                    >
                                        {{ selectedTransaction.kind }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Status</p>
                                    <span
                                        class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="{
                                            'bg-green-100 text-green-800': selectedTransaction.status === 'paid',
                                            'bg-yellow-100 text-yellow-800': selectedTransaction.status === 'pending',
                                            'bg-blue-100 text-blue-800': selectedTransaction.status === 'awaiting_approval',
                                            'bg-red-100 text-red-800': selectedTransaction.status === 'failed',
                                            'bg-gray-100 text-gray-800': selectedTransaction.status === 'cancelled',
                                        }"
                                    >
                                        {{ selectedTransaction.status === 'awaiting_approval' ? 'Awaiting Verification' : selectedTransaction.status }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Category</p>
                                    <p class="text-sm font-medium">
                                        {{ selectedTransaction.meta?.term_name ?? selectedTransaction.type }}
                                    </p>
                                </div>
                                <div class="col-span-2">
                                    <p class="text-xs text-gray-500">Amount</p>
                                    <p class="text-2xl font-bold" :class="selectedTransaction.kind === 'charge' ? 'text-red-600' : 'text-green-600'">
                                        {{ selectedTransaction.kind === 'charge' ? '+' : '−' }}₱{{ formatCurrency(selectedTransaction.amount) }}
                                    </p>
                                </div>
                                <div v-if="!isStaff" class="col-span-2">
                                    <p class="text-xs text-gray-500">Overall Remaining Balance</p>
                                    <p class="text-lg font-bold" :class="accountBalance > 0 ? 'text-red-600' : 'text-green-600'">
                                        ₱{{ formatCurrency(displayBalance) }}
                                        <span v-if="hasCredit" class="text-sm font-normal text-green-600">(Credit)</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Student info (staff only) -->
                        <div v-if="isStaff && selectedTransaction.user">
                            <h3 class="mb-3 border-b pb-2 text-base font-semibold">Student Information</h3>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-xs text-gray-500">Name</p>
                                    <p class="text-sm font-medium">{{ selectedTransaction.user.name }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Student No.</p>
                                    <p class="text-sm font-medium">{{ selectedTransaction.user.account_id }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Email</p>
                                    <p class="text-sm font-medium">{{ selectedTransaction.user.email }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Payment info -->
                        <div v-if="selectedTransaction.kind === 'payment'">
                            <h3 class="mb-3 border-b pb-2 text-base font-semibold">Payment Information</h3>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-xs text-gray-500">Payment Method</p>
                                    <p class="text-sm font-medium capitalize">
                                        {{ selectedTransaction.payment_channel?.replace(/_/g, ' ') || 'N/A' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Payment Date</p>
                                    <p class="text-sm font-medium">
                                        {{ selectedTransaction.paid_at ? formatDate(selectedTransaction.paid_at) : 'N/A' }}
                                    </p>
                                </div>
                                <div v-if="selectedTransaction.meta?.term_name" class="col-span-2">
                                    <p class="text-xs text-gray-500">Payment For</p>
                                    <p class="text-sm font-semibold text-green-700">{{ selectedTransaction.meta.term_name }}</p>
                                </div>
                                <div v-if="selectedTransaction.meta?.description" class="col-span-2">
                                    <p class="text-xs text-gray-500">Description</p>
                                    <p class="text-sm font-medium">{{ selectedTransaction.meta.description }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 border-t pt-4">
                            <Button variant="outline" @click="closeDetailsDialog">Close</Button>
                            <!--
                                Dialog receipt button: downloads the SINGLE-PAYMENT receipt for this
                                specific transaction. Only shown for confirmed PAID payment transactions.
                                Awaiting-verification payments cannot be downloaded.
                            -->
                            <Button
                                v-if="selectedTransaction.kind === 'payment' && selectedTransaction.status === 'paid'"
                                @click="downloadReceipt(selectedTransaction.id)"
                            >
                                📄 Payment Receipt
                            </Button>
                            <span
                                v-if="selectedTransaction.kind === 'payment' && selectedTransaction.status === 'awaiting_approval'"
                                class="flex items-center rounded-lg bg-amber-100 px-4 py-2 text-sm font-medium text-amber-700"
                            >
                                ⏳ Awaiting Verification — Receipt Not Yet Available
                            </span>
                            <Button
                                v-if="selectedTransaction.status === 'pending' && selectedTransaction.kind === 'charge' && !isStaff"
                                :disabled="!canMakePayment"
                                variant="destructive"
                                @click="payNow(); closeDetailsDialog();"
                            >
                                {{ canMakePayment ? 'Pay Now' : 'Cannot Pay' }}
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>