<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { useDataFormatting } from '@/composables/useDataFormatting';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle, Clock, CreditCard, XCircle } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const { formatCurrency, formatDate, formatDateTime, getPaymentTermStatusConfig, getTransactionStatusConfig, getAssessmentStatusConfig } =
    useDataFormatting();

type Fee = {
    name: string;
    amount: number;
    category?: string;
};

type Transaction = {
    id: number;
    reference: string;
    type: string;
    kind: string;
    amount: number;
    status: string;
    created_at: string;
    fee?: {
        name: string;
        category: string;
    };
    meta?: {
        fee_name?: string;
        description?: string;
        assessment_id?: number;
        subject_code?: string;
        subject_name?: string;
    };
};

type Account = {
    id: number;
    balance: number;
    user_id: number;
};

type CurrentTerm = {
    year: number;
    semester: string;
};

type Assessment = {
    id: number;
    assessment_number: string;
    year_level: string;
    semester: string;
    school_year: string;
    tuition_fee: number;
    other_fees: number;
    total_assessment: number;
    status: string;
    created_at: string;
};

type PaymentTerm = {
    id: number;
    term_name: string;
    term_order: number;
    percentage: number;
    amount: number;
    balance: number;
    due_date: string;
    status: string;
    remarks: string | null;
    paid_date: string | null;
};

type Notification = {
    id: number;
    title: string;
    message: string;
    type?: string;
    target_role: string;
    user_id?: number | null;
    is_active: boolean;
    start_date?: string;
    end_date?: string;
    dismissed_at?: string | null;
    created_at: string;
};

const props = withDefaults(
    defineProps<{
        account: Account;
        transactions: Transaction[];
        fees: Fee[];
        currentTerm?: CurrentTerm;
        tab?: string;
        latestAssessment?: Assessment;
        paymentTerms?: PaymentTerm[];
        notifications?: Notification[];
        pendingApprovalPayments?: Array<{
            id: number;
            reference: string;
            amount: number;
            selected_term_id: number | null;
            term_name: string;
            created_at: string;
        }>;
    }>(),
    {
        currentTerm: () => ({
            year: new Date().getFullYear(),
            semester: '1st Sem',
        }),
        tab: 'fees',
        paymentTerms: () => [],
        notifications: () => [],
        pendingApprovalPayments: () => [],
    },
);

const breadcrumbs = [{ title: 'Dashboard', href: route('dashboard') }, { title: 'My Account' }];

// Get tab from URL if prop is not working
const getTabFromUrl = (): 'fees' | 'history' | 'payment' => {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');

    if (tab === 'payment') return 'payment';
    if (tab === 'history') return 'history';
    return 'fees';
};

// Set initial tab - try prop first, then URL
const getInitialTab = (): 'fees' | 'history' | 'payment' => {
    if (props.tab === 'payment' || props.tab === 'history') {
        return props.tab;
    }
    return getTabFromUrl();
};

const activeTab = ref<'fees' | 'history' | 'payment'>(getInitialTab());

// Watch for prop changes (in case of navigation)
watch(
    () => props.tab,
    (newTab) => {
        if (newTab === 'payment' || newTab === 'history') {
            activeTab.value = newTab;
        }
    },
);

// Get term_id from URL to pre-select
const getTermIdFromUrl = (): number | null => {
    const urlParams = new URLSearchParams(window.location.search);
    const termId = urlParams.get('term_id');
    return termId ? parseInt(termId, 10) : null;
};

// Ensure correct tab on mount and pre-select term if provided
const autoRefreshInterval = ref<ReturnType<typeof setInterval> | null>(null);

// Check if there are any awaiting_approval transactions
const hasAwaitingApprovals = computed(() => {
    return props.transactions.some((t) => t.status === 'awaiting_approval');
});

// Filter active, non-dismissed notifications
const activeNotifications = computed(() => {
    return props.notifications
        .filter((n) => !n.dismissed_at && !hiddenNotifications.value.has(n.id))
        .sort((a, b) => {
            // Sort payment_due to top
            if (a.type === 'payment_due' && b.type !== 'payment_due') return -1;
            if (a.type !== 'payment_due' && b.type === 'payment_due') return 1;
            return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
        });
});

