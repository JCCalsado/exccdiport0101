<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const { student } = defineProps<{ student: any }>();

const payment = reactive({
    amount: '',
    description: '',
    payment_method: 'gcash',
    reference_number: '',
});

function addPayment() {
    router.post(`/students/${student.id}/payments`, payment, {
        onSuccess: () => {
            // Reset form
            payment.amount = '';
            payment.description = '';
            payment.reference_number = '';
        },
    });
}

const remainingBalance = computed(() => {
    if (!student.payments) return student.total_balance;

    const totalPaid = student.payments.reduce((sum: number, payment: any) => {
        return sum + parseFloat(payment.amount);
    }, 0);

    return student.total_balance - totalPaid;
});
</script>

<template>
    <Head :title="student.name" />

    <AppLayout>
        <div class="mx-auto max-w-4xl p-6">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-800">{{ student.name }}</h1>
                <p class="text-gray-500">Account ID: {{ student.student_id }}</p>
            </div>

            <!-- Student Info -->
            <div class="mb-6 rounded-xl bg-white p-6 shadow">
                <h2 class="mb-4 text-xl font-medium text-gray-800">Student Information</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div><span class="font-medium">Email:</span> {{ student.email }}</div>
                    <div><span class="font-medium">Course:</span> {{ student.course }}</div>
                    <div><span class="font-medium">Year:</span> {{ student.year_level }}</div>
                    <div><span class="font-medium">Phone:</span> {{ student.phone || 'N/A' }}</div>
                    <div class="md:col-span-2"><span class="font-medium">Address:</span> {{ student.address || 'N/A' }}</div>
                </div>

                <!-- Balance -->
                <div class="mt-4 border-t pt-4">
                    <p class="text-lg font-semibold">
                        Remaining Balance:
                        <span :class="remainingBalance > 0 ? 'text-red-600' : 'text-green-600'"> ₱{{ Math.abs(remainingBalance).toFixed(2) }} </span>
                    </p>
                </div>
            </div>

            <!-- Payments -->
            <div class="rounded-xl bg-white p-6 shadow">
                <h2 class="mb-4 text-xl font-medium text-gray-800">Payment History</h2>

                <!-- Payment List -->
                <div v-if="student.payments.length" class="mb-6 divide-y">
                    <div v-for="payment in student.payments" :key="payment.id" class="flex items-center justify-between py-3">
                        <div>
                            <p class="font-medium">₱{{ payment.amount }}</p>
                            <p class="text-sm text-gray-600">{{ payment.description }}</p>
                            <p class="text-xs text-gray-500">{{ payment.payment_method }} • {{ payment.reference_number }}</p>
                        </div>
                        <span class="text-sm text-gray-500">{{ new Date(payment.created_at).toLocaleDateString() }}</span>
                    </div>
                </div>
                <p v-else class="mb-6 text-gray-500">No payments recorded yet.</p>

                <!-- Add Payment Form -->
                <form @submit.prevent="addPayment" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <input
                        v-model="payment.amount"
                        type="number"
                        placeholder="Amount"
                        class="rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                        required
                    />
                    <input
                        v-model="payment.description"
                        placeholder="Description"
                        class="rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                        required
                    />
                    <select v-model="payment.payment_method" class="rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="bank">Bank Transfer</option>
                    </select>
                    <input
                        v-model="payment.reference_number"
                        placeholder="Reference No."
                        class="rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                    />
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 md:col-span-4">Add Payment</button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
