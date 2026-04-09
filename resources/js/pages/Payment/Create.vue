<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { useDataFormatting } from '@/composables/useDataFormatting';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { AlertCircle, Building2, CheckCircle, Clock, CreditCard, Smartphone, Upload, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const { formatCurrency } = useDataFormatting();

// ─── Types ───────────────────────────────────────────────────────────────────
interface PaymentTerm {
    id: number;
    term_name: string;
    term_order: number;
    percentage: number;
    amount: number;
    balance: number;
    due_date: string | null;
    status: string;
    remarks: string | null;
    paid_date: string | null;
}

interface PendingApprovalPayment {
    id: number;
    reference: string;
    amount: number;
    selected_term_id: number | null;
    term_name: string;
    created_at: string;
}

interface Props {
    studentName: string;
    studentId: number;
    assessmentId: number | null;
    outstandingBalance: number;
    paymentTerms: PaymentTerm[];
    latestAssessment?: any;
    pendingApprovalPayments: PendingApprovalPayment[];
}

// ─── Props ───────────────────────────────────────────────────────────────────
const props = withDefaults(defineProps<Props>(), {
    paymentTerms: () => [],
    pendingApprovalPayments: () => [],
});

// ─── State ───────────────────────────────────────────────────────────────────
type Tab = 'gcash' | 'bank';
const activeTab  = ref<Tab>('gcash');
const isLoading  = ref(false);
const errorMsg   = ref('');
const successMsg = ref('');

// GCash/Maya form
const gcashForm = ref({
    amount : 0,
    method : 'gcash' as 'gcash' | 'paymaya',
});

// Bank Transfer form
const bankForm = ref({
    amount           : 0,
    reference_number : '',
    description      : '',
    proof_file       : null as File | null,
    proof_preview    : '' as string,
});

// Bank details (fetched on mount)
const bankDetails = ref({ bank: '', account_name: '', account_number: '', branch: '' });

// ─── Navigation ──────────────────────────────────────────────────────────────
const breadcrumbs = [
    { title: 'Dashboard', href: route('student.dashboard') },
    { title: 'My Account', href: route('student.account') },
    { title: 'Make Payment' },
];

// ─── Computed ────────────────────────────────────────────────────────────────
const availableTerms = computed(() =>
    props.paymentTerms.filter(t => t.balance > 0).sort((a, b) => a.term_order - b.term_order)
);

const effectiveBalance = computed(() => {
    const totalBalance = props.paymentTerms.reduce((s, t) => s + t.balance, 0);
    const totalPending = props.pendingApprovalPayments.reduce((s, p) => s + p.amount, 0);
    return Math.max(0, Math.round((totalBalance - totalPending) * 100) / 100);
});

const hasPendingPayments = computed(() => props.pendingApprovalPayments.length > 0);

const firstUnpaidTerm = computed(() => availableTerms.value[0] ?? null);

const gcashAmountError = computed(() => {
    const amt = Number(gcashForm.value.amount) || 0;
    if (amt <= 0) return '';
    if (amt < 100) return 'Minimum amount is ₱100.00 for GCash/Maya.';
    if (amt > effectiveBalance.value) return `Amount cannot exceed ₱${formatCurrency(effectiveBalance.value)}.`;
    return '';
});

const bankAmountError = computed(() => {
    const amt = Number(bankForm.value.amount) || 0;
    if (amt <= 0) return '';
    if (amt > effectiveBalance.value) return `Amount cannot exceed ₱${formatCurrency(effectiveBalance.value)}.`;
    return '';
});

const canSubmitGcash = computed(() =>
    effectiveBalance.value > 0 &&
    gcashForm.value.amount >= 100 &&
    !gcashAmountError.value &&
    !isLoading.value
);

const canSubmitBank = computed(() =>
    effectiveBalance.value > 0 &&
    bankForm.value.amount > 0 &&
    bankForm.value.reference_number.trim() !== '' &&
    bankForm.value.proof_file !== null &&
    !bankAmountError.value &&
    !isLoading.value
);

// ─── Helpers ─────────────────────────────────────────────────────────────────
const formatDate = (date: string) =>
    new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

const isOverdue = (dueDate: string) => {
    const d = new Date(dueDate);
    const t = new Date();
    d.setHours(0,0,0,0); t.setHours(0,0,0,0);
    return d < t;
};

const getCsrfToken = (): string =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

// Fetch bank details on tab switch
const fetchBankDetails = async () => {
    if (bankDetails.value.bank) return;
    try {
        const res = await fetch('/api/payments/bank-details', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            credentials: 'include',
        });
        if (res.ok) bankDetails.value = await res.json();
    } catch (e) { /* silent */ }
};

