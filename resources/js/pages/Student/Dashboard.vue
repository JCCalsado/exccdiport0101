<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { useDataFormatting } from '@/composables/useDataFormatting';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { AlertCircle, Bell, CheckCircle, Clock, CreditCard, FileText, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const { formatCurrency, formatDate, getTransactionStatusConfig, formatTransactionType } = useDataFormatting();

type Notification = {
    id: number;
    title: string;
    message: string;
    start_date: string | null;
    end_date: string | null;
    target_role: string;
};

type Account = {
    balance: number;
};

type RecentTransaction = {
    id: number;
    reference: string;
    type: string;
    amount: number;
    status: string;
    created_at: string;
    kind?: string;
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

type Assessment = {
    id: number;
    assessment_number: string;
    total_assessment: number;
    status: string;
    created_at: string;
};

type PaymentReminder = {
    id: number;
    type: string;
    message: string;
    outstanding_balance: number;
    status: string;
    read_at: string | null;
    sent_at: string;
    trigger_reason: string;
};

const props = defineProps<{
    account: Account;
    notifications: Notification[];
    recentTransactions: RecentTransaction[];
    paymentTerms?: PaymentTerm[];
    latestAssessment?: Assessment | null;
    paymentReminders?: PaymentReminder[];
    unreadReminderCount?: number;
    stats: {
        total_fees: number;
        total_paid: number;
        remaining_balance: number;
        pending_charges_count: number;
    };
}>();

const breadcrumbs = [{ title: 'Dashboard', href: route('dashboard') }, { title: 'Student Dashboard' }];

// ============================================================================
// DATA NORMALIZATION & VALIDATION LAYER
// ============================================================================

/**
 * Normalize and validate financial stats from props
 * Ensures all values are valid, non-negative numbers
 * Uses payment terms for remaining balance (most accurate)
 */
const normalizedStats = computed(() => {
    const safeNumber = (value: any): number => {
        // Handle null, undefined
        if (value === null || value === undefined) return 0;

        // Convert to number
        const num = Number(value);

        // Handle NaN, Infinity
        if (!isFinite(num)) return 0;

        // Prevent negative values in financial context
        return Math.max(0, num);
    };

    // Total fees: use assessment total if available (matches AccountOverview)
    const totalFees = props.latestAssessment ? safeNumber(props.latestAssessment.total_assessment) : safeNumber(props.stats?.total_fees);

    // Calculate remaining balance from payment terms if available (most accurate)
    // This matches the AccountOverview calculation logic
    let remainingBalance = safeNumber(props.stats?.remaining_balance);

    if (props.paymentTerms && props.paymentTerms.length > 0) {
        // Sum all balances from payment terms - they are the source of truth
        remainingBalance = safeNumber(props.paymentTerms.reduce((sum, term) => sum + (term.balance || 0), 0));
    }

    return {
        total_fees: totalFees,
        total_paid: safeNumber(props.stats?.total_paid),
        remaining_balance: remainingBalance,
        pending_charges_count: Math.floor(Math.max(0, safeNumber(props.stats?.pending_charges_count))),
    };
});

/**
 * Calculate payment percentage with safe division
 * Result is capped at 100% and protected from division errors
 */
const getPaymentPercentage = computed(() => {
    // Guard against division by zero
    if (normalizedStats.value.total_fees === 0) return 0;

    const percentage = (normalizedStats.value.total_paid / normalizedStats.value.total_fees) * 100;

    // Round to nearest integer and cap at 100%
    return Math.min(100, Math.round(percentage));
});

/**
 * Validate financial consistency
 * Checks if data makes mathematical sense
 */
const financialDataIsConsistent = computed(() => {
    const { total_fees, total_paid, remaining_balance } = normalizedStats.value;

    // If we have payment terms, validate against their sum
    if (props.paymentTerms && props.paymentTerms.length > 0) {
        const paymentTermsBalance = props.paymentTerms.reduce((sum, term) => sum + (term.balance || 0), 0);
        const tolerance = 0.01; // Allow 1 cent difference for rounding
        return Math.abs(remaining_balance - paymentTermsBalance) < tolerance;
    }

    // Fallback: Check if balance equals fees minus paid
    const expectedRemaining = Math.max(0, total_fees - total_paid);
    const tolerance = 0.01; // Allow 1 cent difference for rounding

    return Math.abs(remaining_balance - expectedRemaining) < tolerance;
});

/**
 * Determine financial state for messaging and styling
 */
const paymentState = computed<'fully_paid' | 'in_progress' | 'nearly_due' | 'attention_needed'>(() => {
    const balance = normalizedStats.value.remaining_balance;
    const percentage = getPaymentPercentage.value;

    if (balance === 0) return 'fully_paid';
    if (percentage >= 75) return 'nearly_due';
    if (percentage >= 50) return 'in_progress';
    return 'attention_needed';
});

/**
 * Pending charges context
 * Provides clear, accurate information about pending items
 */
const pendingChargesInfo = computed(() => {
    const count = normalizedStats.value.pending_charges_count;

    return {
        count,
        label: count === 0 ? 'No Pending Charges' : count === 1 ? '1 Pending Charge' : `${count} Pending Charges`,
        hasWarning: count > 0,
        description: count === 0 ? 'All charges are processed' : 'Charges awaiting processing',
    };
});

/**
 * Check if there are any awaiting_approval transactions
 * When a payment is awaiting approval, students should see a visual indicator
 */
const hasAwaitingApprovals = computed(() => {
    return props.recentTransactions.some((t) => t.status === 'awaiting_approval');
});

/**
 * Get unpaid payment terms (terms that still have a balance)
 * Returns terms ordered by term_order for display
 */
const unpaidTerms = computed(() => {
    if (!props.paymentTerms || props.paymentTerms.length === 0) {
        return [];
    }

    return props.paymentTerms.filter((term) => term.balance > 0).sort((a, b) => a.term_order - b.term_order);
});

/**
 * Determine due date color based on proximity to due date
 * Red if: 1 week before due date OR after 1 day past due date
 * Amber if: due date is approaching (2-7 days)
 * Green otherwise (not yet due or recently paid)
 */
const getDueDateColor = (dueDate: string): 'red' | 'amber' | 'green' => {
    const now = new Date();
    const due = new Date(dueDate);

    // Calculate days difference
    const diffTime = due.getTime() - now.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    // More than 1 day past due = red
    if (diffDays < -1) return 'red';

    // 1 week before due date or after 1 day past due = red
    if (diffDays <= 7 && diffDays >= -1) return 'red';

    // 2 weeks before due date = amber
    if (diffDays <= 14) return 'amber';

    // Otherwise green (not yet due)
    return 'green';
};

/**
 * Get the first unpaid term (next payment due)
 * This is the term that requires immediate attention
 */
const nextPaymentDue = computed(() => {
    if (unpaidTerms.value.length === 0) {
        return null;
    }

    const term = unpaidTerms.value[0];
    const dueColor = getDueDateColor(term.due_date);
    const daysUntilDue = Math.ceil((new Date(term.due_date).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24));

    return {
        ...term,
        dueColor,
        daysUntilDue,
        formattedDueDate: formatDate(term.due_date),
        isDueOrOverdue: daysUntilDue <= 7,
    };
});

/**
 * Get remaining unpaid terms (for pending charges display)
 */
const remainingUnpaidTerms = computed(() => {
    if (unpaidTerms.value.length <= 1) {
        return [];
    }

    return unpaidTerms.value.slice(1).map((term) => ({
        ...term,
        formattedDueDate: formatDate(term.due_date),
    }));
});

const activeNotifications = computed(() => {
    const now = new Date();
    return props.notifications.filter((n) => {
        if (!n.start_date) return true;
        const startDate = new Date(n.start_date);
        const endDate = n.end_date ? new Date(n.end_date) : null;
        return startDate <= now && (!endDate || endDate >= now);
    });
});

/**
 * Track whether to show all notifications or just the first 3
 */
const showAllNotifications = ref(false);

/**
 * Show only the first 3 notifications, or all if showAllNotifications is true
 */
const visibleNotifications = computed(() => {
    return showAllNotifications.value ? activeNotifications.value : activeNotifications.value.slice(0, 3);
});

/**
 * Check if there are more notifications than the 3 shown by default
 */
const hasMoreNotifications = computed(() => {
    return activeNotifications.value.length > 3;
});
</script>

<template>
    <AppLayout>
        <Head title="Student Dashboard" />

        <div class="w-full space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Welcome Header -->
            <div class="rounded-lg bg-gradient-to-r from-blue-600 to-blue-800 p-6 text-white shadow-lg">
                <h1 class="mb-2 text-3xl font-bold">Welcome Back, Student!</h1>
                <p class="text-blue-100">Here's your financial overview and important updates</p>
            </div>

            <!-- QUICK STATS + QUICK ACTIONS -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Quick Stats (2x2) -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:col-span-2">
                    <!-- Total Assessment Fee Card -->
                    <div class="flex items-center gap-4 rounded-lg border-l-4 border-blue-300 bg-white p-6 shadow-md">
                        <div class="rounded-lg bg-blue-100 p-3">
                            <FileText :size="24" class="text-blue-600" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Assessment Fee</p>
                            <p class="text-2xl font-bold text-blue-700">
                                {{ formatCurrency(normalizedStats.total_fees) }}
                            </p>
                        </div>
                    </div>

                    <!-- Total Paid Card -->
                    <div class="flex items-center gap-4 rounded-lg border-l-4 border-green-300 bg-white p-6 shadow-md">
                        <div class="rounded-lg bg-green-100 p-3">
                            <CheckCircle :size="24" class="text-green-600" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Paid</p>
                            <p class="text-2xl font-bold text-green-600">
                                {{ formatCurrency(normalizedStats.total_paid) }}
                            </p>
                        </div>
                    </div>

                    <!-- Remaining Balance Card -->
                    <div
                        :class="[
                            'flex items-center gap-4 rounded-lg border-l-4 bg-white p-6 shadow-md',
                            normalizedStats.remaining_balance > 0 ? 'border-red-300' : 'border-green-300',
                        ]"
                    >
                        <div :class="['rounded-lg p-3', normalizedStats.remaining_balance > 0 ? 'bg-red-100' : 'bg-green-100']">
                            <Wallet :size="24" :class="[normalizedStats.remaining_balance > 0 ? 'text-red-600' : 'text-green-600']" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Remaining Balance</p>
                            <p :class="['text-2xl font-bold', normalizedStats.remaining_balance > 0 ? 'text-red-600' : 'text-green-600']">
                                {{ formatCurrency(normalizedStats.remaining_balance) }}
                            </p>
                        </div>
                    </div>

                    <!-- Pending Charges Card -->
                    <div
                        :class="[
                            'flex items-center gap-4 rounded-lg border-l-4 bg-white p-6 shadow-md',
                            pendingChargesInfo.hasWarning ? 'border-yellow-300' : 'border-gray-300',
                        ]"
                    >
                        <div :class="['rounded-lg p-3', pendingChargesInfo.hasWarning ? 'bg-yellow-100' : 'bg-gray-100']">
                            <Clock :size="24" :class="[pendingChargesInfo.hasWarning ? 'text-yellow-600' : 'text-gray-500']" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Pending Charges</p>
                            <p :class="['text-2xl font-bold', pendingChargesInfo.hasWarning ? 'text-yellow-600' : 'text-gray-700']">
                                {{ pendingChargesInfo.count }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="rounded-lg bg-white p-6 shadow-md">
                    <h2 class="mb-4 text-lg font-semibold">Quick Actions</h2>

                    <div class="space-y-3">
                        <Link :href="route('student.account')" class="flex items-center gap-3 rounded-lg bg-blue-50 p-3 hover:bg-blue-100">
                            <Wallet :size="20" class="text-blue-600" />
                            <span class="font-medium">View Account</span>
                        </Link>

                        <Link
                            :href="route('student.account', { tab: 'payment' })"
                            class="flex items-center gap-3 rounded-lg bg-green-50 p-3 hover:bg-green-100"
                        >
                            <CreditCard :size="20" class="text-green-600" />
                            <span class="font-medium">Make Payment</span>
                        </Link>

                        <Link :href="route('transactions.index')" class="flex items-center gap-3 rounded-lg bg-purple-50 p-3 hover:bg-purple-100">
                            <FileText :size="20" class="text-purple-600" />
                            <span class="font-medium">View History</span>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Pending Payment Notice -->
            <div v-if="hasAwaitingApprovals" class="mb-6 flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4">
                <div class="flex flex-1 items-center gap-2">
                    <div class="h-2 w-2 animate-pulse rounded-full bg-blue-500"></div>
                    <p class="text-sm text-blue-700">
                        <strong>Checking for updates...</strong> Your payment is awaiting verification. This page will update automatically as soon as
                        it's processed.
                    </p>
                </div>
            </div>

            <!-- MAIN CONTENT -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- LEFT COLUMN -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Payment Reminders History -->
                    <div v-if="props.paymentReminders && props.paymentReminders.length > 0" class="rounded-lg bg-white p-6 shadow-md">
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <h2 class="text-xl font-semibold">Payment Reminders</h2>
                                <span
                                    v-if="props.unreadReminderCount && props.unreadReminderCount > 0"
                                    class="inline-flex items-center justify-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800"
                                >
                                    {{ props.unreadReminderCount }} new
                                </span>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="reminder in props.paymentReminders"
                                :key="reminder.id"
                                :class="[
                                    'rounded-lg border-l-4 p-4',
                                    reminder.type === 'overdue' || reminder.type === 'approaching_due'
                                        ? 'border-red-400 bg-red-50'
                                        : reminder.type === 'partial_payment'
                                          ? 'border-yellow-400 bg-yellow-50'
                                          : 'border-blue-400 bg-blue-50',
                                ]"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h4
                                            :class="[
                                                'text-sm font-semibold',
                                                reminder.type === 'overdue' || reminder.type === 'approaching_due'
                                                    ? 'text-red-900'
                                                    : reminder.type === 'partial_payment'
                                                      ? 'text-yellow-900'
                                                      : 'text-blue-900',
                                            ]"
                                        >
                                            {{ reminder.message }}
                                        </h4>
                                        <p class="mt-1 text-xs text-gray-600">
                                            {{ formatDate(reminder.sent_at) }}
                                        </p>
                                    </div>
                                    <span
                                        :class="[
                                            'ml-2 rounded px-2 py-1 text-xs font-medium whitespace-nowrap',
                                            reminder.status === 'read' ? 'bg-gray-100 text-gray-700' : 'bg-red-100 text-red-700',
                                        ]"
                                    >
                                        {{ reminder.status === 'read' ? 'Read' : 'Unread' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ✅ Recent Transactions (RETAINED) -->
                    <div class="rounded-lg bg-white p-6 shadow-md">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-xl font-semibold">Recent Transactions</h2>
                            <Link :href="route('transactions.index')" class="text-sm text-blue-600 hover:underline"> View All → </Link>
                        </div>

                        <p v-if="!recentTransactions.length" class="py-4 text-center text-gray-500">No recent transactions</p>

                        <div v-else class="space-y-3">
                            <div
                                v-for="transaction in recentTransactions"
                                :key="transaction.id"
                                class="flex items-center justify-between rounded p-3 hover:bg-gray-50"
                            >
                                <div>
                                    <p class="font-medium">{{ formatTransactionType(transaction.type) }}</p>
                                    <p class="text-sm text-gray-600">{{ transaction.reference || 'N/A' }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ transaction.created_at ? formatDate(transaction.created_at) : '-' }}
                                    </p>
                                </div>

                                <div class="text-right">
                                    <p class="font-semibold">
                                        {{ formatCurrency(transaction.amount) }}
                                    </p>
                                    <span
                                        class="rounded px-2 py-1 text-xs font-medium"
                                        :class="{
                                            ...getTransactionStatusConfig(transaction.status),
                                        }"
                                    >
                                        {{ getTransactionStatusConfig(transaction.status).label }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN - PAYMENT STATUS COMMUNICATION -->
                <div class="space-y-6">
                    <!-- Payment Due - Shows Next Payment Term -->
                    <div
                        v-if="nextPaymentDue"
                        :class="[
                            'rounded-lg border-2 p-6 shadow-md',
                            nextPaymentDue.dueColor === 'red'
                                ? 'border-red-300 bg-gradient-to-br from-red-50 to-red-100'
                                : nextPaymentDue.dueColor === 'amber'
                                  ? 'border-amber-300 bg-gradient-to-br from-amber-50 to-amber-100'
                                  : 'border-green-300 bg-gradient-to-br from-green-50 to-green-100',
                        ]"
                    >
                        <!-- Header -->
                        <div class="mb-4 flex items-start justify-between">
                            <div>
                                <h3
                                    :class="[
                                        'text-lg font-semibold',
                                        nextPaymentDue.dueColor === 'red'
                                            ? 'text-red-900'
                                            : nextPaymentDue.dueColor === 'amber'
                                              ? 'text-amber-900'
                                              : 'text-green-900',
                                    ]"
                                >
                                    {{ nextPaymentDue.term_name }}
                                </h3>
                                <p
                                    :class="[
                                        'mt-1 text-xs',
                                        nextPaymentDue.dueColor === 'red'
                                            ? 'text-red-700'
                                            : nextPaymentDue.dueColor === 'amber'
                                              ? 'text-amber-700'
                                              : 'text-green-700',
                                    ]"
                                >
                                    {{ nextPaymentDue.isDueOrOverdue ? 'Payment due soon' : 'Upcoming payment' }}
                                </p>
                            </div>
                            <div
                                :class="[
                                    'rounded-lg p-2',
                                    nextPaymentDue.dueColor === 'red'
                                        ? 'bg-red-200'
                                        : nextPaymentDue.dueColor === 'amber'
                                          ? 'bg-amber-200'
                                          : 'bg-green-200',
                                ]"
                            >
                                <AlertCircle
                                    v-if="nextPaymentDue.dueColor === 'red'"
                                    :size="20"
                                    :class="[nextPaymentDue.dueColor === 'red' ? 'text-red-700' : '']"
                                />
                                <Clock v-else-if="nextPaymentDue.dueColor === 'amber'" :size="20" class="text-amber-700" />
                                <CheckCircle v-else :size="20" class="text-green-700" />
                            </div>
                        </div>

                        <!-- Term Details -->
                        <div
                            :class="[
                                'mb-4 rounded-lg border p-4',
                                nextPaymentDue.dueColor === 'red'
                                    ? 'bg-opacity-60 border-red-200 bg-white'
                                    : nextPaymentDue.dueColor === 'amber'
                                      ? 'bg-opacity-60 border-amber-200 bg-white'
                                      : 'bg-opacity-60 border-green-200 bg-white',
                            ]"
                        >
                            <div class="space-y-3">
                                <!-- Amount Due -->
                                <div>
                                    <p
                                        :class="[
                                            'mb-1 text-xs font-medium',
                                            nextPaymentDue.dueColor === 'red'
                                                ? 'text-red-700'
                                                : nextPaymentDue.dueColor === 'amber'
                                                  ? 'text-amber-700'
                                                  : 'text-green-700',
                                        ]"
                                    >
                                        Amount Due
                                    </p>
                                    <p
                                        :class="[
                                            'text-2xl font-bold',
                                            nextPaymentDue.dueColor === 'red'
                                                ? 'text-red-700'
                                                : nextPaymentDue.dueColor === 'amber'
                                                  ? 'text-amber-700'
                                                  : 'text-green-700',
                                        ]"
                                    >
                                        {{ formatCurrency(nextPaymentDue.balance) }}
                                    </p>
                                </div>

                                <!-- Due Date with Color Coding -->
                                <div class="border-t border-gray-300 pt-2">
                                    <p class="mb-1 text-xs text-gray-600">Due Date</p>
                                    <div class="flex items-center justify-between">
                                        <p
                                            :class="[
                                                'font-semibold',
                                                nextPaymentDue.dueColor === 'red'
                                                    ? 'text-red-700'
                                                    : nextPaymentDue.dueColor === 'amber'
                                                      ? 'text-amber-700'
                                                      : 'text-gray-700',
                                            ]"
                                        >
                                            {{ nextPaymentDue.formattedDueDate }}
                                        </p>
                                        <span
                                            v-if="nextPaymentDue.daysUntilDue >= 0"
                                            :class="[
                                                'rounded px-2 py-1 text-xs font-medium',
                                                nextPaymentDue.dueColor === 'red'
                                                    ? 'bg-red-100 text-red-700'
                                                    : nextPaymentDue.dueColor === 'amber'
                                                      ? 'bg-amber-100 text-amber-700'
                                                      : 'bg-green-100 text-green-700',
                                            ]"
                                        >
                                            {{ nextPaymentDue.daysUntilDue }} day{{ nextPaymentDue.daysUntilDue !== 1 ? 's' : '' }} left
                                        </span>
                                        <span v-else class="rounded bg-red-100 px-2 py-1 text-xs font-medium text-red-700">
                                            {{ Math.abs(nextPaymentDue.daysUntilDue) }} day{{
                                                Math.abs(nextPaymentDue.daysUntilDue) !== 1 ? 's' : ''
                                            }}
                                            overdue
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-3">
                            <Link
                                :href="route('student.account')"
                                class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-center text-sm font-medium text-white transition hover:bg-blue-700"
                            >
                                View Details
                            </Link>
                            <Link
                                :href="route('student.account', { tab: 'payment', term_id: nextPaymentDue.id })"
                                class="flex-1 rounded-lg bg-green-600 px-4 py-2 text-center text-sm font-medium text-white transition hover:bg-green-700"
                            >
                                Pay Now
                            </Link>
                        </div>
                    </div>

                    <!-- Success State - All Paid -->
                    <div
                        v-if="normalizedStats.remaining_balance === 0"
                        class="rounded-lg border-2 border-green-300 bg-gradient-to-br from-green-50 to-green-100 p-6 shadow-md"
                    >
                        <!-- Header -->
                        <div class="mb-4 flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-green-900">Account in Good Standing</h3>
                                <p class="mt-1 text-xs text-green-700">All payments are current</p>
                            </div>
                            <div class="rounded-lg bg-green-200 p-2">
                                <CheckCircle :size="20" class="text-green-700" />
                            </div>
                        </div>

                        <!-- Status Message -->
                        <div class="bg-opacity-60 mb-4 rounded-lg border border-green-200 bg-white p-4">
                            <p class="text-sm text-green-800">Your account balance is fully paid. No payment action is required at this time.</p>
                        </div>

                        <!-- Guidance -->
                        <div class="text-xs text-green-700">
                            <p class="mb-2">
                                <span class="font-semibold">📌 Reminder:</span> Check your dashboard regularly for any new assessment notices or
                                payment terms.
                            </p>
                            <p>
                                <span class="font-semibold">📧 Questions?</span> Contact the Office of the Registrar if you need to verify your
                                account status.
                            </p>
                        </div>
                    </div>

                    <!-- Data Integrity Note (development only - can be removed) -->
                    <div v-if="!financialDataIsConsistent" class="rounded-lg border border-yellow-400 bg-yellow-50 p-4">
                        <p class="text-xs text-yellow-800">
                            <span class="font-semibold">⚠️ Note:</span> There is a discrepancy in your financial data. Please contact support if this
                            persists.
                        </p>
                    </div>

                    <!-- Important Updates / Notifications -->
                    <div v-if="activeNotifications.length">
                        <div class="mb-4 flex items-center gap-2">
                            <Bell class="h-6 w-6 text-blue-600" />
                            <h2 class="text-xl font-bold text-gray-900">Important Updates</h2>
                        </div>

                        <div class="space-y-4">
                            <div
                                v-for="notification in visibleNotifications"
                                :key="notification.id"
                                class="rounded-lg border-l-4 border-blue-500 bg-white p-5 shadow-md transition-all hover:bg-blue-50 hover:shadow-lg"
                            >
                                <div class="mb-3 flex items-start justify-between">
                                    <h3 class="flex-1 pr-2 text-base font-bold text-gray-900">{{ notification.title }}</h3>
                                    <div class="flex-shrink-0">
                                        <div
                                            class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-1 text-xs font-semibold whitespace-nowrap text-green-700"
                                        >
                                            ✓ Active
                                        </div>
                                    </div>
                                </div>

                                <p class="mb-3 text-sm leading-relaxed text-gray-700">{{ notification.message }}</p>

                                <div class="space-y-1 border-t border-gray-200 pt-3 text-xs text-gray-600">
                                    <p v-if="notification.start_date">📅 <strong>From:</strong> {{ formatDate(notification.start_date) }}</p>
                                    <p v-if="notification.end_date">📅 <strong>Until:</strong> {{ formatDate(notification.end_date) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- View More / Show Less Button -->
                        <div v-if="hasMoreNotifications" class="mt-4">
                            <button
                                @click="showAllNotifications = !showAllNotifications"
                                class="w-full rounded-lg bg-blue-600 px-4 py-2 text-center font-medium text-white transition-colors hover:bg-blue-700"
                            >
                                {{ showAllNotifications ? 'Show Less' : `View More Updates (${activeNotifications.length - 3} more)` }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
