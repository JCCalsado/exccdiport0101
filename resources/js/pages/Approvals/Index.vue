<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface WorkflowMeta {
    transaction_id: number;
    amount: number;
    payment_method: string;
    term_name: string;
    year?: number | string;
    semester?: string;
    student_user_id: number;
    submitted_at: string;
}

interface Approval {
    id: number;
    step_name: string;
    status: 'pending' | 'approved' | 'rejected';
    comments: string | null;
    created_at: string;
    workflow_instance: {
        metadata: WorkflowMeta;
        workflow: { name: string };
        workflowable: {
            reference: string;
            amount: number;
            user?: { first_name: string; last_name: string; student_id: string };
        };
    };
}

const props = defineProps<{
    approvals: { data: Approval[]; links: any[] };
    filters: { status?: string; year?: string; semester?: string };
}>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('accounting.dashboard') },
    { title: 'Payment Approvals', href: route('approvals.index') },
];

const filters = ref({ ...props.filters });
const searchQuery = ref('');
const showRejectDialog = ref(false);
const selectedApprovalId = ref<number | null>(null);

const rejectForm = useForm({ comments: '' });

// Extract unique years from approvals
const uniqueYears = computed(() => {
    const years = new Set<string | number>();
    props.approvals.data.forEach((approval) => {
        const year = approval.workflow_instance.metadata?.year;
        if (year) years.add(year);
    });
    return Array.from(years).sort((a, b) => {
        const aNum = typeof a === 'string' ? parseInt(a) : a;
        const bNum = typeof b === 'string' ? parseInt(b) : b;
        return bNum - aNum;
    });
});

// Extract unique semesters from approvals
const uniqueSemesters = computed(() => {
    const semesters = new Set<string>();
    props.approvals.data.forEach((approval) => {
        const semester = approval.workflow_instance.metadata?.semester;
        if (semester) semesters.add(semester);
    });
    return Array.from(semesters).sort();
});

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);

const formatDate = (date: string) => new Date(date).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });

const formatMethod = (method: string) =>
    ({
        cash: 'Cash',
        gcash: 'GCash',
        bank_transfer: 'Bank Transfer',
        credit_card: 'Credit Card',
        debit_card: 'Debit Card',
    })[method] ?? method;

const getStudentName = (approval: Approval) => {
    const w = approval.workflow_instance.workflowable;
    if (w.user) return `${w.user.last_name}, ${w.user.first_name}`;
    return 'Unknown Student';
};

// Filter approvals by search query, year, semester, and status
const filteredApprovals = computed(() => {
    let result = props.approvals.data;

    // Filter by year
    if (filters.value.year) {
        result = result.filter((approval) => String(approval.workflow_instance.metadata?.year) === filters.value.year);
    }

    // Filter by semester
    if (filters.value.semester) {
        result = result.filter((approval) => approval.workflow_instance.metadata?.semester === filters.value.semester);
    }

    // Filter by search query
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter((approval) => {
            const studentName = getStudentName(approval).toLowerCase();
            const ref = approval.workflow_instance.workflowable.reference.toLowerCase();
            const studentId = approval.workflow_instance.workflowable.user?.student_id?.toLowerCase() ?? '';
            return studentName.includes(query) || ref.includes(query) || studentId.includes(query);
        });
    }

    return result;
});

const applyFilter = () => {
    router.get(route('approvals.index'), filters.value, { preserveState: true, replace: true });
};

const approve = (approvalId: number) => {
    router.post(
        route('approvals.approve', approvalId),
        {},
        {
            preserveScroll: true,
            onSuccess: () => router.reload(),
        },
    );
};

const openRejectDialog = (approvalId: number) => {
    selectedApprovalId.value = approvalId;
    rejectForm.reset();
    showRejectDialog.value = true;
};

const submitRejection = () => {
    if (!selectedApprovalId.value) return;
    rejectForm.post(route('approvals.reject', selectedApprovalId.value), {
        onSuccess: () => {
            showRejectDialog.value = false;
            router.reload();
        },
    });
};
</script>

