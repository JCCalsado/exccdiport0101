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

                <!-- Comment -->
                <div v-if="approval.comment" class="border-t pt-4">
                    <p class="text-sm text-gray-600">Comment</p>
                    <p class="mt-2 rounded-lg bg-gray-50 p-4">{{ approval.comment }}</p>
                </div>

                <!-- Action Buttons (if pending) -->
                <div v-if="approval.status === 'pending'" class="flex gap-3 border-t pt-4">
                    <button
                        @click="approve"
                        :disabled="processing"
                        class="rounded-lg bg-green-600 px-6 py-2 font-medium text-white transition-colors hover:bg-green-700 disabled:opacity-50"
                    >
                        {{ processing ? 'Processing...' : 'Approve' }}
                    </button>
                    <button
                        @click="openRejectDialog"
                        :disabled="processing"
                        class="rounded-lg bg-red-600 px-6 py-2 font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-50"
                    >
                        Declined
                    </button>
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
import { ref } from 'vue';

interface Approval {
    id: number;
    status: string;
    workflowable_type: string;
    approver_name: string;
    comment: string | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    approval: Approval;
}

const props = defineProps<Props>();

const processing = ref(false);
const showRejectDialog = ref(false);
const rejectReason = ref('');

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
        { comment: rejectReason.value },
        {
            onFinish: () => {
                processing.value = false;
                showRejectDialog.value = false;
            },
        },
    );
};
</script>
