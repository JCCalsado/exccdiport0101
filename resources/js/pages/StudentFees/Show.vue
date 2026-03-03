<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ArrowLeft, Plus, Download, Wallet, CreditCard, TrendingDown, TrendingUp, CheckCircle2,
         AlertCircle, Clock, Receipt, ChevronDown, Eye } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';

interface PaymentTerm {
    id: number;
    term_name: string;
    term_order: number;
    percentage: number;
    amount: number;
    balance: number;
    due_date: string | null;
    status: string;
    remarks?: string;
}

interface Props {
    student: any;
    assessment: any;
    transactions: any[];
    payments: any[];
    feeBreakdown: Array<{ category: string; total: number; items: number }>;
}

const props = defineProps<Props>();

// ─── Balance ──────────────────────────────────────────────────────────────────
/**
 * Remaining balance — resolved in priority order:
 *
 * 1. account.balance  (set by AccountService::recalculate, most authoritative)
 *    Used when > 0, meaning charge transactions exist and have been summed.
 *
 * 2. SUM(payment_terms.balance)  (fallback)
 *    Used when account.balance is 0 but unpaid terms exist.
 *    This covers students like jcdc742713 whose assessment was seeded
 *    without charge transactions, so AccountService never saw any charges
 *    and left account.balance at 0.
 *
 * This makes the displayed balance accurate in both Index and Show
 * regardless of whether transactions were created alongside the assessment.
 */
const remainingBalance = computed(() => {
    const accountBal = parseFloat(String(props.student.account?.balance ?? 0));
    if (accountBal > 0) return accountBal;

    // Fallback: sum unpaid payment term balances
    const terms: PaymentTerm[] = props.assessment?.paymentTerms ?? [];
    if (terms.length > 0) {
        const termsTotal = terms.reduce((sum, t) => sum + parseFloat(String(t.balance)), 0);
        if (termsTotal > 0) return termsTotal;
    }

    return 0;
});

/** Total assessment amount from the assessment record. */
const totalAssessment = computed(() => parseFloat(String(props.assessment?.total_assessment || 0)));

/** Total paid = totalAssessment - remainingBalance (floor at 0). */
const totalPaid = computed(() => Math.max(0, totalAssessment.value - remainingBalance.value));

/**
 * Payment timing status using payment terms:
 * - 'behind' : first term (Upon Registration / term_order=1) is still fully unpaid
 * - 'on_track': first term has been at least partially paid
 * - 'paid'   : no remaining balance
 */
const paymentTimingStatus = computed((): 'behind' | 'on_track' | 'paid' => {
    if (remainingBalance.value === 0) return 'paid';

    const terms: PaymentTerm[] = props.assessment?.paymentTerms ?? [];
    if (terms.length === 0) return 'behind';

    const sorted = [...terms].sort((a, b) => a.term_order - b.term_order);
    const first = sorted[0];

    const firstBalance = parseFloat(String(first.balance));
    const firstAmount  = parseFloat(String(first.amount));

    // Behind if the first term hasn't been touched at all
    if (first.status === 'pending' && firstBalance >= firstAmount * 0.99) return 'behind';
    return 'on_track';
});

const balanceCardConfig = computed(() => {
    switch (paymentTimingStatus.value) {
        case 'paid':
            return {
                bg: 'bg-gradient-to-r from-green-50 to-emerald-50 border-green-200',
                iconBg: 'bg-green-100',
                icon: CheckCircle2,
                iconColor: 'text-green-600',
                labelColor: 'text-green-700',
                amountColor: 'text-green-700',
                badge: { label: 'Fully Paid', cls: 'bg-green-500 text-white' },
            };
        case 'on_track':
            return {
                bg: 'bg-gradient-to-r from-blue-50 to-indigo-50 border-blue-200',
                iconBg: 'bg-blue-100',
                icon: TrendingUp,
                iconColor: 'text-blue-600',
                labelColor: 'text-blue-700',
                amountColor: 'text-blue-700',
                badge: { label: 'On Track', cls: 'bg-blue-500 text-white' },
            };
        default: // behind
            return {
                bg: 'bg-gradient-to-r from-red-50 to-rose-50 border-red-200',
                iconBg: 'bg-red-100',
                icon: TrendingDown,
                iconColor: 'text-red-600',
                labelColor: 'text-red-700',
                amountColor: 'text-red-700',
                badge: { label: 'Behind Schedule', cls: 'bg-red-500 text-white' },
            };
    }
});

