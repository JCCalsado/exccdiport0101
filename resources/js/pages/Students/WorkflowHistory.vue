<template>
    <Head title="Workflow History" />

    <AppLayout>
        <div class="w-full space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div>
                <h1 class="text-3xl font-bold">Workflow History</h1>
                <p class="text-gray-500">View the approval workflow history for this student</p>
            </div>

            <!-- Timeline View -->
            <div class="space-y-4">
                <div v-if="workflows && workflows.length > 0">
                    <div v-for="(workflow, index) in workflows" :key="workflow.id" class="relative">
                        <!-- Timeline Connector -->
                        <div v-if="index < workflows.length - 1" class="absolute top-12 left-6 h-12 w-0.5 bg-gray-300"></div>

                        <!-- Timeline Item -->
                        <div class="flex gap-4">
                            <!-- Dot -->
                            <div
                                class="z-10 flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full font-bold text-white"
                                :class="{
                                    'bg-green-500': workflow.status === 'approved',
                                    'bg-red-500': workflow.status === 'rejected',
                                    'bg-yellow-500': workflow.status === 'in_progress',
                                    'bg-gray-400': workflow.status === 'pending',
                                }"
                            >
                                {{ index + 1 }}
                            </div>

                            <!-- Content -->
                            <div class="flex-1 rounded-lg border bg-white p-4 shadow-sm">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h3 class="text-lg font-semibold">{{ workflow.workflow_type }}</h3>
                                        <p class="mt-1 text-sm text-gray-600">{{ workflow.description }}</p>
                                    </div>
                                    <span
                                        :class="{
                                            'bg-green-100 text-green-800': workflow.status === 'approved',
                                            'bg-red-100 text-red-800': workflow.status === 'rejected',
                                            'bg-yellow-100 text-yellow-800': workflow.status === 'in_progress',
                                            'bg-gray-100 text-gray-800': workflow.status === 'pending',
                                        }"
                                        class="rounded-full px-3 py-1 text-sm font-semibold"
                                    >
                                        {{ workflow.status }}
                                    </span>
                                </div>

                                <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-600">Started</p>
                                        <p class="font-semibold">{{ formatDate(workflow.created_at) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-600">
                                            {{ workflow.status === 'approved' || workflow.status === 'rejected' ? 'Completed' : 'Updated' }}
                                        </p>
                                        <p class="font-semibold">{{ formatDate(workflow.updated_at) }}</p>
                                    </div>
                                </div>

                                <div v-if="workflow.approver_name" class="mt-4 border-t pt-4">
                                    <p class="text-sm text-gray-600">Approved by</p>
                                    <p class="font-semibold">{{ workflow.approver_name }}</p>
                                </div>

                                <div v-if="workflow.comment" class="mt-4">
                                    <p class="text-sm text-gray-600">Comments</p>
                                    <p class="mt-1 rounded bg-gray-50 p-3 text-sm">{{ workflow.comment }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="rounded-lg border bg-gray-50 py-12 text-center">
                    <p class="text-lg text-gray-500">No workflow history available</p>
                    <p class="mt-2 text-sm text-gray-400">This student has not been through any approval workflows yet.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

interface Workflow {
    id: number;
    workflow_type: string;
    description: string;
    status: string;
    approver_name: string | null;
    comment: string | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    student_id: string;
    workflows: Workflow[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('admin.dashboard') },
    { title: 'Students', href: route('students.index') },
    { title: `${props.student_id} - Workflow History` },
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
</script>