// Track notifications that are auto-hidden
const hiddenNotifications = ref<Set<number>>(new Set());

// Dismiss notification (can be manual or auto)
const dismissNotification = (notificationId: number) => {
    hiddenNotifications.value.add(notificationId);
    router.post(route('notifications.dismiss', notificationId));
};

// Auto-dismiss notification after 5 seconds
const autoDismissNotification = (notificationId: number) => {
    setTimeout(() => {
        dismissNotification(notificationId);
    }, 5000);
};

onMounted(() => {
    const urlTab = getTabFromUrl();
    if (urlTab === 'payment' || urlTab === 'history') {
        activeTab.value = urlTab;
    }

    // Pre-select term if provided in URL
    const termId = getTermIdFromUrl();
    if (termId) {
        paymentForm.selected_term_id = termId;
    }

    // Auto-refresh page every 10 seconds if there are awaiting_approval payments
    // This ensures that when accounting approves a payment, the student sees it update automatically
    const startAutoRefresh = () => {
        if (autoRefreshInterval.value) {
            clearInterval(autoRefreshInterval.value);
        }

        if (hasAwaitingApprovals.value) {
            autoRefreshInterval.value = setInterval(() => {
                router.reload();
            }, 10000); // Refresh every 10 seconds
        }
    };

    // Start auto-refresh if needed
    startAutoRefresh();

    // Auto-dismiss notifications after 5 seconds
    props.notifications.forEach((notification) => {
        if (!notification.dismissed_at && !hiddenNotifications.value.has(notification.id)) {
            autoDismissNotification(notification.id);
        }
    });

    // Watch for changes in awaiting approvals status
    watch(hasAwaitingApprovals, (newVal) => {
        if (newVal) {
            startAutoRefresh();
        } else if (autoRefreshInterval.value) {
            clearInterval(autoRefreshInterval.value);
            autoRefreshInterval.value = null;
        }
    });
});

// Clean up interval on unmount
onUnmounted(() => {
    if (autoRefreshInterval.value) {
        clearInterval(autoRefreshInterval.value);
        autoRefreshInterval.value = null;
    }
});

const paymentForm = useForm({
    amount: 0,
    payment_method: 'gcash',
    paid_at: new Date().toISOString().split('T')[0],
    selected_term_id: null as number | null,
});

// Use latest assessment if available, otherwise calculate from fees
const totalAssessmentFee = computed(() => {
    if (props.latestAssessment) {
        return Number(props.latestAssessment.total_assessment);
    }
    return props.fees.reduce((sum, fee) => sum + Number(fee.amount), 0);
});

const totalPaid = computed(() => {
    return props.transactions.filter((t) => t.kind === 'payment' && t.status === 'paid').reduce((sum, t) => sum + Number(t.amount), 0);
});

// Calculate remaining balance from PAYMENT TERMS (not transactions)
// Payment terms are the source of truth for student balances
const remainingBalance = computed(() => {
    // If we have payment terms, calculate from them (most accurate)
    if (props.paymentTerms && props.paymentTerms.length > 0) {
        const outstandingBalance = props.paymentTerms.reduce((sum, term) => sum + Number(term.balance || 0), 0);
        return Math.max(0, Math.round(outstandingBalance * 100) / 100);
    }

    // Fallback to transaction-based calculation if no payment terms
    const txs = props.transactions ?? [];
    const charges = txs.filter((t) => t.kind === 'charge').reduce((sum, t) => sum + Number(t.amount || 0), 0);

    const payments = txs.filter((t) => t.kind === 'payment' && t.status === 'paid').reduce((sum, t) => sum + Number(t.amount || 0), 0);

    const diff = charges - payments;
    const rounded = Math.round(diff * 100) / 100;

    return rounded > 0 ? rounded : 0;
});

// Track pending approval payments grouped by term
const pendingPaymentsByTerm = computed(() => {
    const pending: Record<number, number> = {};
    props.pendingApprovalPayments?.forEach((payment) => {
        if (payment.selected_term_id !== null) {
            pending[payment.selected_term_id] = (pending[payment.selected_term_id] || 0) + payment.amount;
        }
    });
    return pending;
});