// ─── Payment Terms ─────────────────────────────────────────────────────────────
const availableTermsForPayment = computed(() => {
    const unpaidTerms: PaymentTerm[] = (props.assessment?.paymentTerms ?? [])
        .filter((t: PaymentTerm) => parseFloat(String(t.balance)) > 0)
        .sort((a: PaymentTerm, b: PaymentTerm) => a.term_order - b.term_order);

    return unpaidTerms.map((term: PaymentTerm, index: number) => ({
        ...term,
        isSelectable: index === 0,
        hasCarryover: term.remarks?.toLowerCase().includes('carried') ?? false,
    }));
});

const allTermsSorted = computed((): PaymentTerm[] => {
    return [...(props.assessment?.paymentTerms ?? [])].sort((a, b) => a.term_order - b.term_order);
});

const paidTermsCount = computed(() =>
    allTermsSorted.value.filter(t => t.status === 'paid').length
);

// ─── Fee breakdown enrichment ──────────────────────────────────────────────────
/**
 * Canonical fee line items with friendly labels.
 * We enrich the raw feeBreakdown from the controller with known display names.
 */
const feeLineItems = computed(() => {
    const labelMap: Record<string, string> = {
        Tuition:       'Tuition Fee',
        Miscellaneous: 'Miscellaneous Fee',
        Laboratory:    'Laboratory Fee',
        Library:       'Library Fee',
        Athletic:      'Athletic Fee',
        Registration:  'Registration Fee',
    };

    return props.feeBreakdown.map(item => ({
        ...item,
        displayLabel: labelMap[item.category] ?? item.category,
    }));
});

// ─── Transaction History (term-grouped, styled like Transactions/Index) ────────
interface TxGroup { key: string; transactions: any[]; totalCharges: number; totalPaid: number; balance: number }

const transactionsByTerm = computed((): TxGroup[] => {
    const groups: Record<string, any[]> = {};

    for (const t of props.transactions) {
        const key = t.year && t.semester ? `${t.year} ${t.semester}` : 'Other';
        if (!groups[key]) groups[key] = [];
        groups[key].push(t);
    }

    return Object.entries(groups).map(([key, txns]) => {
        const totalCharges = txns.filter(t => t.kind === 'charge').reduce((s, t) => s + parseFloat(t.amount), 0);
        const totalPaidAmt = txns.filter(t => t.kind === 'payment' && t.status === 'paid').reduce((s, t) => s + parseFloat(t.amount), 0);
        return { key, transactions: txns, totalCharges, totalPaid: totalPaidAmt, balance: totalCharges - totalPaidAmt };
    });
});

const expandedTerms = ref<Record<string, boolean>>({});
// Auto-expand first term
if (transactionsByTerm.value.length > 0) {
    expandedTerms.value[transactionsByTerm.value[0].key] = true;
}
const toggleTerm = (key: string) => {
    expandedTerms.value[key] = !expandedTerms.value[key];
};

// ─── Payment form ──────────────────────────────────────────────────────────────
const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Student Fee Management', href: route('student-fees.index') },
    { title: props.student.name },
];

const showPaymentDialog = ref(false);

const paymentForm = useForm({
    amount: '',
    payment_method: 'cash',
    term_id: null as string | number | null,
    payment_date: new Date().toISOString().split('T')[0],
});

const firstUnpaidTerm = computed(() =>
    availableTermsForPayment.value.find((t: any) => t.isSelectable) ?? null
);

const selectedTerm = computed(() =>
    paymentForm.term_id
        ? availableTermsForPayment.value.find((t: any) => t.id === paymentForm.term_id) ?? null
        : null
);

const projectedRemainingBalance = computed(() => {
    const amt = parseFloat(paymentForm.amount) || 0;
    return Math.max(0, remainingBalance.value - amt);
});

