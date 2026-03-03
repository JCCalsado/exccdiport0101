<template>
    <Head title="Make Payment" />

    <AppLayout>
        <div class="w-full space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div>
                <h1 class="text-3xl font-bold">Make Payment</h1>
                <p class="text-gray-500">Submit your payment and track its status</p>
            </div>

            <!-- Payment Information -->
            <div class="grid grid-cols-2 gap-6">
                <!-- Outstanding Balance -->
                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <p class="text-sm text-gray-600">Outstanding Balance</p>
                    <p class="mt-2 text-4xl font-bold text-red-600">₱{{ formatCurrency(outstandingBalance) }}</p>
                </div>

                <!-- Payment Method -->
                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <p class="text-sm text-gray-600">Payment Method</p>
                    <p class="mt-2 text-lg font-semibold">{{ paymentMethod || 'Select a method' }}</p>
                </div>
            </div>

            <!-- Payment Form -->
            <form @submit.prevent="submitPayment" class="space-y-6 rounded-xl border bg-white p-6 shadow-sm">
                <!-- Student Name -->
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Student Name</label>
                    <input type="text" :value="studentName" disabled class="w-full rounded-lg border bg-gray-50 p-3 text-gray-600" />
                </div>

                <!-- Amount -->
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Payment Amount</label>
                    <div class="relative">
                        <span class="absolute top-3 left-3 text-gray-600">₱</span>
                        <input
                            v-model.number="form.amount"
                            type="number"
                            step="0.01"
                            min="0"
                            :max="outstandingBalance"
                            :placeholder="formatCurrency(outstandingBalance)"
                            class="w-full rounded-lg border p-3 pl-8 outline-none focus:border-transparent focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <p v-if="form.errors.amount" class="mt-1 text-sm text-red-600">{{ form.errors.amount }}</p>
                </div>

                <!-- Payment Method Selection -->
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Payment Method</label>
                    <select
                        v-model="form.payment_method"
                        class="w-full rounded-lg border p-3 outline-none focus:border-transparent focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">Select a payment method</option>
                        <option value="online">Online Transfer</option>
                        <option value="card">Credit/Debit Card</option>
                        <option value="cash">Cash</option>
                        <option value="check">Check</option>
                    </select>
                    <p v-if="form.errors.payment_method" class="mt-1 text-sm text-red-600">{{ form.errors.payment_method }}</p>
                </div>

                <!-- Reference Number (for online transfers) -->
                <div v-if="form.payment_method === 'online'">
                    <label class="mb-2 block text-sm font-medium text-gray-700">Reference Number / Transaction ID</label>
                    <input
                        v-model="form.reference_number"
                        type="text"
                        placeholder="e.g., REMIT-12345678"
                        class="w-full rounded-lg border p-3 outline-none focus:border-transparent focus:ring-2 focus:ring-blue-500"
                    />
                    <p v-if="form.errors.reference_number" class="mt-1 text-sm text-red-600">{{ form.errors.reference_number }}</p>
                </div>

                <!-- Notes -->
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Additional Notes (Optional)</label>
                    <textarea
                        v-model="form.notes"
                        placeholder="Any additional information about this payment..."
                        rows="3"
                        class="w-full rounded-lg border p-3 outline-none focus:border-transparent focus:ring-2 focus:ring-blue-500"
                    ></textarea>
                </div>

                <!-- Terms Acceptance -->
                <div class="flex items-start gap-3">
                    <input v-model="form.terms_accepted" type="checkbox" id="terms" class="mt-1" />
                    <label for="terms" class="text-sm text-gray-700">
                        I confirm that this payment information is accurate and will be subject to verification by the accounting department.
                    </label>
                </div>
                <p v-if="form.errors.terms_accepted" class="text-sm text-red-600">{{ form.errors.terms_accepted }}</p>

                <!-- Submit Button -->
                <div class="flex gap-3 border-t pt-4">
                    <button
                        type="button"
                        @click="$router.back()"
                        class="flex-1 rounded-lg bg-gray-300 px-6 py-2 font-medium text-gray-800 transition-colors hover:bg-gray-400"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex-1 rounded-lg bg-green-600 px-6 py-2 font-medium text-white transition-colors hover:bg-green-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Submitting...' : 'Submit Payment' }}
                    </button>
                </div>
            </form>

            <!-- Info Box -->
            <div class="rounded border-l-4 border-blue-500 bg-blue-50 p-4">
                <p class="text-sm text-blue-800">
                    <strong>Note:</strong> After submission, your payment will be reviewed by the accounting department. You will receive a
                    confirmation email once it has been verified.
                </p>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    studentName: string;
    outstandingBalance: number;
}

const props = defineProps<Props>();

const form = useForm({
    amount: 0,
    payment_method: '',
    reference_number: '',
    notes: '',
    terms_accepted: false,
});

const breadcrumbs = [
    { title: 'Dashboard', href: route('student.dashboard') },
    { title: 'Account', href: route('student.account') },
    { title: 'Make Payment' },
];

const outstandingBalance = computed(() => props.outstandingBalance);
const studentName = computed(() => props.studentName);
const paymentMethod = computed(() => {
    const methods: Record<string, string> = {
        online: 'Online Transfer',
        card: 'Credit/Debit Card',
        cash: 'Cash',
        check: 'Check',
    };
    return methods[form.payment_method] || '';
});

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
};

const submitPayment = () => {
    form.post(route('account.pay-now'), {
        onSuccess: () => {
            // Success is handled by the backend redirect
        },
    });
};
</script>