// Calculate the effective balance (actual balance minus pending payments)
const effectiveBalance = computed(() => {
    if (!props.paymentTerms || props.paymentTerms.length === 0) {
        return remainingBalance.value;
    }

    const totalBalance = props.paymentTerms.reduce((sum, term) => sum + Number(term.balance || 0), 0);
    const totalPending = props.pendingApprovalPayments?.reduce((sum, p) => sum + p.amount, 0) || 0;

    return Math.max(0, Math.round((totalBalance - totalPending) * 100) / 100);
});

// Check if there are any pending payments
const hasPendingPayments = computed(() => {
    return props.pendingApprovalPayments && props.pendingApprovalPayments.length > 0;
});

// Get pending payments for a specific term
const getPendingAmountForTerm = (termId: number): number => {
    return pendingPaymentsByTerm.value[termId] || 0;
};

const availableTermsForPayment = computed(() => {
    const unpaidTerms = props.paymentTerms?.filter((term) => term.balance > 0).sort((a, b) => a.term_order - b.term_order) || [];

    // Only the first unpaid term is selectable
    const firstUnpaidIndex = unpaidTerms.length > 0 ? 0 : -1;

    return unpaidTerms.map((term, index) => {
        const pendingAmount = getPendingAmountForTerm(term.id);
        const hasPending = pendingAmount > 0;

        return {
            id: term.id,
            label: term.term_name,
            term_name: term.term_name,
            value: term.id,
            balance: term.balance,
            amount: term.amount,
            due_date: term.due_date,
            status: term.status,
            isSelectable: index === firstUnpaidIndex && !hasPending,
            hasCarryover: term.remarks?.toLowerCase().includes('carried') || false,
            hasPending,
            pendingAmount,
        };
    });
});

// Get the first unpaid term ID (for "Pay Now" button visibility)
const firstUnpaidTermId = computed(() => {
    const unpaid = props.paymentTerms?.filter((t) => t.balance > 0).sort((a, b) => a.term_order - b.term_order);
    return unpaid?.[0]?.id ?? null;
});

const paymentHistory = computed(() => {
    return props.transactions.filter((t) => t.kind === 'payment').sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
});

const pendingCharges = computed(() => {
    return props.transactions.filter((t) => t.kind === 'charge' && t.status === 'pending');
});

const selectedTermInfo = computed(() => {
    if (!paymentForm.selected_term_id) {
        return null;
    }
    return availableTermsForPayment.value.find((term) => term.id === paymentForm.selected_term_id) || null;
});

const submitButtonMessage = computed(() => {
    if (!paymentForm.selected_term_id) {
        return 'Select a Payment Term';
    }

    const selectedTermHasPending = getPendingAmountForTerm(paymentForm.selected_term_id) > 0;
    if (selectedTermHasPending) {
        const pending = getPendingAmountForTerm(paymentForm.selected_term_id);
        return `⏳ Awaiting Approval (₱${formatCurrency(pending)}) — Cannot Submit`;
    }

    return 'Submit Payment';
});

const isPaymentDisabledReason = computed(() => {
    if (remainingBalance.value <= 0) {
        return 'This account has no outstanding balance.';
    }

    if (effectiveBalance.value <= 0 && hasPendingPayments.value) {
        return 'Your full outstanding balance is currently awaiting accounting approval.';
    }

    if (!paymentForm.selected_term_id) {
        return 'Select a term to proceed.';
    }

    const pending = getPendingAmountForTerm(paymentForm.selected_term_id);
    if (pending > 0) {
        return `₱${formatCurrency(pending)} for this term is awaiting accounting approval. Wait for approval before submitting another payment.`;
    }

    if (paymentForm.amount <= 0) {
        return 'Enter a payment amount.';
    }

    if (paymentForm.amount > effectiveBalance.value) {
        return 'Amount exceeds your available balance.';
    }

    return '';
});

