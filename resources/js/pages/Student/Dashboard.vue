<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { useDataFormatting } from '@/composables/useDataFormatting';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertCircle, Bell, CalendarClock, CheckCircle, Clock, CreditCard, FileText, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const { formatCurrency, formatDate, getTransactionStatusConfig, formatTransactionType } = useDataFormatting();

type Notification = {
    id: number;
    title: string;
    message: string;
    type: string | null;
    start_date: string | null;
    end_date: string | null;
    due_date: string | null;         // actual payment deadline
    payment_term_id: number | null;  // links to pay-now flow
    target_role: string;
    is_active: boolean;
    is_complete: boolean;
    dismissed_at: string | null;
    created_at: string;
};

type Account = { balance: number };

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

// ── Financial normalization ───────────────────────────────────────────────────

const normalizedStats = computed(() => {
    const safe = (v: any): number => {
        if (v == null) return 0;
        const n = Number(v);
        return isFinite(n) ? Math.max(0, n) : 0;
    };

    const totalFees = props.latestAssessment
        ? safe(props.latestAssessment.total_assessment)
        : safe(props.stats?.total_fees);

    const remainingBalance =
        props.paymentTerms && props.paymentTerms.length > 0
            ? safe(props.paymentTerms.reduce((s, t) => s + (t.balance || 0), 0))
            : safe(props.stats?.remaining_balance);

    return {
        total_fees: totalFees,
        total_paid: safe(props.stats?.total_paid),
        remaining_balance: remainingBalance,
        pending_charges_count: Math.floor(safe(props.stats?.pending_charges_count)),
    };
});

const financialDataIsConsistent = computed(() => {
    const { total_fees, total_paid, remaining_balance } = normalizedStats.value;
    if (props.paymentTerms && props.paymentTerms.length > 0) {
        const termsBalance = props.paymentTerms.reduce((s, t) => s + (t.balance || 0), 0);
        return Math.abs(remaining_balance - termsBalance) < 0.01;
    }
    return Math.abs(remaining_balance - Math.max(0, total_fees - total_paid)) < 0.01;
});

const pendingChargesInfo = computed(() => {
    const count = normalizedStats.value.pending_charges_count;
    return {
        count,
        hasWarning: count > 0,
        description: count === 0 ? 'All charges are processed' : 'Charges awaiting processing',
    };
});

const hasAwaitingApprovals = computed(() =>
    props.recentTransactions.some((t) => t.status === 'awaiting_approval'),
);

// ── Payment term helpers ──────────────────────────────────────────────────────

const unpaidTerms = computed(() =>
    (props.paymentTerms ?? []).filter((t) => t.balance > 0).sort((a, b) => a.term_order - b.term_order),
);

const getDueDateColor = (dueDate: string): 'red' | 'amber' | 'green' => {
    const diffDays = Math.ceil((new Date(dueDate).getTime() - Date.now()) / 86_400_000);
    if (diffDays <= 7)  return 'red';
    if (diffDays <= 14) return 'amber';
    return 'green';
};

const nextPaymentDue = computed(() => {
    if (!unpaidTerms.value.length) return null;
    const term = unpaidTerms.value[0];
    const daysUntilDue = Math.ceil((new Date(term.due_date).getTime() - Date.now()) / 86_400_000);
    return {
        ...term,
        dueColor: getDueDateColor(term.due_date),
        daysUntilDue,
        formattedDueDate: formatDate(term.due_date),
        isDueOrOverdue: daysUntilDue <= 7,
    };
});

// ── Notification due-date helpers ─────────────────────────────────────────────

/**
 * Compute urgency colour for a notification's due_date field.
 * Red   = overdue or ≤ 7 days
 * Amber = 8–14 days
 * Green = > 14 days
 */
const getNotifDueDateColor = (dueDateStr: string | null): 'red' | 'amber' | 'green' => {
    if (!dueDateStr) return 'amber';
    return getDueDateColor(dueDateStr);
};

/**
 * Human-readable urgency label for the due-date chip inside a notification banner.
 * Examples: "Overdue", "Due today", "Due in 3 days", "Due Apr 20, 2026"
 */
const dueDateLabel = (dueDateStr: string | null): string => {
    if (!dueDateStr) return '';
    const diffDays = Math.ceil((new Date(dueDateStr).getTime() - Date.now()) / 86_400_000);
    if (diffDays < 0)  return `Overdue by ${Math.abs(diffDays)} day${Math.abs(diffDays) !== 1 ? 's' : ''}`;
    if (diffDays === 0) return 'Due today';
    if (diffDays === 1) return 'Due tomorrow';
    if (diffDays <= 14) return `Due in ${diffDays} days`;
    return `Due ${formatDate(dueDateStr)}`;
};