const switchTab = (tab: Tab) => {
    activeTab.value = tab;
    errorMsg.value  = '';
    successMsg.value = '';
    if (tab === 'bank') fetchBankDetails();
};

// Proof of payment file handler
const onFileChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    bankForm.value.proof_file = file;
    if (file) {
        const reader = new FileReader();
        reader.onload = ev => { bankForm.value.proof_preview = ev.target?.result as string; };
        reader.readAsDataURL(file);
    } else {
        bankForm.value.proof_preview = '';
    }
};

const removeProof = () => {
    bankForm.value.proof_file    = null;
    bankForm.value.proof_preview = '';
};

// ─── Submit: GCash / Maya ────────────────────────────────────────────────────
const submitGcash = async () => {
    if (!canSubmitGcash.value) return;
    isLoading.value = true;
    errorMsg.value  = '';

    try {
        const res = await fetch('/api/payments/gcash-maya', {
            method : 'POST',
            headers: {
                'Content-Type' : 'application/json',
                'Accept'       : 'application/json',
                'X-CSRF-TOKEN' : getCsrfToken(),
            },
            credentials: 'include',
            body: JSON.stringify({
                amount                : gcashForm.value.amount,
                method                : gcashForm.value.method,
                student_id            : props.studentId,
                student_assessment_id : props.assessmentId,
                description           : `CCDI Tuition – ${props.studentName}`,
            }),
        });

        const data = await res.json();

        if (!res.ok) {
            errorMsg.value = data.message ?? 'Payment creation failed. Please try again.';
            return;
        }

        // Redirect to PayMongo checkout
        window.location.href = data.checkout_url;

    } catch (e) {
        errorMsg.value = 'Network error. Please check your connection and try again.';
    } finally {
        isLoading.value = false;
    }
};