const canSubmitPayment = computed(() => {
    // Cannot submit if pending payments exist for the selected term
    const selectedTermHasPending = paymentForm.selected_term_id !== null && getPendingAmountForTerm(paymentForm.selected_term_id) > 0;

    return (
        effectiveBalance.value > 0 &&
        paymentForm.amount > 0 &&
        paymentForm.amount <= effectiveBalance.value &&
        paymentForm.selected_term_id !== null &&
        availableTermsForPayment.value.length > 0 &&
        !selectedTermHasPending
    );
});

const isOverdue = (dueDate: string): boolean => {
    const due = new Date(dueDate);
    const today = new Date();

    // Normalize to midnight for date-only comparison
    due.setHours(0, 0, 0, 0);
    today.setHours(0, 0, 0, 0);

    // Overdue only if 1 day or more has passed (due date is before today)
    return due < today;
};

const submitPayment = () => {
    // Validate term selection
    if (!paymentForm.selected_term_id) {
        paymentForm.setError('selected_term_id', 'Please select a payment term');
        return;
    }

    // Validate amount
    if (paymentForm.amount <= 0) {
        paymentForm.setError('amount', 'Amount must be greater than zero');
        return;
    }

    // Use effectiveBalance (which subtracts pending payments) to prevent overpayment
    if (paymentForm.amount > effectiveBalance.value) {
        const pendingTotal = props.pendingApprovalPayments?.reduce((sum, p) => sum + p.amount, 0) || 0;
        if (pendingTotal > 0) {
            paymentForm.setError(
                'amount',
                `Amount cannot exceed available balance of ₱${formatCurrency(effectiveBalance.value)} (${formatCurrency(pendingTotal)} is awaiting approval)`,
            );
        } else {
            paymentForm.setError('amount', 'Amount cannot exceed remaining balance');
        }
        return;
    }

    paymentForm.post(route('account.pay-now'), {
        preserveScroll: true,
        onSuccess: () => {
            // Reset form after successful payment
            paymentForm.reset();
            paymentForm.amount = 0;
            paymentForm.payment_method = 'gcash';
            paymentForm.paid_at = new Date().toISOString().split('T')[0];
            paymentForm.selected_term_id = null;

            // Switch to payment history tab to see the new payment
            activeTab.value = 'history';
        },
        onError: (errors) => {
            // Form validation errors are automatically set on paymentForm
            // and displayed via paymentForm.errors properties
            console.error('Payment submission failed:', errors);
        },
    });
};
</script>

