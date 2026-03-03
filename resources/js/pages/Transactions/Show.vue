<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Head, Link } from '@inertiajs/vue3';

interface Props {
    transaction: {
        id: number;
        reference: string;
        amount: number;
        status: string;
        type: string;
        kind?: 'charge' | 'payment';
        year?: string;
        semester?: string;
        subtype?: string | null;
        created_at: string;
        paid_at?: string;
        payment_channel?: string;
        user?: { id: number; name: string; email: string };
    };
    account?: {
        balance: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Transactions', href: route('transactions.index') },
    { title: `#${props.transaction.reference || props.transaction.id}`, href: route('transactions.show', props.transaction.id) },
];

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const overallRemainingBalance = Math.max(0, Math.abs(props.account?.balance || 0));
</script>

<template>
    <div class="w-full p-6">
        <Head :title="`Transaction ${props.transaction.reference}`" />
        <Breadcrumbs :items="breadcrumbs" class="mb-4" />

        <div class="space-y-6 rounded-xl bg-white p-6 shadow-md">
            <!-- Header -->
            <div>
                <h1 class="mb-2 text-2xl font-bold">Transaction #{{ props.transaction.reference || props.transaction.id }}</h1>
                <p class="text-gray-600">{{ formatDate(props.transaction.created_at) }}</p>
            </div>

            <!-- Main Details -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div>
                    <p class="text-sm font-medium text-gray-600">Type</p>
                    <p class="text-lg font-semibold">{{ props.transaction.type }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Category</p>
                    <span
                        class="mt-1 inline-block rounded-full px-2 py-1 text-xs font-semibold"
                        :class="props.transaction.kind === 'charge' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'"
                    >
                        {{ props.transaction.kind || 'transaction' }}
                    </span>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Amount</p>
                    <p class="text-2xl font-bold" :class="props.transaction.kind === 'charge' ? 'text-red-600' : 'text-green-600'">
                        {{ props.transaction.kind === 'charge' ? '+' : '-' }}{{ formatCurrency(props.transaction.amount) }}
                    </p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Status</p>
                    <span
                        class="mt-1 inline-block rounded-full px-2 py-1 text-xs font-semibold"
                        :class="{
                            'bg-green-100 text-green-800': props.transaction.status === 'paid',
                            'bg-yellow-100 text-yellow-800': props.transaction.status === 'pending',
                            'bg-red-100 text-red-800': props.transaction.status === 'failed',
                            'bg-gray-100 text-gray-800': props.transaction.status === 'cancelled',
                        }"
                    >
                        {{ props.transaction.status }}
                    </span>
                </div>
            </div>

            <!-- Year and Semester -->
            <div v-if="props.transaction.year || props.transaction.semester" class="border-t pt-4">
                <p class="mb-2 text-sm font-medium text-gray-600">Academic Term</p>
                <p class="text-lg font-semibold">{{ props.transaction.year }} {{ props.transaction.semester }}</p>
            </div>

            <!-- Payment Information (if payment) -->
            <div v-if="props.transaction.kind === 'payment'" class="border-t pt-4">
                <h3 class="mb-3 text-lg font-semibold">Payment Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Payment Method</p>
                        <p class="font-semibold capitalize">{{ props.transaction.payment_channel || 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Payment Date</p>
                        <p class="font-semibold">{{ props.transaction.paid_at ? formatDate(props.transaction.paid_at) : 'N/A' }}</p>
                    </div>
                </div>
            </div>

            <!-- Overall Balance (for students) -->
            <div class="rounded-lg border-t bg-blue-50 p-4 pt-4">
                <p class="text-sm font-medium text-gray-600">Overall Remaining Balance</p>
                <p class="mt-2 text-3xl font-bold" :class="overallRemainingBalance > 0 ? 'text-red-600' : 'text-green-600'">
                    {{ formatCurrency(overallRemainingBalance) }}
                </p>
            </div>
        </div>

        <div class="mt-4">
            <Link :href="route('transactions.index')" class="text-blue-600 hover:underline">← Back to Transactions</Link>
        </div>
    </div>
</template>
