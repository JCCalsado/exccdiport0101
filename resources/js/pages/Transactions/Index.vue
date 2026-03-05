<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Transaction {
    id: number;
    reference: string;
    user?: {
        id: number;
        name: string;
        student_id: string;
        email: string;
    };
    kind: 'charge' | 'payment';
    type: string;
    year: string;
    semester: string;
    amount: number;
    status: string;
    payment_channel?: string;
    paid_at?: string;
    created_at: string;
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
    };
    currentTerm: string;
}

const props = defineProps<Props>();

const breadcrumbs = [{ title: 'Dashboard', href: route('dashboard') }, { title: 'Transaction History' }];

const search = ref('');
const expanded = ref<Record<string, boolean>>({});
const showPastSemesters = ref(false);
const selectedTransaction = ref<Transaction | null>(null);
const showDetailsDialog = ref(false);

const isStaff = computed(() => {
    return ['admin', 'accounting'].includes(props.auth.user.role);
});

// Initialize first term as expanded
if (props.currentTerm && props.transactionsByTerm && props.transactionsByTerm[props.currentTerm]) {
    expanded.value[props.currentTerm] = true;
}

// Count total terms
const totalTermsCount = computed(() => {
    return props.transactionsByTerm ? Object.keys(props.transactionsByTerm).length : 0;
});

const toggle = (key: string) => {
    expanded.value[key] = !expanded.value[key];
};

// Calculate term summary
const calculateTermSummary = (transactions: Transaction[]): TermSummary => {
    if (!transactions || !Array.isArray(transactions)) {
        return {
            total_assessment: 0,
            total_paid: 0,
            current_balance: 0,
        };
    }

    const charges = transactions.filter((t) => t && t.kind === 'charge').reduce((sum, t) => sum + parseFloat(String(t.amount || 0)), 0);

    const payments = transactions
        .filter((t) => t && t.kind === 'payment' && t.status === 'paid')
        .reduce((sum, t) => sum + parseFloat(String(t.amount || 0)), 0);

    return {
        total_assessment: charges,
        total_paid: payments,
        current_balance: charges - payments,
    };
};

// Filter transactions based on search and past semesters visibility
// Filter to only show assessment-related charges (exclude lab fees, misc fees, etc.)
const isAssessmentTransaction = (transaction: Transaction): boolean => {
    const type = transaction.type?.toLowerCase() || '';
    // Exclude individual fees like laboratory fee, lab fee, misc fees, etc.
    const excludedTypes = ['laboratory', 'lab', 'lab fee', 'laboratory fee', 'misc', 'miscellaneous'];
    const isExcluded = excludedTypes.some((excluded) => type.includes(excluded));

    // Show payments regardless, and charges that are not excluded
    return transaction.kind === 'payment' || !isExcluded;
};

const filteredTransactionsByTerm = computed(() => {
    if (!props.transactionsByTerm) return {};

    let terms = props.transactionsByTerm;

    // Filter out past semesters if not showing them
    if (!showPastSemesters.value && props.currentTerm && terms[props.currentTerm]) {
        terms = { [props.currentTerm]: terms[props.currentTerm] };
    }

    // Filter transactions to only show assessment-related items
    const filtered: Record<string, Transaction[]> = {};
    Object.entries(terms).forEach(([term, transactions]) => {
        if (!transactions || !Array.isArray(transactions)) return;

        const assessmentTransactions = transactions.filter(isAssessmentTransaction);
        if (assessmentTransactions.length > 0) {
            filtered[term] = assessmentTransactions;
        }
    });

    // Apply search filter
    if (!search.value) return filtered;

    const searchLower = search.value.toLowerCase();
    const searchFiltered: Record<string, Transaction[]> = {};

    Object.entries(filtered).forEach(([term, transactions]) => {
        const matchingTransactions = transactions.filter(
            (txn) =>
                txn.reference?.toLowerCase().includes(searchLower) ||
                txn.type?.toLowerCase().includes(searchLower) ||
                txn.user?.name?.toLowerCase().includes(searchLower) ||
                txn.user?.student_id?.toLowerCase().includes(searchLower),
        );

        if (matchingTransactions.length > 0) {
            searchFiltered[term] = matchingTransactions;
        }
    });

    return searchFiltered;
});

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const downloadPDF = (termKey: string) => {
    router.get(
        route('transactions.download', { term: termKey }),
        {},
        {
            preserveScroll: true,
        },
    );
};