<template>
    <AppLayout>
        <Head title="My Account" />

        <div class="w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Active Notifications -->
            <div
                v-for="notification in activeNotifications"
                :key="notification.id"
                class="mb-4 flex items-start justify-between rounded-lg border p-4"
                :class="notification.type === 'payment_due' ? 'border-amber-200 bg-amber-50' : 'border-blue-200 bg-blue-50'"
            >
                <div class="flex-1">
                    <h3 class="mb-1 font-semibold" :class="notification.type === 'payment_due' ? 'text-amber-900' : 'text-blue-900'">
                        {{ notification.title }}
                    </h3>
                    <p :class="notification.type === 'payment_due' ? 'text-sm text-amber-800' : 'text-sm text-blue-800'">
                        {{ notification.message }}
                    </p>
                </div>
                <button @click="dismissNotification(notification.id)" class="ml-4 flex-shrink-0 text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <!-- Auto-Refresh Status Indicator -->
            <div v-if="hasAwaitingApprovals" class="mb-4 flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 p-3">
                <div class="flex items-center gap-2">
                    <div class="h-2 w-2 animate-pulse rounded-full bg-blue-500"></div>
                    <p class="text-sm text-blue-700">
                        <strong>Checking for updates...</strong> Your payment is awaiting verification. This page will update automatically.
                    </p>
                </div>
            </div>

            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold">My Account Overview</h1>
                <p v-if="currentTerm" class="mt-1 text-gray-600">{{ currentTerm.semester }} - {{ currentTerm.year }}-{{ currentTerm.year + 1 }}</p>
                <p v-if="latestAssessment" class="mt-1 text-sm text-gray-500">Assessment No: {{ latestAssessment.assessment_number }}</p>
            </div>

            <!-- Balance Cards -->
            <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-3">
                <!-- Total Assessment -->
                <div class="rounded-lg bg-white p-6 shadow-md transition-shadow hover:shadow-lg">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="rounded-lg bg-blue-100 p-3">
                            <CreditCard :size="24" class="text-blue-600" />
                        </div>
                    </div>
                    <h3 class="mb-2 text-sm font-medium text-gray-600">Total Assessment Fee</h3>
                    <p class="text-3xl font-bold text-blue-600">{{ formatCurrency(totalAssessmentFee) }}</p>
                    <p v-if="latestAssessment" class="mt-2 text-xs text-gray-500">
                        Tuition: {{ formatCurrency(latestAssessment.tuition_fee) }} • Other: {{ formatCurrency(latestAssessment.other_fees) }}
                    </p>
                </div>

                <!-- Total Paid -->
                <div class="rounded-lg bg-white p-6 shadow-md transition-shadow hover:shadow-lg">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="rounded-lg bg-green-100 p-3">
                            <CheckCircle :size="24" class="text-green-600" />
                        </div>
                    </div>
                    <h3 class="mb-2 text-sm font-medium text-gray-600">Total Paid</h3>
                    <p class="text-3xl font-bold text-green-600">{{ formatCurrency(totalPaid) }}</p>
                    <p class="mt-2 text-xs text-gray-500">{{ paymentHistory.length }} payment(s) made</p>
                </div>

                <!-- Current Balance -->
                <div class="rounded-lg bg-white p-6 shadow-md transition-shadow hover:shadow-lg">
                    <div class="mb-2 flex items-center justify-between">
                        <div :class="['rounded-lg p-3', remainingBalance > 0 ? 'bg-red-100' : 'bg-green-100']">
                            <component
                                :is="remainingBalance > 0 ? AlertCircle : CheckCircle"
                                :size="24"
                                :class="remainingBalance > 0 ? 'text-red-600' : 'text-green-600'"
                            />
                        </div>
                    </div>
                    <h3 class="mb-2 text-sm font-medium text-gray-600">Current Balance</h3>
                    <p class="text-3xl font-bold" :class="remainingBalance > 0 ? 'text-red-600' : 'text-green-600'">
                        {{ formatCurrency(remainingBalance) }}
                    </p>
                    <p class="mt-2 text-xs text-gray-500">
                        {{ remainingBalance > 0 ? 'Amount due' : 'Fully paid' }}
                    </p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="mb-6 rounded-lg bg-white shadow-md">
                <div class="border-b">
                    <nav class="flex gap-4 px-6">
                        <button
                            @click="activeTab = 'fees'"
                            :class="[
                                'border-b-2 px-2 py-4 text-sm font-medium transition-colors',
                                activeTab === 'fees' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700',
                            ]"
                        >
                            Fees & Assessment
                        </button>
                        <button
                            @click="activeTab = 'history'"
                            :class="[
                                'border-b-2 px-2 py-4 text-sm font-medium transition-colors',
                                activeTab === 'history' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700',
                            ]"
                        >
                            Payment History
                        </button>
                        <button
                            @click="activeTab = 'payment'"
                            :class="[
                                'border-b-2 px-2 py-4 text-sm font-medium transition-colors',
                                activeTab === 'payment' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700',
                            ]"
                        >
                            Make Payment
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="p-6">
                    <!-- Fees Tab -->
                    <div v-if="activeTab === 'fees'">
                        <h2 class="mb-4 text-lg font-semibold">CURRENT ASSESSMENT</h2>

                        <!-- Assessment Info Banner -->
                        <div v-if="latestAssessment" class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4">
                            <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                                <div>
                                    <span class="text-gray-600">Assessment No:</span>
                                    <p class="font-semibold">{{ latestAssessment.assessment_number }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-600">School Year:</span>
                                    <p class="font-semibold">{{ latestAssessment.school_year }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-600">Semester:</span>
                                    <p class="font-semibold">{{ latestAssessment.semester }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-600">Status:</span>
                                    <span
                                        v-if="latestAssessment"
                                        :class="[
                                            'ml-2 inline-block rounded-full px-2 py-1 text-xs font-semibold',
                                            getAssessmentStatusConfig(latestAssessment.status).bgClass,
                                            getAssessmentStatusConfig(latestAssessment.status).textClass,
                                        ]"
                                    >
                                        {{ getAssessmentStatusConfig(latestAssessment.status).label }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Terms Table -->
                        <div v-if="paymentTerms && paymentTerms.length" class="mt-8 border-t pt-6">
                            <h3 class="text-md mb-4 flex items-center gap-2 font-semibold text-gray-800">
                                <Clock :size="20" />
                                PAYMENT TERMS
                            </h3>
                            <div class="overflow-x-auto">
                                <table class="w-full border-collapse">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Payment Term</th>
                                            <th class="px-4 py-3 text-right font-semibold text-gray-700">Original Amount</th>
                                            <th class="px-4 py-3 text-right font-semibold text-gray-700">Current Balance</th>
                                            <th class="px-4 py-3 text-right font-semibold text-gray-700">Due Date</th>
                                            <th class="px-4 py-3 text-center font-semibold text-gray-700">Status</th>
                                            <th class="px-4 py-3 text-center font-semibold text-gray-700">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="term in paymentTerms"
                                            :key="term.id"
                                            class="border-b border-gray-200 transition-colors hover:bg-gray-50"
                                        >
                                            <td class="px-4 py-3 text-gray-900">{{ term.term_name || 'N/A' }}</td>
                                            <td class="px-4 py-3 text-right text-gray-700">{{ formatCurrency(term.amount) }}</td>
                                            <td
                                                class="px-4 py-3 text-right font-medium"
                                                :class="term.balance > 0 ? 'text-red-600' : 'text-green-600'"
                                            >
                                                {{ formatCurrency(term.balance) }}
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <p class="text-sm text-gray-700">{{ term.due_date ? formatDate(term.due_date) : '-' }}</p>
                                                <p
                                                    v-if="term.due_date && isOverdue(term.due_date) && term.status !== 'paid'"
                                                    class="mt-1 text-xs text-red-600"
                                                >
                                                    ⚠️ Overdue
                                                </p>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span
                                                    :class="[
                                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                                        getPaymentTermStatusConfig(term.status).bgClass,
                                                        getPaymentTermStatusConfig(term.status).textClass,
                                                    ]"
                                                >
                                                    {{ getPaymentTermStatusConfig(term.status).label }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <div class="flex justify-center gap-2">
                                                    <button
                                                        class="rounded bg-gray-200 px-2 py-1 text-xs text-gray-700 transition-colors hover:bg-gray-300"
                                                        title="View details"
                                                    >
                                                        View
                                                    </button>
                                                    <button
                                                        v-if="term.balance > 0 && term.id === firstUnpaidTermId"
                                                        @click="
                                                            () => {
                                                                paymentForm.selected_term_id = term.id;
                                                                activeTab = 'payment';
                                                            }
                                                        "
                                                        class="rounded bg-indigo-600 px-2 py-1 text-xs text-white transition-colors hover:bg-indigo-700"
                                                        title="Make payment for this term"
                                                    >
                                                        Pay Now
                                                    </button>
                                                    <button
                                                        v-else-if="term.balance > 0"
                                                        class="cursor-not-allowed rounded bg-gray-200 px-2 py-1 text-xs text-gray-500"
                                                        disabled
                                                        title="Pay earlier terms first"
                                                    >
                                                        Locked
                                                    </button>
                                                    <button
                                                        v-else
                                                        class="cursor-not-allowed rounded bg-gray-100 px-2 py-1 text-xs text-gray-400"
                                                        disabled
                                                    >
                                                        Paid
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Payment History Tab -->
                    <div v-if="activeTab === 'history'">
                        <h2 class="mb-4 text-lg font-semibold">Payment History</h2>

                        <!-- Pending Payments Section -->
                        <div v-if="hasPendingPayments" class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4">
                            <div class="mb-3 flex items-center gap-2">
                                <Clock :size="18" class="text-amber-600" />
                                <h3 class="font-semibold text-amber-900">Pending Approval ({{ pendingApprovalPayments.length }})</h3>
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="payment in pendingApprovalPayments"
                                    :key="payment.id"
                                    class="flex items-center justify-between rounded border border-amber-200 bg-white p-3"
                                >
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ payment.term_name }}</p>
                                        <p class="text-xs text-gray-600">{{ payment.reference }} • {{ formatDate(payment.created_at) }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-amber-700">₱{{ formatCurrency(payment.amount) }}</p>
                                        <p class="text-xs text-amber-600">⏳ Awaiting Approval</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="paymentHistory.length" class="space-y-3">
                            <div
                                v-for="payment in paymentHistory"
                                :key="payment.id"
                                class="flex items-center justify-between rounded-lg border p-4 transition-colors hover:bg-gray-50"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="rounded bg-green-100 p-2">
                                        <CheckCircle :size="20" class="text-green-600" />
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">{{ payment.meta?.description || payment.type || 'Payment' }}</p>
                                        <p class="text-sm text-gray-600">
                                            {{ payment.created_at ? formatDate(payment.created_at) : '-' }}
                                        </p>
                                        <p class="text-xs text-gray-500">{{ payment.reference || 'N/A' }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-semibold text-green-600">{{ formatCurrency(payment.amount) }}</p>
                                    <span
                                        :class="[
                                            'inline-block rounded px-2 py-1 text-xs font-medium',
                                            getTransactionStatusConfig(payment.status).bgClass,
                                            getTransactionStatusConfig(payment.status).textClass,
                                        ]"
                                    >
                                        {{ getTransactionStatusConfig(payment.status).label }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="!hasPendingPayments" class="py-12 text-center">
                            <XCircle :size="48" class="mx-auto mb-3 text-gray-400" />
                            <p class="text-gray-500">No payment history yet</p>
                            <p class="mt-1 text-sm text-gray-400">Your payments will appear here after you make them</p>
                        </div>
                    </div>

                    <!-- Payment Form Tab -->
                    <div v-if="activeTab === 'payment'">
                        <h2 class="mb-6 text-2xl font-bold">Add New Payment</h2>

                        <!-- Pending Payment Warning Banner -->
                        <div v-if="hasPendingPayments" class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4">
                            <div class="flex items-start gap-3">
                                <AlertCircle :size="20" class="mt-0.5 flex-shrink-0 text-amber-600" />
                                <div class="flex-1">
                                    <p class="mb-2 font-semibold text-amber-900">⏳ Pending Payment(s) Awaiting Approval</p>
                                    <div class="space-y-1 text-sm text-amber-800">
                                        <div v-for="payment in pendingApprovalPayments" :key="payment.id" class="flex justify-between">
                                            <span>{{ payment.term_name }} ({{ payment.reference }})</span>
                                            <span class="font-semibold">₱{{ formatCurrency(payment.amount) }}</span>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs text-amber-700 italic">
                                        Please wait for accounting to verify and approve your pending payment(s) before submitting another payment for
                                        the same term.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- No Balance Message -->
                        <div v-if="remainingBalance <= 0" class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4">
                            <div class="flex items-center gap-2">
                                <CheckCircle :size="20" class="text-green-600" />
                                <p class="font-medium text-green-800">You have no outstanding balance!</p>
                            </div>
                            <p class="mt-1 text-sm text-green-700">All fees have been paid in full.</p>
                        </div>

                        <form @submit.prevent="submitPayment">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <!-- Amount -->
                                <div>
                                    <label for="payment-amount" class="mb-1 block text-sm font-medium text-gray-700">Amount</label>
                                    <input
                                        id="payment-amount"
                                        v-model="paymentForm.amount"
                                        type="number"
                                        name="amount"
                                        step="0.01"
                                        min="0"
                                        :max="effectiveBalance"
                                        placeholder="0.00"
                                        required
                                        :disabled="effectiveBalance <= 0"
                                        class="w-full rounded-lg border px-4 py-2 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-100"
                                    />
                                    <p class="mt-1 text-xs text-gray-500">
                                        Maximum: {{ formatCurrency(effectiveBalance) }}
                                        <span v-if="hasPendingPayments" class="ml-1 text-amber-600"
                                            >({{ formatCurrency(remainingBalance - effectiveBalance) }} awaiting approval)</span
                                        >
                                    </p>
                                    <div v-if="paymentForm.errors.amount" class="mt-1 text-sm text-red-500">
                                        {{ paymentForm.errors.amount }}
                                    </div>
                                </div>

                                <!-- Payment Method -->
                                <div>
                                    <label for="payment-method" class="mb-1 block text-sm font-medium text-gray-700">Payment Method</label>
                                    <select
                                        id="payment-method"
                                        v-model="paymentForm.payment_method"
                                        name="payment_method"
                                        :disabled="effectiveBalance <= 0"
                                        class="w-full rounded-lg border px-4 py-2 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-100"
                                    >
                                        <option value="gcash">GCash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="credit_card">Credit Card</option>
                                        <option value="debit_card">Debit Card</option>
                                    </select>
                                    <div v-if="paymentForm.errors.payment_method" class="mt-1 text-sm text-red-500">
                                        {{ paymentForm.errors.payment_method }}
                                    </div>
                                </div>

                                <!-- Select Term (Required) -->
                                <div>
                                    <label for="payment-term" class="mb-1 block text-sm font-medium text-gray-700">
                                        Select Term
                                        <span class="text-xs text-red-500">*</span>
                                    </label>
                                    <select
                                        id="payment-term"
                                        v-model.number="paymentForm.selected_term_id"
                                        name="selected_term_id"
                                        required
                                        :disabled="effectiveBalance <= 0 || availableTermsForPayment.length === 0"
                                        class="w-full rounded-lg border px-4 py-2 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-100"
                                    >
                                        <option :value="null">-- Choose a payment term --</option>
                                        <option
                                            v-for="term in availableTermsForPayment"
                                            :key="term.id"
                                            :value="term.id"
                                            :disabled="!term.isSelectable"
                                        >
                                            {{ term.label
                                            }}{{
                                                term.hasPending
                                                    ? ` (⏳ Pending ₱${formatCurrency(term.pendingAmount)} approval)`
                                                    : ` - ₱${formatCurrency(term.balance)}`
                                            }}{{ !term.isSelectable && !term.hasPending ? ' (Not yet available)' : '' }}
                                        </option>
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Only the first unpaid term can be selected. Overpayments will carry over to the next term.
                                    </p>
                                    <div v-if="paymentForm.errors.selected_term_id" class="mt-1 text-sm text-red-500">
                                        {{ paymentForm.errors.selected_term_id }}
                                    </div>
                                </div>

                                <!-- Payment Date -->
                                <div>
                                    <label for="payment-date" class="mb-1 block text-sm font-medium text-gray-700">Payment Date</label>
                                    <input
                                        id="payment-date"
                                        v-model="paymentForm.paid_at"
                                        type="date"
                                        name="paid_at"
                                        required
                                        :disabled="effectiveBalance <= 0"
                                        class="w-full rounded-lg border px-4 py-2 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-100"
                                    />
                                    <div v-if="paymentForm.errors.paid_at" class="mt-1 text-sm text-red-500">
                                        {{ paymentForm.errors.paid_at }}
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="md:col-span-2">
                                    <button
                                        type="submit"
                                        class="w-full rounded-lg bg-indigo-600 px-5 py-3 font-medium text-white shadow transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-400 disabled:opacity-50"
                                        :disabled="!canSubmitPayment || paymentForm.processing"
                                        :title="isPaymentDisabledReason"
                                    >
                                        <span v-if="paymentForm.processing">Processing...</span>
                                        <span v-else-if="remainingBalance <= 0">No Balance to Pay</span>
                                        <span v-else-if="effectiveBalance <= 0 && hasPendingPayments">Payment Awaiting Approval</span>
                                        <span v-else-if="paymentForm.selected_term_id && getPendingAmountForTerm(paymentForm.selected_term_id) > 0">
                                            ⏳ Awaiting Approval for {{ selectedTermInfo?.term_name }}
                                        </span>
                                        <span v-else>{{ submitButtonMessage }}</span>
                                    </button>
                                    <p v-if="isPaymentDisabledReason" class="mt-2 text-xs text-amber-700">
                                        {{ isPaymentDisabledReason }}
                                    </p>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