const paymentAmountError = computed(() => {
    const amount = parseFloat(paymentForm.amount) || 0;
    if (amount <= 0 && paymentForm.amount) return 'Amount must be greater than zero';
    if (amount > remainingBalance.value) return `Amount cannot exceed remaining balance of ${formatCurrency(remainingBalance.value)}`;
    if (selectedTerm.value && amount > parseFloat(String(selectedTerm.value.balance)))
        return `Amount cannot exceed selected term balance of ${formatCurrency(parseFloat(String(selectedTerm.value.balance)))}`;
    return '';
});

const canSubmitPayment = computed(() => {
    const amount = parseFloat(paymentForm.amount) || 0;
    return (
        amount > 0 &&
        amount <= remainingBalance.value &&
        paymentForm.term_id !== null &&
        !paymentForm.processing &&
        availableTermsForPayment.value.length > 0
    );
});

const getTermStatusConfig = (status: string) => {
    const map: Record<string, { bg: string; text: string; label: string }> = {
        pending: { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'Unpaid' },
        partial: { bg: 'bg-orange-100', text: 'text-orange-800', label: 'Partial' },
        paid:    { bg: 'bg-green-100',  text: 'text-green-800',  label: 'Paid'   },
        overdue: { bg: 'bg-red-100',    text: 'text-red-800',    label: 'Overdue' },
    };
    return map[status] ?? { bg: 'bg-gray-100', text: 'text-gray-800', label: status };
};

watch(() => showPaymentDialog.value, (isOpen) => {
    if (isOpen && firstUnpaidTerm.value && !paymentForm.term_id) {
        paymentForm.term_id = firstUnpaidTerm.value.id;
    }
});

const submitPayment = () => {
    if (!canSubmitPayment.value) {
        if (!paymentForm.term_id) paymentForm.setError('term_id', 'Please select a payment term');
        if (!paymentForm.amount) paymentForm.setError('amount', 'Please enter an amount');
        return;
    }
    paymentForm.post(route('student-fees.payments.store', props.student.id), {
        preserveScroll: true,
        onSuccess: () => { showPaymentDialog.value = false; paymentForm.reset(); paymentForm.clearErrors(); },
        onError: (errors) => console.error('Payment errors:', errors),
    });
};

// ─── Helpers ──────────────────────────────────────────────────────────────────
const formatCurrency = (amount: number) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);

const formatDate = (date: string) =>
    new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

const formatDateShort = (date: string) =>
    new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

const getStudentStatusColor = (status: string) => {
    const map: Record<string, string> = {
        active:    'bg-green-100 text-green-800',
        graduated: 'bg-blue-100 text-blue-800',
        dropped:   'bg-red-100 text-red-800',
    };
    return map[status] ?? 'bg-gray-100 text-gray-800';
};
</script>