const viewTransaction = (transaction: Transaction) => {
    selectedTransaction.value = transaction;
    showDetailsDialog.value = true;
};

const closeDetailsDialog = () => {
    showDetailsDialog.value = false;
    selectedTransaction.value = null;
};

// Calculate overall remaining balance from account
const overallRemainingBalance = computed(() => {
    return Math.max(0, Math.abs(props.account?.balance || 0));
});

// Check if payment can be made
const canMakePayment = computed(() => {
    return overallRemainingBalance.value > 0;
});

const payNow = () => {
    if (!canMakePayment.value) {
        alert('No outstanding balance to pay');
        return;
    }
    router.visit(route('student.account', { tab: 'payment' }));
};
</script>

<template>
    <Head title="Transaction History" />

    <AppLayout>
        <div class="w-full space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- HEADER -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Transaction History</h1>
                    <p class="text-gray-500">View all your financial transactions by term</p>
                </div>
                <Button v-if="totalTermsCount > 1" variant="outline" @click="showPastSemesters = !showPastSemesters">
                    {{ showPastSemesters ? 'Hide Past Semesters' : 'Show Past Semesters' }}
                </Button>
            </div>

            <!-- Current Balance Card (Students only) -->
            <div v-if="!isStaff && account" class="rounded-xl border bg-blue-50 p-6 shadow-sm">
                <h2 class="text-lg font-semibold">Current Balance</h2>
                <p class="text-gray-500">Your outstanding balance</p>
                <p class="mt-2 text-4xl font-bold" :class="(account.balance || 0) > 0 ? 'text-red-600' : 'text-green-600'">
                    ₱{{ formatCurrency(Math.abs(account.balance || 0)) }}
                </p>
            </div>

            <!-- Search Bar (Admin + Accounting only) -->
            <div v-if="isStaff" class="rounded-xl border bg-white p-4 shadow-sm">
                <input
                    v-model="search"
                    type="text"
                    class="w-full rounded-lg border p-3 outline-none focus:border-transparent focus:ring-2 focus:ring-blue-500"
                    placeholder="Search by reference, type, or student..."
                />
            </div>

            <!-- No Results -->
            <div v-if="Object.keys(filteredTransactionsByTerm).length === 0" class="py-12 text-center">
                <p class="text-lg text-gray-500">No transactions found</p>
                <p class="mt-2 text-sm text-gray-400">Try adjusting your search criteria</p>
            </div>

            <!-- TERMS -->
            <div
                v-for="(transactions, termKey) in filteredTransactionsByTerm"
                :key="termKey"
                class="overflow-hidden rounded-xl border bg-white shadow-sm"
            >
                <!-- Summary Header -->
                <div class="flex cursor-pointer items-center justify-between p-5 transition-colors hover:bg-gray-50" @click="toggle(termKey)">
                    <div class="flex-1">
                        <div class="flex items-center gap-3">
                            <h2 class="text-xl font-bold">{{ termKey }}</h2>
                            <span v-if="termKey === currentTerm" class="rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800">
                                Current Term
                            </span>
                        </div>
                        <p class="mt-1 text-gray-500">
                            Academic Year & Semester | {{ transactions.length }} transaction{{ transactions.length !== 1 ? 's' : '' }}
                        </p>
                    </div>

                    <!-- Summary Row -->
                    <div class="flex items-center gap-14 text-right">
                        <div>
                            <p class="text-sm text-gray-500">Total Assessment Fee</p>
                            <p class="font-bold text-red-600">₱{{ formatCurrency(calculateTermSummary(transactions).total_assessment) }}</p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-500">Total Paid</p>
                            <p class="font-bold text-green-600">₱{{ formatCurrency(calculateTermSummary(transactions).total_paid) }}</p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-500">Current Balance</p>
                            <p class="font-bold" :class="calculateTermSummary(transactions).current_balance > 0 ? 'text-red-600' : 'text-green-600'">
                                ₱{{ formatCurrency(Math.abs(calculateTermSummary(transactions).current_balance)) }}
                            </p>
                        </div>

                        <!-- Download PDF for this term (Receipt) -->
                        <button
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                            @click.stop="downloadPDF(termKey)"
                            title="Download Receipt for this term"
                        >
                            📄 Receipt
                        </button>

                        <div>
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
                </div>

                <!-- Expanded Table -->
                <div v-if="expanded[termKey]" class="border-t p-5">
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr class="bg-gray-100 text-sm text-gray-600">
                                    <th class="p-3 font-medium">Reference</th>
                                    <th v-if="isStaff" class="p-3 font-medium">Student</th>
                                    <th class="p-3 font-medium">Type</th>
                                    <th class="p-3 font-medium">Category</th>
                                    <th class="p-3 font-medium">Year & Semester</th>
                                    <th class="p-3 font-medium">Amount</th>
                                    <th class="p-3 font-medium">Status</th>
                                    <th class="p-3 font-medium">Date</th>
                                    <th class="p-3 font-medium">Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-for="t in transactions" :key="t.id" class="border-b transition-colors hover:bg-gray-50">
                                    <td class="p-3 font-mono text-sm">{{ t.reference }}</td>
                                    <td v-if="isStaff" class="p-3 text-sm">
                                        <div>
                                            <p class="font-medium">{{ t.user?.name }}</p>
                                            <p class="text-xs text-gray-500">{{ t.user?.student_id }}</p>
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
                                    <td class="p-3 text-sm">{{ t.type }}</td>
                                    <td class="p-3 text-sm">
                                        <div v-if="t.year || t.semester" class="space-y-1">
                                            <p v-if="t.year" class="font-medium">{{ t.year }}</p>
                                            <p v-if="t.semester" class="text-xs text-gray-600">{{ t.semester }}</p>
                                        </div>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="p-3 font-semibold" :class="t.kind === 'charge' ? 'text-red-600' : 'text-green-600'">
                                        {{ t.kind === 'charge' ? '+' : '-' }}₱{{ formatCurrency(t.amount) }}
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
                                    <td class="p-3 text-sm text-gray-600">{{ formatDate(t.created_at) }}</td>
                                    <td class="flex gap-2 p-3">
                                        <button
                                            @click="viewTransaction(t)"
                                            class="rounded-lg bg-blue-600 px-3 py-1 text-sm text-white transition-colors hover:bg-blue-700"
                                            title="View transaction details"
                                        >
                                            View
                                        </button>
                                        <button
                                            v-if="t.kind === 'payment'"
                                            @click="viewTransaction(t)"
                                            class="rounded-lg bg-green-600 px-3 py-1 text-sm text-white transition-colors hover:bg-green-700"
                                            title="Download receipt for this payment"
                                        >
                                            📄 Receipt
                                        </button>
                                        <button
                                            v-if="t.status === 'pending' && t.kind === 'charge' && !isStaff"
                                            @click="payNow(t)"
                                            :disabled="!canMakePayment"
                                            :class="
                                                canMakePayment
                                                    ? 'bg-red-600 text-white hover:bg-red-700'
                                                    : 'cursor-not-allowed bg-gray-400 text-gray-200'
                                            "
                                            class="rounded-lg px-3 py-1 text-sm transition-colors"
                                            :title="canMakePayment ? 'Make payment' : 'No outstanding balance'"
                                        >
                                            Pay Now
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Transaction Details Dialog -->
            <Dialog v-model:open="showDetailsDialog">
                <DialogContent class="max-h-[80vh] max-w-2xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Transaction Details</DialogTitle>
                        <DialogDescription> Complete information about this transaction </DialogDescription>
                    </DialogHeader>

                    <div v-if="selectedTransaction" class="space-y-6">
                        <!-- Basic Information -->
                        <div class="space-y-4">
                            <h3 class="border-b pb-2 text-lg font-semibold">Basic Information</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600">Reference Number</p>
                                    <p class="font-mono font-medium">{{ selectedTransaction.reference }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Date</p>
                                    <p class="font-medium">{{ formatDate(selectedTransaction.created_at) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Year & Semester</p>
                                    <p v-if="selectedTransaction.year || selectedTransaction.semester" class="font-medium">
                                        {{ selectedTransaction.year }} {{ selectedTransaction.semester }}
                                    </p>
                                    <p v-else class="text-gray-400">-</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Transaction Type</p>
                                    <span
                                        class="inline-block rounded-full px-2 py-1 text-xs font-semibold"
                                        :class="selectedTransaction.kind === 'charge' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'"
                                    >
                                        {{ selectedTransaction.kind }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Status</p>
                                    <span
                                        class="inline-block rounded-full px-2 py-1 text-xs font-semibold"
                                        :class="{
                                            'bg-green-100 text-green-800': selectedTransaction.status === 'paid',
                                            'bg-yellow-100 text-yellow-800': selectedTransaction.status === 'pending',
                                            'bg-red-100 text-red-800': selectedTransaction.status === 'failed',
                                            'bg-gray-100 text-gray-800': selectedTransaction.status === 'cancelled',
                                        }"
                                    >
                                        {{ selectedTransaction.status }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Category</p>
                                    <p class="font-medium">{{ selectedTransaction.type }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Amount</p>
                                    <p class="text-xl font-bold" :class="selectedTransaction.kind === 'charge' ? 'text-red-600' : 'text-green-600'">
                                        {{ selectedTransaction.kind === 'charge' ? '+' : '-' }}₱{{ formatCurrency(selectedTransaction.amount) }}
                                    </p>
                                </div>
                                <div v-if="!isStaff" class="col-span-2">
                                    <p class="text-sm text-gray-600">Overall Remaining Balance</p>
                                    <p class="text-lg font-bold" :class="overallRemainingBalance > 0 ? 'text-red-600' : 'text-green-600'">
                                        ₱{{ formatCurrency(overallRemainingBalance) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Student Information (for staff) -->
                        <div v-if="isStaff && selectedTransaction.user" class="space-y-4">
                            <h3 class="border-b pb-2 text-lg font-semibold">Student Information</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600">Student Name</p>
                                    <p class="font-medium">{{ selectedTransaction.user.name }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Account ID</p>
                                    <p class="font-medium">{{ selectedTransaction.user.student_id }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Email</p>
                                    <p class="font-medium">{{ selectedTransaction.user.email }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Information (if payment) -->
                        <div v-if="selectedTransaction.kind === 'payment'" class="space-y-4">
                            <h3 class="border-b pb-2 text-lg font-semibold">Payment Information</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600">Payment Method</p>
                                    <p class="font-medium capitalize">{{ selectedTransaction.payment_channel || 'N/A' }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Payment Date</p>
                                    <p class="font-medium">{{ selectedTransaction.paid_at ? formatDate(selectedTransaction.paid_at) : 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Fee Breakdown (if charge with metadata) -->
                        <div v-if="selectedTransaction.kind === 'charge'" class="space-y-4">
                            <h3 class="border-b pb-2 text-lg font-semibold">Fee Breakdown</h3>
                            <div class="space-y-3 rounded-lg bg-gray-50 p-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-700">{{ selectedTransaction.type }}</span>
                                    <span class="font-semibold">₱{{ formatCurrency(selectedTransaction.amount) }}</span>
                                </div>
                                <div v-if="selectedTransaction.year && selectedTransaction.semester" class="border-t pt-2 text-sm text-gray-600">
                                    <p>Academic Year: {{ selectedTransaction.year }}</p>
                                    <p>Semester: {{ selectedTransaction.semester }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex justify-end gap-3 border-t pt-4">
                            <Button variant="outline" @click="closeDetailsDialog"> Close </Button>
                            <Button @click="downloadPDF(`${selectedTransaction.year} ${selectedTransaction.semester}`)">{{
                                selectedTransaction.kind === 'payment' ? '📄 Payment Receipt' : '📄 Invoice'
                            }}</Button>
                            <Button
                                v-if="selectedTransaction.status === 'pending' && selectedTransaction.kind === 'charge' && !isStaff"
                                :disabled="!canMakePayment"
                                variant="destructive"
                                @click="
                                    payNow(selectedTransaction);
                                    closeDetailsDialog();
                                "
                                :title="canMakePayment ? 'Make payment' : 'No outstanding balance'"
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
