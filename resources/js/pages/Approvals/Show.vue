<template>
    <Head title="Approval Details" />

    <AppLayout>
        <div class="w-full space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Approval Details</h1>
                    <p class="text-gray-500">Review and action this workflow approval</p>
                </div>
                <button
                    @click="refreshApproval"
                    title="Refresh approval details"
                    class="rounded-lg border border-gray-300 bg-white p-2 text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900"
                >
                    <RotateCcw :size="20" />
                </button>
            </div>

            <!-- Approval Details Card -->
            <div v-if="approval" class="space-y-6 rounded-xl border bg-white p-6 shadow-sm">
                <!-- Status Badge -->
                <div class="flex items-center gap-4">
                    <span
                        :class="{
                            'bg-yellow-100 text-yellow-800': approval.status === 'pending',
                            'bg-green-100 text-green-800': approval.status === 'approved',
                            'bg-red-100 text-red-800': approval.status === 'rejected',
                        }"
                        class="rounded-full px-4 py-2 text-sm font-semibold"
                    >
                        {{ approval.status }}
                    </span>
                </div>

                <!-- Workflow Item Info -->
                <div class="grid grid-cols-2 gap-4 border-b pb-4">
                    <div>
                        <p class="text-sm text-gray-600">Type</p>
                        <p class="font-semibold">{{ approval.workflowable_type }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Created</p>
                        <p class="font-semibold">{{ formatDate(approval.created_at) }}</p>
                    </div>
                </div>

                <!-- Payment Details -->
                <div class="grid grid-cols-2 gap-4 border-b pb-4">
                    <div>
                        <p class="text-sm text-gray-600">Term</p>
                        <p class="font-semibold">{{ approval.workflow_instance?.workflowable?.meta?.term_name ?? approval.workflow_instance?.workflowable?.type ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Payment Method</p>
                        <p class="font-semibold">{{ approval.workflow_instance?.workflowable?.payment_channel ?? 'N/A' }}</p>
                    </div>
                </div>

                <!-- Approver & Metadata -->
                <div class="grid grid-cols-2 gap-4 border-b pb-4">
                    <div>
                        <p class="text-sm text-gray-600">Assigned to</p>
                        <p class="font-semibold">{{ approval.approver_name || 'Unassigned' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Last Updated</p>
                        <p class="font-semibold">{{ formatDate(approval.updated_at) }}</p>
                    </div>
                </div>

                <!-- Comments -->
                <div v-if="approval.comments" class="border-t pt-4">
                    <p class="text-sm text-gray-600">Comment</p>
                    <p class="mt-2 rounded-lg bg-gray-50 p-4">{{ approval.comments }}</p>
                </div>

                <!-- Unpaid Payment Terms -->
                <div v-if="props.unpaidTerms && props.unpaidTerms.length > 0" class="border-t pt-6">
                    <h3 class="mb-4 font-semibold text-gray-900">Other Unpaid Payment Terms</h3>
                    <div class="overflow-x-auto rounded-lg border">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border-b px-4 py-3 text-left text-sm font-semibold text-gray-700">Term</th>
                                    <th class="border-b px-4 py-3 text-left text-sm font-semibold text-gray-700">Amount</th>
                                    <th class="border-b px-4 py-3 text-left text-sm font-semibold text-gray-700">Balance</th>
                                    <th class="border-b px-4 py-3 text-left text-sm font-semibold text-gray-700">Due Date</th>
                                    <th class="border-b px-4 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="term in props.unpaidTerms" :key="term.id" class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm">{{ term.term_name }}</td>
                                    <td class="px-4 py-3 text-sm">{{ formatCurrency(term.amount) }}</td>
                                    <td class="px-4 py-3 text-sm font-semibold text-orange-600">{{ formatCurrency(term.balance) }}</td>
                                    <td class="px-4 py-3 text-sm">{{ formatDate(term.due_date) }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span :class="{
                                            'bg-yellow-100 text-yellow-800': term.status === 'pending',
                                            'bg-orange-100 text-orange-800': term.status === 'partial',
                                            'bg-green-100 text-green-800': term.status === 'paid',
                                        }" class="rounded-full px-2.5 py-1 text-xs font-semibold">
                                            {{ term.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Action Buttons (if pending) -->
                <div v-if="approval.status === 'pending'" class="border-t pt-6">
                    <p class="mb-4 text-sm font-semibold text-gray-700">Take Action</p>
                    <div class="grid grid-cols-2 gap-4">
                        <button
                            @click="approve"
                            :disabled="processing"
                            class="group relative inline-flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-green-500 to-green-600 px-6 py-3 font-semibold text-white shadow-lg transition-all duration-200 hover:from-green-600 hover:to-green-700 hover:shadow-xl hover:scale-105 disabled:scale-100 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <CheckCircle2 v-if="!processing" :size="20" class="transition-transform group-hover:scale-110" />
                            <span>{{ processing ? 'Processing...' : 'Approve' }}</span>
                        </button>
                        <button
                            @click="openRejectDialog"
                            :disabled="processing"
                            class="group relative inline-flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-red-500 to-red-600 px-6 py-3 font-semibold text-white shadow-lg transition-all duration-200 hover:from-red-600 hover:to-red-700 hover:shadow-xl hover:scale-105 disabled:scale-100 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <XCircle v-if="!processing" :size="20" class="transition-transform group-hover:scale-110" />
                            <span>{{ processing ? 'Processing...' : 'Decline' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-else class="py-12 text-center">
                <p class="text-gray-500">Loading approval details...</p>
            </div>

            <!-- Reject Reason Dialog -->
            <div v-if="showRejectDialog" class="bg-opacity-50 fixed inset-0 z-50 flex items-center justify-center bg-black">
                <div class="w-full max-w-md rounded-lg bg-white p-6">
                    <h2 class="mb-4 text-xl font-bold">Decline Approval</h2>
                    <p class="mb-4 text-gray-600">Please provide a reason for decline:</p>
                    <textarea
                        v-model="rejectReason"
                        class="w-full rounded-lg border p-3 outline-none focus:border-transparent focus:ring-2 focus:ring-blue-500"
                        placeholder="Enter rejection reason..."
                        rows="4"
                    ></textarea>
                    <div class="mt-6 flex gap-3">
                        <button
                            @click="showRejectDialog = false"
                            class="flex-1 rounded-lg bg-gray-300 px-4 py-2 font-medium transition-colors hover:bg-gray-400"
                        >
                            Cancel
                        </button>
                        <button
                            @click="reject"
                            :disabled="processing"
                            class="flex-1 rounded-lg bg-red-600 px-4 py-2 font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-50"
                        >
                            {{ processing ? 'Processing...' : 'Declined' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { RotateCcw, CheckCircle2, XCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import { useDataFormatting } from '@/composables/useDataFormatting';

interface Approval {
    id: number;
    status: string;
    workflowable_type: string;
    approver_name: string;
    comments: string | null;
    created_at: string;
    updated_at: string;
    workflow_instance?: {
        workflowable: {
            meta?: { term_name?: string };
            type?: string;
            payment_channel?: string;
        };
    };
}

interface UnpaidTerm {
    id: number;
    term_name: string;
    amount: number;
    balance: number;
    due_date: string;
    status: string;
}

interface Props {
    approval: Approval;
    unpaidTerms?: UnpaidTerm[];
}

const props = defineProps<Props>();

const processing = ref(false);
const showRejectDialog = ref(false);
const rejectReason = ref('');

const { formatCurrency, formatDate: formatDateUtil } = useDataFormatting();

const breadcrumbs = [
    { title: 'Dashboard', href: route('admin.dashboard') },
    { title: 'Approvals', href: route('approvals.index') },
    { title: 'Details' },
];

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const approve = async () => {
    processing.value = true;
    router.post(
        route('approvals.approve', props.approval.id),
        {},
        {
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};

const openRejectDialog = () => {
    showRejectDialog.value = true;
};

const reject = async () => {
    if (!rejectReason.value.trim()) {
        alert('Please provide a rejection reason');
        return;
    }

    processing.value = true;
    router.post(
        route('approvals.reject', props.approval.id),
        { comments: rejectReason.value },
        {
            onFinish: () => {
                processing.value = false;
                showRejectDialog.value = false;
            },
        },
    );
};

const refreshApproval = () => {
    router.reload();
};
</script>