<template>
    <Head :title="`Fee Details — ${student.name}`" />

    <AppLayout>
        <div class="space-y-6 max-w-6xl mx-auto p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- ── Header ── -->
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-4">
                    <Link :href="route('student-fees.index')">
                        <Button variant="outline" size="sm">
                            <ArrowLeft class="w-4 h-4 mr-2" />
                            Back
                        </Button>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ student.name }}</h1>
                        <p class="text-sm text-gray-500 mt-0.5">{{ student.student_id }} · {{ student.course }} · {{ student.year_level }}</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('student-fees.export-pdf', student.id)" target="_blank">
                        <Button variant="outline" size="sm">
                            <Download class="w-4 h-4 mr-2" />
                            Export PDF
                        </Button>
                    </Link>
                    <Dialog v-model:open="showPaymentDialog">
                        <DialogTrigger as-child>
                            <Button size="sm">
                                <Plus class="w-4 h-4 mr-2" />
                                Record Payment
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Record New Payment</DialogTitle>
                                <DialogDescription>
                                    <div class="space-y-1">
                                        <p>Add a payment for {{ student.name }}</p>
                                        <p class="text-base font-semibold text-slate-900">
                                            Current Balance: {{ formatCurrency(remainingBalance) }}
                                        </p>
                                    </div>
                                </DialogDescription>
                            </DialogHeader>
                            <form @submit.prevent="submitPayment" class="space-y-4">
                                <!-- Amount -->
                                <div class="space-y-2">
                                    <Label for="amount">Amount *</Label>
                                    <Input id="amount" v-model="paymentForm.amount" type="number" step="0.01"
                                        min="0.01" :max="remainingBalance" required placeholder="0.00"
                                        :class="{ 'border-red-500': paymentAmountError }" />
                                    <p v-if="paymentAmountError" class="text-sm text-red-500 font-medium">{{ paymentAmountError }}</p>
                                    <p v-else class="text-xs text-gray-500">Maximum: {{ formatCurrency(remainingBalance) }}</p>
                                    <p v-if="paymentForm.errors.amount" class="text-sm text-red-500">{{ paymentForm.errors.amount }}</p>
                                </div>
                                <!-- Payment Method -->
                                <div class="space-y-2">
                                    <Label>Payment Method</Label>
                                    <div class="px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                                        <p class="text-gray-700 font-medium">Cash</p>
                                        <p class="text-xs text-gray-500">On-campus, in-person payment</p>
                                    </div>
                                </div>
                                <!-- Payment Date -->
                                <div class="space-y-2">
                                    <Label for="payment_date">Payment Date *</Label>
                                    <Input id="payment_date" v-model="paymentForm.payment_date" type="date" required />
                                    <p v-if="paymentForm.errors.payment_date" class="text-sm text-red-500">{{ paymentForm.errors.payment_date }}</p>
                                </div>
                                <!-- Select Term -->
                                <div class="space-y-2">
                                    <Label for="term_id">Select Term <span class="text-xs text-red-500">*</span></Label>
                                    <select id="term_id" v-model.number="paymentForm.term_id" required
                                        :disabled="remainingBalance <= 0 || availableTermsForPayment.length === 0"
                                        class="w-full px-4 py-2 border rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed text-sm">
                                        <option :value="null">-- Choose a payment term --</option>
                                        <option v-for="term in availableTermsForPayment" :key="term.id"
                                            :value="term.id" :disabled="!term.isSelectable">
                                            {{ term.term_name }} — {{ formatCurrency(term.balance) }}
                                            {{ !term.isSelectable ? '(Not yet available)' : '' }}
                                        </option>
                                    </select>
                                    <p class="text-xs text-gray-500">Only the first unpaid term can be selected. Overpayments carry over.</p>
                                    <p v-if="paymentForm.errors.term_id" class="text-red-600 text-sm">{{ paymentForm.errors.term_id }}</p>
                                </div>
                                <!-- Selected Term Details -->
                                <div v-if="selectedTerm" class="p-3 bg-blue-50 rounded-lg border border-blue-200 text-sm">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="text-xs text-gray-500 font-medium uppercase">Selected Term</p>
                                            <p class="font-semibold text-gray-900 mt-0.5">{{ selectedTerm.term_name }}</p>
                                        </div>
                                        <span :class="['text-xs px-2 py-0.5 rounded font-medium', getTermStatusConfig(selectedTerm.status).bg, getTermStatusConfig(selectedTerm.status).text]">
                                            {{ getTermStatusConfig(selectedTerm.status).label }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 mt-2 pt-2 border-t border-blue-200">
                                        <div>
                                            <p class="text-xs text-gray-500">Term Balance</p>
                                            <p class="font-semibold text-blue-700">{{ formatCurrency(selectedTerm.balance) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Original Amount</p>
                                            <p class="font-semibold text-gray-700">{{ formatCurrency(selectedTerm.amount) }}</p>
                                        </div>
                                    </div>
                                </div>
                                <!-- Payment Preview -->
                                <div v-if="parseFloat(paymentForm.amount) > 0" class="p-3 bg-green-50 rounded-lg border border-green-200 text-sm">
                                    <p class="text-xs text-gray-500 font-medium uppercase mb-2">Payment Preview</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <p class="text-xs text-gray-500">Current Balance</p>
                                            <p class="font-semibold text-red-600">{{ formatCurrency(remainingBalance) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Payment Amount</p>
                                            <p class="font-semibold text-blue-600">− {{ formatCurrency(parseFloat(paymentForm.amount)) }}</p>
                                        </div>
                                        <div class="col-span-2 pt-2 border-t border-green-200 flex justify-between">
                                            <span class="text-xs text-gray-500 font-medium">Balance After Payment</span>
                                            <span :class="['font-bold', projectedRemainingBalance > 0 ? 'text-red-600' : 'text-green-600']">
                                                {{ formatCurrency(projectedRemainingBalance) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <DialogFooter>
                                    <Button type="button" variant="outline" @click="showPaymentDialog = false">Cancel</Button>
                                    <Button type="submit" :disabled="!canSubmitPayment"
                                        :class="{ 'opacity-50 cursor-not-allowed': !canSubmitPayment }">
                                        <span v-if="paymentForm.processing">Recording…</span>
                                        <span v-else-if="!canSubmitPayment && remainingBalance <= 0">No Balance to Pay</span>
                                        <span v-else-if="!canSubmitPayment && availableTermsForPayment.length === 0">No Unpaid Terms</span>
                                        <span v-else>Record Payment</span>
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <!-- ── Personal Info ── -->
            <Card>
                <CardHeader>
                    <CardTitle>Personal Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <Label class="text-xs text-gray-500">Full Name</Label>
                            <p class="font-medium mt-0.5">{{ student.name }}</p>
                        </div>
                        <div>
                            <Label class="text-xs text-gray-500">Email</Label>
                            <p class="font-medium mt-0.5">{{ student.email }}</p>
                        </div>
                        <div>
                            <Label class="text-xs text-gray-500">Birthday</Label>
                            <p class="font-medium mt-0.5">{{ student.birthday ? formatDate(student.birthday) : 'N/A' }}</p>
                        </div>
                        <div>
                            <Label class="text-xs text-gray-500">Phone</Label>
                            <p class="font-medium mt-0.5">{{ student.phone || 'N/A' }}</p>
                        </div>
                        <div>
                            <Label class="text-xs text-gray-500">Student ID</Label>
                            <p class="font-medium mt-0.5">{{ student.student_id }}</p>
                        </div>
                        <div>
                            <Label class="text-xs text-gray-500">Course</Label>
                            <p class="font-medium mt-0.5">{{ student.course }}</p>
                        </div>
                        <div>
                            <Label class="text-xs text-gray-500">Year Level</Label>
                            <p class="font-medium mt-0.5">{{ student.year_level }}</p>
                        </div>
                        <div>
                            <Label class="text-xs text-gray-500">Status</Label>
                            <span class="inline-block mt-0.5 px-2 py-0.5 text-xs font-semibold rounded-full"
                                :class="getStudentStatusColor(student.status)">
                                {{ student.status }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ── Fee Breakdown ── -->
            <Card>
                <CardHeader>
                    <CardTitle>Fee Breakdown</CardTitle>
                    <CardDescription>
                        Assessment for {{ assessment?.year_level }} — {{ assessment?.semester }} {{ assessment?.school_year }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-5">

                    <!-- Individual fee line items -->
                    <div class="space-y-2">
                        <div v-for="item in feeLineItems" :key="item.category"
                            class="flex items-center justify-between py-2.5 px-4 rounded-lg bg-gray-50 border border-gray-100">
                            <div class="flex items-center gap-2">
                                <Receipt class="w-4 h-4 text-gray-400" />
                                <span class="text-sm font-medium text-gray-700">{{ item.displayLabel }}</span>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(item.total) }}</span>
                        </div>
                        <div v-if="feeLineItems.length === 0" class="text-sm text-gray-400 text-center py-4">
                            No fee items on record
                        </div>
                    </div>

                    <!-- Divider + Total -->
                    <div class="flex justify-between items-center pt-2 border-t-2 border-gray-200 px-1">
                        <span class="font-semibold text-gray-700">Total Assessment</span>
                        <span class="text-lg font-bold text-gray-900">{{ formatCurrency(totalAssessment) }}</span>
                    </div>

                    <!-- Progress bar -->
                    <div class="space-y-1 px-1">
                        <div class="flex justify-between text-xs text-gray-500">
                            <span>Payment Progress</span>
                            <span>{{ totalAssessment > 0 ? Math.round((totalPaid / totalAssessment) * 100) : 0 }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                            <div class="h-2.5 rounded-full transition-all duration-500"
                                :class="paymentTimingStatus === 'behind' ? 'bg-red-500' : paymentTimingStatus === 'on_track' ? 'bg-blue-500' : 'bg-green-500'"
                                :style="{ width: totalAssessment > 0 ? `${Math.min(100, (totalPaid / totalAssessment) * 100)}%` : '0%' }">
                            </div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 pt-0.5">
                            <span>Paid: <strong class="text-green-600">{{ formatCurrency(totalPaid) }}</strong></span>
                            <span>Remaining: <strong :class="paymentTimingStatus === 'paid' ? 'text-green-600' : 'text-red-600'">{{ formatCurrency(remainingBalance) }}</strong></span>
                        </div>
                    </div>

                    <!-- Balance status card -->
                    <div :class="['rounded-xl border-2 p-4 flex items-center gap-4 mt-2', balanceCardConfig.bg]">
                        <div :class="['p-3 rounded-xl', balanceCardConfig.iconBg]">
                            <component :is="balanceCardConfig.icon" :class="['w-6 h-6', balanceCardConfig.iconColor]" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm" :class="balanceCardConfig.labelColor">Remaining Balance</p>
                                <span class="text-xs font-bold px-2 py-0.5 rounded-full" :class="balanceCardConfig.badge.cls">
                                    {{ balanceCardConfig.badge.label }}
                                </span>
                            </div>
                            <p class="text-3xl font-extrabold mt-0.5" :class="balanceCardConfig.amountColor">
                                {{ formatCurrency(remainingBalance) }}
                            </p>
                            <p v-if="assessment?.paymentTerms?.length" class="text-xs mt-1" :class="balanceCardConfig.labelColor">
                                {{ paidTermsCount }} of {{ allTermsSorted.length }} terms paid
                            </p>
                        </div>
                    </div>

                    <!-- Payment terms progress (pills) -->
                    <div v-if="allTermsSorted.length > 0" class="space-y-2 pt-1">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Payment Terms</p>
                        <div class="grid grid-cols-1 sm:grid-cols-5 gap-2">
                            <div v-for="term in allTermsSorted" :key="term.id"
                                :class="['rounded-lg border p-2.5 text-center text-xs transition-all',
                                    term.status === 'paid'    ? 'bg-green-50 border-green-200' :
                                    term.status === 'partial' ? 'bg-orange-50 border-orange-200' :
                                    term.status === 'overdue' ? 'bg-red-100 border-red-300' :
                                                                'bg-gray-50 border-gray-200']">
                                <p class="font-semibold text-gray-700 truncate">{{ term.term_name }}</p>
                                <p class="font-bold mt-0.5"
                                    :class="term.status === 'paid' ? 'text-green-600' : term.status === 'overdue' ? 'text-red-600' : 'text-gray-800'">
                                    {{ formatCurrency(parseFloat(String(term.balance))) }}
                                </p>
                                <span :class="['inline-block mt-1 px-1.5 py-0.5 rounded-full font-medium',
                                    getTermStatusConfig(term.status).bg, getTermStatusConfig(term.status).text]">
                                    {{ getTermStatusConfig(term.status).label }}
                                </span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ── Payment History ── -->
            <Card>
                <CardHeader>
                    <CardTitle>Payment History</CardTitle>
                    <CardDescription>All recorded payments ({{ payments.length }} total)</CardDescription>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Method</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-if="payments.length === 0">
                                    <td colspan="6" class="px-6 py-10 text-center text-gray-400">
                                        <CreditCard class="w-8 h-8 mx-auto mb-2 opacity-30" />
                                        <p>No payment history found</p>
                                    </td>
                                </tr>
                                <tr v-for="payment in payments" :key="payment.id"
                                    class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-600">
                                        {{ formatDateShort(payment.paid_at) }}
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        <span class="font-mono text-xs text-gray-700">{{ payment.reference_number }}</span>
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800 font-medium capitalize">
                                            {{ payment.payment_method }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-600">{{ payment.description }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-semibold text-green-600">
                                        + {{ formatCurrency(payment.amount) }}
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap text-center">
                                        <span class="px-2 py-0.5 text-xs rounded-full font-semibold"
                                            :class="payment.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">
                                            {{ payment.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- ── Transaction History (styled like Transactions/Index) ── -->
            <div>
                <div class="flex items-center justify-between mb-3 px-1">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Transaction History</h2>
                        <p class="text-sm text-gray-500">All charges and payments grouped by term</p>
                    </div>
                </div>

                <div v-if="transactionsByTerm.length === 0" class="bg-white border rounded-xl p-10 text-center text-gray-400">
                    <AlertCircle class="w-8 h-8 mx-auto mb-2 opacity-30" />
                    <p>No transactions found</p>
                </div>

                <div v-for="group in transactionsByTerm" :key="group.key"
                    class="border rounded-xl shadow-sm bg-white overflow-hidden mb-4">

                    <!-- Term header (collapsible) -->
                    <div class="flex justify-between items-center p-5 cursor-pointer hover:bg-gray-50 transition-colors select-none"
                        @click="toggleTerm(group.key)">
                        <div>
                            <h3 class="font-bold text-lg text-gray-900">{{ group.key }}</h3>
                            <p class="text-sm text-gray-400 mt-0.5">
                                {{ group.transactions.length }} transaction{{ group.transactions.length !== 1 ? 's' : '' }}
                            </p>
                        </div>

                        <!-- Summary numbers -->
                        <div class="flex items-center gap-8 md:gap-12 text-right">
                            <div>
                                <p class="text-xs text-gray-400">Total Assessed</p>
                                <p class="font-bold text-red-600 text-sm">₱{{ new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(group.totalCharges) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Total Paid</p>
                                <p class="font-bold text-green-600 text-sm">₱{{ new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(group.totalPaid) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Balance</p>
                                <p class="font-bold text-sm" :class="group.balance > 0 ? 'text-red-600' : 'text-green-600'">
                                    ₱{{ new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(Math.abs(group.balance)) }}
                                </p>
                            </div>
                            <ChevronDown class="w-5 h-5 text-gray-400 transition-transform"
                                :class="{ 'rotate-180': expandedTerms[group.key] }" />
                        </div>
                    </div>

                    <!-- Expanded rows -->
                    <div v-if="expandedTerms[group.key]" class="border-t">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 text-gray-500 text-xs uppercase">
                                        <th class="px-4 py-3 font-semibold">Reference</th>
                                        <th class="px-4 py-3 font-semibold">Type</th>
                                        <th class="px-4 py-3 font-semibold">Category</th>
                                        <th class="px-4 py-3 font-semibold">Year & Semester</th>
                                        <th class="px-4 py-3 font-semibold">Amount</th>
                                        <th class="px-4 py-3 font-semibold">Status</th>
                                        <th class="px-4 py-3 font-semibold">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="t in group.transactions" :key="t.id"
                                        class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ t.reference }}</td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full"
                                                :class="t.kind === 'charge'
                                                    ? 'bg-red-100 text-red-800'
                                                    : 'bg-green-100 text-green-800'">
                                                {{ t.kind }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ t.type }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <div v-if="t.year || t.semester">
                                                <p class="font-medium text-gray-800">{{ t.year }}</p>
                                                <p class="text-xs text-gray-500">{{ t.semester }}</p>
                                            </div>
                                            <span v-else class="text-gray-400">—</span>
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-sm"
                                            :class="t.kind === 'charge' ? 'text-red-600' : 'text-green-600'">
                                            {{ t.kind === 'charge' ? '+' : '−' }}₱{{ new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(t.amount) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full"
                                                :class="{
                                                    'bg-green-100 text-green-800':  t.status === 'paid',
                                                    'bg-yellow-100 text-yellow-800': t.status === 'pending',
                                                    'bg-blue-100 text-blue-800':    t.status === 'awaiting_approval',
                                                    'bg-red-100 text-red-800':      t.status === 'failed',
                                                    'bg-gray-100 text-gray-700':    t.status === 'cancelled',
                                                }">
                                                {{ t.status === 'awaiting_approval' ? 'Awaiting Verification' : t.status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500">{{ formatDateShort(t.created_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>