// ─── Submit: Bank Transfer ───────────────────────────────────────────────────
const submitBank = async () => {
    if (!canSubmitBank.value) return;
    isLoading.value  = true;
    errorMsg.value   = '';
    successMsg.value = '';

    try {
        const fd = new FormData();
        fd.append('student_id',            String(props.studentId));
        fd.append('student_assessment_id', String(props.assessmentId ?? ''));
        fd.append('amount',                String(bankForm.value.amount));
        fd.append('reference_number',      bankForm.value.reference_number);
        fd.append('description',           bankForm.value.description || 'PNB Bank Transfer');
        fd.append('proof_of_payment',      bankForm.value.proof_file!);

        const res = await fetch('/api/payments/bank-transfer', {
            method     : 'POST',
            headers    : { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            credentials: 'include',
            body       : fd,
        });

        const data = await res.json();

        if (!res.ok) {
            errorMsg.value = data.message ?? 'Submission failed. Please try again.';
            return;
        }

        successMsg.value = 'Bank transfer submitted! Accounting will verify your payment shortly.';
        bankForm.value   = { amount: 0, reference_number: '', description: '', proof_file: null, proof_preview: '' };

        setTimeout(() => router.visit(route('student.account', { tab: 'history' })), 2500);

    } catch (e) {
        errorMsg.value = 'Network error. Please check your connection and try again.';
    } finally {
        isLoading.value = false;
    }
};
</script>

<template>
    <Head title="Make Payment" />
    <AppLayout>
        <div class="mx-auto max-w-2xl space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Make Payment</h1>
                <p class="text-gray-500">Choose your preferred payment method below</p>
            </div>

            <!-- Balance Summary -->
            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Outstanding Balance</p>
                    <p class="mt-1 text-3xl font-bold" :class="outstandingBalance > 0 ? 'text-red-600' : 'text-green-600'">
                        ₱{{ formatCurrency(outstandingBalance) }}
                    </p>
                </div>
                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Available to Pay</p>
                    <p class="mt-1 text-3xl font-bold text-indigo-600">₱{{ formatCurrency(effectiveBalance) }}</p>
                    <p v-if="hasPendingPayments" class="mt-1 text-xs text-amber-600">
                        (₱{{ formatCurrency(outstandingBalance - effectiveBalance) }} awaiting approval)
                    </p>
                </div>
            </div>

            <!-- Fully Paid -->
            <div v-if="outstandingBalance <= 0" class="rounded-lg border border-green-200 bg-green-50 p-4">
                <div class="flex items-center gap-2">
                    <CheckCircle class="h-5 w-5 text-green-600" />
                    <p class="font-semibold text-green-800">Account fully paid! No outstanding balance.</p>
                </div>
            </div>

            <!-- Pending Payments Banner -->
            <div v-if="hasPendingPayments" class="rounded-lg border border-amber-300 bg-amber-50 p-4">
                <div class="mb-2 flex items-center gap-2">
                    <Clock class="h-5 w-5 text-amber-600" />
                    <p class="font-semibold text-amber-900">Payments Awaiting Approval ({{ pendingApprovalPayments.length }})</p>
                </div>
                <div class="space-y-1.5">
                    <div v-for="p in pendingApprovalPayments" :key="p.id"
                         class="flex items-center justify-between rounded border border-amber-200 bg-white px-3 py-2 text-sm">
                        <div>
                            <p class="font-medium text-gray-800">{{ p.term_name }}</p>
                            <p class="text-xs text-gray-500">{{ p.reference }} · {{ formatDate(p.created_at) }}</p>
                        </div>
                        <span class="font-semibold text-amber-700">₱{{ formatCurrency(p.amount) }}</span>
                    </div>
                </div>
            </div>

            <!-- Next Due Term -->
            <div v-if="firstUnpaidTerm" class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm">
                <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-blue-600">Next Payment Due</p>
                <div class="flex items-center justify-between">
                    <p class="font-semibold text-gray-900">{{ firstUnpaidTerm.term_name }}</p>
                    <p class="font-bold text-blue-700">₱{{ formatCurrency(firstUnpaidTerm.balance) }}</p>
                </div>
                <p v-if="firstUnpaidTerm.due_date" class="mt-1 text-xs"
                   :class="isOverdue(firstUnpaidTerm.due_date) ? 'text-red-600 font-semibold' : 'text-gray-500'">
                    Due: {{ formatDate(firstUnpaidTerm.due_date) }}
                    <span v-if="isOverdue(firstUnpaidTerm.due_date)"> ⚠ Overdue</span>
                </p>
            </div>

            <!-- Payment Form (only if there is balance) -->
            <template v-if="outstandingBalance > 0">

                <!-- Tab Switcher -->
                <div class="flex rounded-xl border bg-gray-100 p-1">
                    <button type="button" @click="switchTab('gcash')"
                            class="flex flex-1 items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-medium transition-all"
                            :class="activeTab === 'gcash'
                                ? 'bg-white text-indigo-700 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700'">
                        <Smartphone class="h-4 w-4" />
                        GCash / Maya
                    </button>
                    <button type="button" @click="switchTab('bank')"
                            class="flex flex-1 items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-medium transition-all"
                            :class="activeTab === 'bank'
                                ? 'bg-white text-indigo-700 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700'">
                        <Building2 class="h-4 w-4" />
                        Bank Transfer
                    </button>
                </div>

                <!-- Error / Success Messages -->
                <div v-if="errorMsg" class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                    <AlertCircle class="mt-0.5 h-4 w-4 flex-shrink-0" />
                    <span>{{ errorMsg }}</span>
                </div>
                <div v-if="successMsg" class="flex items-start gap-2 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    <CheckCircle class="mt-0.5 h-4 w-4 flex-shrink-0" />
                    <span>{{ successMsg }}</span>
                </div>

                <!-- ── TAB: GCash / Maya ── -->
                <div v-if="activeTab === 'gcash'" class="space-y-5 rounded-xl border bg-white p-6 shadow-sm">
                    <div class="flex items-center gap-2">
                        <Smartphone class="h-5 w-5 text-indigo-600" />
                        <h2 class="text-lg font-semibold text-gray-900">GCash / Maya Payment</h2>
                    </div>
                    <p class="text-sm text-gray-500">
                        You will be redirected to the PayMongo secure checkout to complete your payment via GCash or Maya.
                    </p>

                    <!-- Student (read-only) -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Student Name</label>
                        <input type="text" :value="studentName" disabled
                               class="w-full rounded-lg border bg-gray-50 px-4 py-2 text-gray-600" />
                    </div>

                    <!-- Method -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Payment Method <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" @click="gcashForm.method = 'gcash'"
                                    class="flex items-center justify-center gap-2 rounded-lg border py-3 text-sm font-medium transition-all"
                                    :class="gcashForm.method === 'gcash'
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                        : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                                <Smartphone class="h-4 w-4" /> GCash
                            </button>
                            <button type="button" @click="gcashForm.method = 'paymaya'"
                                    class="flex items-center justify-center gap-2 rounded-lg border py-3 text-sm font-medium transition-all"
                                    :class="gcashForm.method === 'paymaya'
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                        : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                                <CreditCard class="h-4 w-4" /> Maya
                            </button>
                        </div>
                    </div>

                    <!-- Amount -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Amount <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute top-2.5 left-3 text-gray-500">₱</span>
                            <input v-model.number="gcashForm.amount" type="number" step="0.01" min="100"
                                   :max="effectiveBalance" placeholder="0.00"
                                   class="w-full rounded-lg border py-2 pr-4 pl-8 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                   :class="{ 'border-red-400': gcashAmountError }" />
                        </div>
                        <p v-if="gcashAmountError" class="mt-1 text-sm text-red-600">{{ gcashAmountError }}</p>
                        <p v-else class="mt-1 text-xs text-gray-500">
                            Maximum: ₱{{ formatCurrency(effectiveBalance) }} · Minimum: ₱100.00
                        </p>
                    </div>

                    <!-- Buttons -->
                    <div class="flex gap-3 border-t pt-4">
                        <button type="button" @click="router.visit(route('student.account'))"
                                class="flex-1 rounded-lg border bg-white px-6 py-2.5 font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="button" @click="submitGcash" :disabled="!canSubmitGcash"
                                class="flex-1 rounded-lg bg-indigo-600 px-6 py-2.5 font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                            <span v-if="isLoading">Redirecting…</span>
                            <span v-else>Pay via {{ gcashForm.method === 'gcash' ? 'GCash' : 'Maya' }} →</span>
                        </button>
                    </div>
                </div>

                <!-- ── TAB: Bank Transfer ── -->
                <div v-if="activeTab === 'bank'" class="space-y-5 rounded-xl border bg-white p-6 shadow-sm">
                    <div class="flex items-center gap-2">
                        <Building2 class="h-5 w-5 text-indigo-600" />
                        <h2 class="text-lg font-semibold text-gray-900">Bank Transfer (PNB)</h2>
                    </div>

                    <!-- Bank Details -->
                    <div v-if="bankDetails.bank" class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm space-y-1">
                        <p class="font-semibold text-gray-800">{{ bankDetails.bank }}</p>
                        <p class="text-gray-600">Account Name: <span class="font-medium text-gray-900">{{ bankDetails.account_name }}</span></p>
                        <p class="text-gray-600">Account Number: <span class="font-medium text-gray-900 select-all">{{ bankDetails.account_number }}</span></p>
                        <p v-if="bankDetails.branch" class="text-gray-600">Branch: <span class="font-medium text-gray-900">{{ bankDetails.branch }}</span></p>
                    </div>
                    <div v-else class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-500">
                        Loading bank details…
                    </div>

                    <!-- Student (read-only) -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Student Name</label>
                        <input type="text" :value="studentName" disabled
                               class="w-full rounded-lg border bg-gray-50 px-4 py-2 text-gray-600" />
                    </div>

                    <!-- Amount -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Amount Transferred <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute top-2.5 left-3 text-gray-500">₱</span>
                            <input v-model.number="bankForm.amount" type="number" step="0.01" min="1"
                                   :max="effectiveBalance" placeholder="0.00"
                                   class="w-full rounded-lg border py-2 pr-4 pl-8 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                   :class="{ 'border-red-400': bankAmountError }" />
                        </div>
                        <p v-if="bankAmountError" class="mt-1 text-sm text-red-600">{{ bankAmountError }}</p>
                        <p v-else class="mt-1 text-xs text-gray-500">Maximum: ₱{{ formatCurrency(effectiveBalance) }}</p>
                    </div>

                    <!-- Reference Number -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Reference / Transaction Number <span class="text-red-500">*</span>
                        </label>
                        <input v-model="bankForm.reference_number" type="text" maxlength="100"
                               placeholder="e.g. 20240901-123456"
                               class="w-full rounded-lg border px-4 py-2 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                    </div>

                    <!-- Description (optional) -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Description <span class="text-gray-400">(optional)</span></label>
                        <input v-model="bankForm.description" type="text" maxlength="255"
                               placeholder="e.g. 1st term tuition"
                               class="w-full rounded-lg border px-4 py-2 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                    </div>

                    <!-- Proof of Payment Upload -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Proof of Payment <span class="text-red-500">*</span>
                        </label>

                        <!-- Preview if file selected -->
                        <div v-if="bankForm.proof_preview" class="relative mb-2 inline-block">
                            <img v-if="bankForm.proof_file?.type.startsWith('image/')"
                                 :src="bankForm.proof_preview" alt="Proof"
                                 class="h-32 rounded-lg border object-cover" />
                            <div v-else class="flex items-center gap-2 rounded-lg border bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                <Upload class="h-4 w-4" />
                                {{ bankForm.proof_file?.name }}
                            </div>
                            <button type="button" @click="removeProof"
                                    class="absolute -top-2 -right-2 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white shadow">
                                <X class="h-3 w-3" />
                            </button>
                        </div>

                        <!-- File input -->
                        <label v-if="!bankForm.proof_file"
                               class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-6 transition-colors hover:border-indigo-400 hover:bg-indigo-50">
                            <Upload class="mb-2 h-8 w-8 text-gray-400" />
                            <p class="text-sm font-medium text-gray-700">Click to upload proof of payment</p>
                            <p class="text-xs text-gray-500">JPG, PNG, or PDF · Max 5MB</p>
                            <input type="file" accept=".jpg,.jpeg,.png,.pdf" class="hidden" @change="onFileChange" />
                        </label>
                    </div>

                    <!-- Buttons -->
                    <div class="flex gap-3 border-t pt-4">
                        <button type="button" @click="router.visit(route('student.account'))"
                                class="flex-1 rounded-lg border bg-white px-6 py-2.5 font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="button" @click="submitBank" :disabled="!canSubmitBank"
                                class="flex-1 rounded-lg bg-indigo-600 px-6 py-2.5 font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                            <span v-if="isLoading">Submitting…</span>
                            <span v-else>Submit Bank Transfer</span>
                        </button>
                    </div>
                </div>

            </template>

            <!-- Info Note -->
            <div class="rounded border-l-4 border-blue-500 bg-blue-50 p-4 text-sm text-blue-800">
                <strong>Note:</strong>
                GCash/Maya payments are processed instantly via PayMongo.
                Bank transfer payments require verification by the accounting department before being applied to your account.
            </div>
        </div>
    </AppLayout>
</template>