// ── Notification state ────────────────────────────────────────────────────────

const hiddenNotifications = ref<Set<number>>(new Set());

const activeNotifications = computed(() => {
    const now = Date.now();
    return props.notifications
        .filter((n) => {
            if (n.dismissed_at) return false;
            if (n.is_complete)  return false;
            if (hiddenNotifications.value.has(n.id)) return false;
            if (n.start_date && new Date(n.start_date).getTime() > now) return false;
            if (n.end_date   && new Date(n.end_date).getTime()   < now) return false;
            return true;
        })
        .sort((a, b) => {
            // payment_due banners first, then by urgency (soonest due date first)
            if (a.type === 'payment_due' && b.type !== 'payment_due') return -1;
            if (a.type !== 'payment_due' && b.type === 'payment_due') return 1;
            if (a.due_date && b.due_date) {
                return new Date(a.due_date).getTime() - new Date(b.due_date).getTime();
            }
            return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
        });
});

const showAllNotifications = ref(false);
const visibleNotifications = computed(() =>
    showAllNotifications.value ? activeNotifications.value : activeNotifications.value.slice(0, 3),
);
const hasMoreNotifications = computed(() => activeNotifications.value.length > 3);

const dismissNotification = (id: number) => {
    hiddenNotifications.value.add(id);
    router.post(route('notifications.dismiss', id), {}, {
        preserveScroll: true,
        preserveState: true,
    });
};
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

            <!-- Quick Stats + Quick Actions -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:col-span-2">
                    <!-- Total Assessment Fee -->
                    <div class="flex items-center gap-4 rounded-lg border-l-4 border-blue-300 bg-white p-6 shadow-md">
                        <div class="rounded-lg bg-blue-100 p-3"><FileText :size="24" class="text-blue-600" /></div>
                        <div>
                            <p class="text-sm text-gray-600">Total Assessment Fee</p>
                            <p class="text-2xl font-bold text-blue-700">{{ formatCurrency(normalizedStats.total_fees) }}</p>
                        </div>
                    </div>

                    <!-- Total Paid -->
                    <div class="flex items-center gap-4 rounded-lg border-l-4 border-green-300 bg-white p-6 shadow-md">
                        <div class="rounded-lg bg-green-100 p-3"><CheckCircle :size="24" class="text-green-600" /></div>
                        <div>
                            <p class="text-sm text-gray-600">Total Paid</p>
                            <p class="text-2xl font-bold text-green-600">{{ formatCurrency(normalizedStats.total_paid) }}</p>
                        </div>
                    </div>

                    <!-- Remaining Balance -->
                    <div :class="['flex items-center gap-4 rounded-lg border-l-4 bg-white p-6 shadow-md', normalizedStats.remaining_balance > 0 ? 'border-red-300' : 'border-green-300']">
                        <div :class="['rounded-lg p-3', normalizedStats.remaining_balance > 0 ? 'bg-red-100' : 'bg-green-100']">
                            <Wallet :size="24" :class="normalizedStats.remaining_balance > 0 ? 'text-red-600' : 'text-green-600'" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Remaining Balance</p>
                            <p :class="['text-2xl font-bold', normalizedStats.remaining_balance > 0 ? 'text-red-600' : 'text-green-600']">
                                {{ formatCurrency(normalizedStats.remaining_balance) }}
                            </p>
                        </div>
                    </div>

                    <!-- Pending Charges -->
                    <div :class="['flex items-center gap-4 rounded-lg border-l-4 bg-white p-6 shadow-md', pendingChargesInfo.hasWarning ? 'border-yellow-300' : 'border-gray-300']">
                        <div :class="['rounded-lg p-3', pendingChargesInfo.hasWarning ? 'bg-yellow-100' : 'bg-gray-100']">
                            <Clock :size="24" :class="pendingChargesInfo.hasWarning ? 'text-yellow-600' : 'text-gray-500'" />
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
                            <Wallet :size="20" class="text-blue-600" /><span class="font-medium">View Account</span>
                        </Link>
                        <Link :href="route('student.account', { tab: 'payment' })" class="flex items-center gap-3 rounded-lg bg-green-50 p-3 hover:bg-green-100">
                            <CreditCard :size="20" class="text-green-600" /><span class="font-medium">Make Payment</span>
                        </Link>
                        <Link :href="route('transactions.index')" class="flex items-center gap-3 rounded-lg bg-purple-50 p-3 hover:bg-purple-100">
                            <FileText :size="20" class="text-purple-600" /><span class="font-medium">View History</span>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Awaiting Approval Banner -->
            <div v-if="hasAwaitingApprovals" class="flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4">
                <div class="h-2 w-2 animate-pulse rounded-full bg-blue-500"></div>
                <p class="text-sm text-blue-700">
                    <strong>Checking for updates…</strong> Your payment is awaiting verification. This page will update automatically.
                </p>
            </div>

            <!-- Main Content -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                <!-- Left Column -->
                <div class="space-y-6 lg:col-span-2">

                    <!-- Payment Reminders -->
                    <div v-if="props.paymentReminders && props.paymentReminders.length > 0" class="rounded-lg bg-white p-6 shadow-md">
                        <div class="mb-4 flex items-center gap-2">
                            <h2 class="text-xl font-semibold">Payment Reminders</h2>
                            <span v-if="props.unreadReminderCount && props.unreadReminderCount > 0"
                                class="inline-flex items-center justify-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">
                                {{ props.unreadReminderCount }} new
                            </span>
                        </div>
                        <div class="space-y-3">
                            <div v-for="reminder in props.paymentReminders" :key="reminder.id"
                                :class="['rounded-lg border-l-4 p-4',
                                    reminder.type === 'overdue' || reminder.type === 'approaching_due' ? 'border-red-400 bg-red-50'
                                    : reminder.type === 'partial_payment' ? 'border-yellow-400 bg-yellow-50'
                                    : 'border-blue-400 bg-blue-50']">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h4 :class="['text-sm font-semibold',
                                            reminder.type === 'overdue' || reminder.type === 'approaching_due' ? 'text-red-900'
                                            : reminder.type === 'partial_payment' ? 'text-yellow-900' : 'text-blue-900']">
                                            {{ reminder.message }}
                                        </h4>
                                        <p class="mt-1 text-xs text-gray-600">{{ formatDate(reminder.sent_at) }}</p>
                                    </div>
                                    <span :class="['ml-2 rounded px-2 py-1 text-xs font-medium whitespace-nowrap',
                                        reminder.status === 'read' ? 'bg-gray-100 text-gray-700' : 'bg-red-100 text-red-700']">
                                        {{ reminder.status === 'read' ? 'Read' : 'Unread' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Transactions -->
                    <div class="rounded-lg bg-white p-6 shadow-md">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-xl font-semibold">Recent Transactions</h2>
                            <Link :href="route('transactions.index')" class="text-sm text-blue-600 hover:underline">View All →</Link>
                        </div>
                        <p v-if="!recentTransactions.length" class="py-4 text-center text-gray-500">No recent transactions</p>
                        <div v-else class="space-y-3">
                            <div v-for="transaction in recentTransactions" :key="transaction.id"
                                class="flex items-center justify-between rounded p-3 hover:bg-gray-50">
                                <div>
                                    <p class="font-medium">{{ formatTransactionType(transaction.type) }}</p>
                                    <p class="text-sm text-gray-600">{{ transaction.reference || 'N/A' }}</p>
                                    <p class="text-xs text-gray-500">{{ transaction.created_at ? formatDate(transaction.created_at) : '-' }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold">{{ formatCurrency(transaction.amount) }}</p>
                                    <span class="rounded px-2 py-1 text-xs font-medium" :class="{ ...getTransactionStatusConfig(transaction.status) }">
                                        {{ getTransactionStatusConfig(transaction.status).label }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">

                    <!-- Next Payment Due Card -->
                    <div v-if="nextPaymentDue"
                        :class="['rounded-lg border-2 p-6 shadow-md',
                            nextPaymentDue.dueColor === 'red'   ? 'border-red-300 bg-gradient-to-br from-red-50 to-red-100'
                            : nextPaymentDue.dueColor === 'amber' ? 'border-amber-300 bg-gradient-to-br from-amber-50 to-amber-100'
                            : 'border-green-300 bg-gradient-to-br from-green-50 to-green-100']">
                        <div class="mb-4 flex items-start justify-between">
                            <div>
                                <h3 :class="['text-lg font-semibold',
                                    nextPaymentDue.dueColor === 'red' ? 'text-red-900'
                                    : nextPaymentDue.dueColor === 'amber' ? 'text-amber-900' : 'text-green-900']">
                                    {{ nextPaymentDue.term_name }}
                                </h3>
                                <p :class="['mt-1 text-xs',
                                    nextPaymentDue.dueColor === 'red' ? 'text-red-700'
                                    : nextPaymentDue.dueColor === 'amber' ? 'text-amber-700' : 'text-green-700']">
                                    {{ nextPaymentDue.isDueOrOverdue ? 'Payment due soon' : 'Upcoming payment' }}
                                </p>
                            </div>
                            <div :class="['rounded-lg p-2',
                                nextPaymentDue.dueColor === 'red' ? 'bg-red-200'
                                : nextPaymentDue.dueColor === 'amber' ? 'bg-amber-200' : 'bg-green-200']">
                                <AlertCircle v-if="nextPaymentDue.dueColor === 'red'" :size="20" class="text-red-700" />
                                <Clock v-else-if="nextPaymentDue.dueColor === 'amber'" :size="20" class="text-amber-700" />
                                <CheckCircle v-else :size="20" class="text-green-700" />
                            </div>
                        </div>

                        <div :class="['mb-4 rounded-lg border p-4',
                            nextPaymentDue.dueColor === 'red' ? 'border-red-200 bg-white/60'
                            : nextPaymentDue.dueColor === 'amber' ? 'border-amber-200 bg-white/60'
                            : 'border-green-200 bg-white/60']">
                            <div class="space-y-3">
                                <div>
                                    <p :class="['mb-1 text-xs font-medium',
                                        nextPaymentDue.dueColor === 'red' ? 'text-red-700'
                                        : nextPaymentDue.dueColor === 'amber' ? 'text-amber-700' : 'text-green-700']">Amount Due</p>
                                    <p :class="['text-2xl font-bold',
                                        nextPaymentDue.dueColor === 'red' ? 'text-red-700'
                                        : nextPaymentDue.dueColor === 'amber' ? 'text-amber-700' : 'text-green-700']">
                                        {{ formatCurrency(nextPaymentDue.balance) }}
                                    </p>
                                </div>
                                <div class="border-t border-gray-300 pt-2">
                                    <p class="mb-1 text-xs text-gray-600">Due Date</p>
                                    <div class="flex items-center justify-between">
                                        <p :class="['font-semibold',
                                            nextPaymentDue.dueColor === 'red' ? 'text-red-700'
                                            : nextPaymentDue.dueColor === 'amber' ? 'text-amber-700' : 'text-gray-700']">
                                            {{ nextPaymentDue.formattedDueDate }}
                                        </p>
                                        <span v-if="nextPaymentDue.daysUntilDue >= 0"
                                            :class="['rounded px-2 py-1 text-xs font-medium',
                                                nextPaymentDue.dueColor === 'red' ? 'bg-red-100 text-red-700'
                                                : nextPaymentDue.dueColor === 'amber' ? 'bg-amber-100 text-amber-700'
                                                : 'bg-green-100 text-green-700']">
                                            {{ nextPaymentDue.daysUntilDue }} day{{ nextPaymentDue.daysUntilDue !== 1 ? 's' : '' }} left
                                        </span>
                                        <span v-else class="rounded bg-red-100 px-2 py-1 text-xs font-medium text-red-700">
                                            {{ Math.abs(nextPaymentDue.daysUntilDue) }} day{{ Math.abs(nextPaymentDue.daysUntilDue) !== 1 ? 's' : '' }} overdue
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <Link :href="route('student.account')"
                                class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-center text-sm font-medium text-white transition hover:bg-blue-700">
                                View Details
                            </Link>
                            <Link :href="route('student.account', { tab: 'payment', term_id: nextPaymentDue.id })"
                                class="flex-1 rounded-lg bg-green-600 px-4 py-2 text-center text-sm font-medium text-white transition hover:bg-green-700">
                                Pay Now
                            </Link>
                        </div>
                    </div>

                    <!-- All Paid State -->
                    <div v-if="normalizedStats.remaining_balance === 0"
                        class="rounded-lg border-2 border-green-300 bg-gradient-to-br from-green-50 to-green-100 p-6 shadow-md">
                        <div class="mb-4 flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-green-900">Account in Good Standing</h3>
                                <p class="mt-1 text-xs text-green-700">All payments are current</p>
                            </div>
                            <div class="rounded-lg bg-green-200 p-2"><CheckCircle :size="20" class="text-green-700" /></div>
                        </div>
                        <div class="mb-4 rounded-lg border border-green-200 bg-white/60 p-4">
                            <p class="text-sm text-green-800">Your account balance is fully paid. No payment action is required at this time.</p>
                        </div>
                        <div class="space-y-1 text-xs text-green-700">
                            <p><span class="font-semibold">📌 Reminder:</span> Check your dashboard regularly for new assessment notices.</p>
                            <p><span class="font-semibold">📧 Questions?</span> Contact the Office of the Registrar to verify your account status.</p>
                        </div>
                    </div>

                    <!-- Data integrity warning -->
                    <div v-if="!financialDataIsConsistent" class="rounded-lg border border-yellow-400 bg-yellow-50 p-4">
                        <p class="text-xs text-yellow-800">
                            <span class="font-semibold">⚠️ Note:</span> There is a discrepancy in your financial data. Please contact support if this persists.
                        </p>
                    </div>

                    <!-- ── Notification Banners ──────────────────────────────────────────
                         Each payment_due banner now shows a dedicated due-date chip that
                         is colour-coded by urgency (red/amber/green) and displays a
                         human-readable label ("Due in 3 days", "Overdue by 2 days", etc.)
                         The chip is built from the structured `due_date` column — not
                         parsed from the message text.
                         A "Pay Now" button is also shown when payment_term_id is set.
                    ─────────────────────────────────────────────────────────────────── -->
                    <div v-if="activeNotifications.length">
                        <div class="mb-4 flex items-center gap-2">
                            <Bell class="h-6 w-6 text-blue-600" />
                            <h2 class="text-xl font-bold text-gray-900">Important Updates</h2>
                        </div>

                        <div class="space-y-4">
                            <div v-for="notification in visibleNotifications" :key="notification.id"
                                :class="['rounded-lg border-l-4 bg-white p-5 shadow-md transition-all hover:shadow-lg',
                                    notification.type === 'payment_due'
                                        ? 'border-amber-500 hover:bg-amber-50'
                                        : 'border-blue-500 hover:bg-blue-50']">

                                <!-- Header row: title + dismiss -->
                                <div class="mb-2 flex items-start justify-between gap-2">
                                    <h3 class="flex-1 text-base font-bold text-gray-900">{{ notification.title }}</h3>
                                    <button @click="dismissNotification(notification.id)"
                                        class="flex-shrink-0 rounded p-1 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-600"
                                        title="Dismiss notification" aria-label="Dismiss notification">✕</button>
                                </div>

                                <!-- Due date chip — shown for payment_due when due_date is set -->
                                <div v-if="notification.type === 'payment_due' && notification.due_date" class="mb-3">
                                    <span :class="['inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold',
                                        getNotifDueDateColor(notification.due_date) === 'red'
                                            ? 'bg-red-100 text-red-700 ring-1 ring-red-200'
                                            : getNotifDueDateColor(notification.due_date) === 'amber'
                                              ? 'bg-amber-100 text-amber-700 ring-1 ring-amber-200'
                                              : 'bg-green-100 text-green-700 ring-1 ring-green-200']">
                                        <CalendarClock :size="12" />
                                        {{ dueDateLabel(notification.due_date) }}
                                        <span class="font-normal opacity-75">· {{ formatDate(notification.due_date) }}</span>
                                    </span>
                                </div>

                                <!-- Message body -->
                                <p class="mb-3 text-sm leading-relaxed text-gray-700">{{ notification.message }}</p>

                                <!-- Footer: date window + pay now button -->
                                <div class="flex items-center justify-between gap-2 border-t border-gray-200 pt-3">
                                    <div class="space-y-0.5 text-xs text-gray-500">
                                        <p v-if="notification.start_date">📅 From: {{ formatDate(notification.start_date) }}</p>
                                        <p v-if="notification.end_date">📅 Until: {{ formatDate(notification.end_date) }}</p>
                                    </div>

                                    <!-- Pay Now button — only for payment_due with a linked term -->
                                    <Link v-if="notification.type === 'payment_due' && notification.payment_term_id"
                                        :href="route('student.account', { tab: 'payment', term_id: notification.payment_term_id })"
                                        class="flex-shrink-0 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-green-700">
                                        Pay Now
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <div v-if="hasMoreNotifications" class="mt-4">
                            <button @click="showAllNotifications = !showAllNotifications"
                                class="w-full rounded-lg bg-blue-600 px-4 py-2 text-center font-medium text-white transition-colors hover:bg-blue-700">
                                {{ showAllNotifications ? 'Show Less' : `View More Updates (${activeNotifications.length - 3} more)` }}
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>