<template>
    <Head title="Payment Approvals" />
    <AppLayout>
        <div class="w-full space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <div>
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold">Payment Approvals</h1>
                        <p class="text-gray-500">Review and verify student payment submissions</p>
                    </div>
                </div>

                <!-- Filters Row -->
                <div class="flex flex-col gap-4 md:flex-row">
                    <!-- Search Field -->
                    <div class="relative flex-1">
                        <Search class="absolute top-3 left-3 text-gray-400" :size="18" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search by student name, ID, or reference..."
                            class="w-full rounded-lg border border-gray-300 py-2 pr-4 pl-10 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <!-- Year Dropdown -->
                    <select
                        v-model="filters.year"
                        @change="applyFilter"
                        class="min-w-[120px] rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All Years</option>
                        <option v-for="year in uniqueYears" :key="year" :value="String(year)">
                            {{ year }}
                        </option>
                    </select>

                    <!-- Semester Dropdown -->
                    <select
                        v-model="filters.semester"
                        @change="applyFilter"
                        class="min-w-[120px] rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All Semesters</option>
                        <option v-for="semester in uniqueSemesters" :key="semester" :value="semester">
                            {{ semester }}
                        </option>
                    </select>

                    <!-- Status Filter Dropdown -->
                    <select
                        v-model="filters.status"
                        @change="applyFilter"
                        class="min-w-[120px] rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>

            <div v-if="filteredApprovals.length === 0" class="py-16 text-center text-gray-400">
                {{ searchQuery || filters.year || filters.semester || filters.status ? 'No approvals match your filters.' : 'No approvals found.' }}
            </div>

            <div v-else class="space-y-4">
                <div v-for="approval in filteredApprovals" :key="approval.id" class="rounded-xl border bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 space-y-1">
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-semibold">{{ getStudentName(approval) }}</h3>
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="{
                                        'bg-yellow-100 text-yellow-800': approval.status === 'pending',
                                        'bg-green-100 text-green-800': approval.status === 'approved',
                                        'bg-red-100 text-red-800': approval.status === 'rejected',
                                    }"
                                    >{{ approval.status }}</span
                                >
                            </div>
                            <p class="text-sm text-gray-500">
                                Ref: <span class="font-mono">{{ approval.workflow_instance.workflowable.reference }}</span>
                            </p>
                        </div>
                        <p class="text-2xl font-bold whitespace-nowrap text-blue-700">
                            {{ formatCurrency(approval.workflow_instance.metadata?.amount ?? approval.workflow_instance.workflowable.amount) }}
                        </p>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm text-gray-600 md:grid-cols-4">
                        <div>
                            <p class="text-xs tracking-wide text-gray-400 uppercase">Term</p>
                            <p>{{ approval.workflow_instance.metadata?.term_name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs tracking-wide text-gray-400 uppercase">Method</p>
                            <p>{{ formatMethod(approval.workflow_instance.metadata?.payment_method ?? '') }}</p>
                        </div>
                        <div>
                            <p class="text-xs tracking-wide text-gray-400 uppercase">Student ID</p>
                            <p>{{ approval.workflow_instance.workflowable.user?.student_id ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs tracking-wide text-gray-400 uppercase">Submitted</p>
                            <p>{{ formatDate(approval.created_at) }}</p>
                        </div>
                    </div>

                    <div v-if="approval.status === 'pending'" class="mt-4 flex gap-3">
                        <Button @click="approve(approval.id)" class="bg-green-600 text-white hover:bg-green-700"> ✓ Approve </Button>
                        <Button variant="outline" @click="openRejectDialog(approval.id)" class="border-red-300 text-red-600 hover:bg-red-50">
                            ✗ Declined
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Decline Dialog -->
        <Dialog v-model:open="showRejectDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Decline Payment</DialogTitle>
                    <DialogDescription>Provide a reason. The student will be notified.</DialogDescription>
                </DialogHeader>
                <div class="mt-2 space-y-4">
                    <textarea
                        v-model="rejectForm.comments"
                        class="min-h-[100px] w-full rounded-lg border p-3 text-sm"
                        placeholder="Enter rejection reason (required)..."
                    />
                    <p v-if="rejectForm.errors.comments" class="text-sm text-red-500">{{ rejectForm.errors.comments }}</p>
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" @click="showRejectDialog = false">Cancel</Button>
                        <Button variant="destructive" :disabled="rejectForm.processing || !rejectForm.comments" @click="submitRejection"
                            >Confirm Decline</Button
                        >
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
