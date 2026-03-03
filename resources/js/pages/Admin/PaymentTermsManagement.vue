<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertCircle, Check, Edit2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PaymentTerm {
    id: number;
    term_name: string;
    term_order: number;
    amount: number;
    balance: number;
    due_date: string | null;
    status: string;
    student_id: string;
    student_name: string;
    assessment_id: number;
}

interface Props {
    payment_terms: PaymentTerm[];
    unsetDueDatesCount: number;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: route('admin.dashboard') },
    { title: 'Payment Terms Management', href: route('admin.dashboard') },
];

const showEditDialog = ref(false);
const selectedTerm = ref<PaymentTerm | null>(null);
const filterStudentId = ref('');
const filterStatus = ref('all');

const editForm = useForm({
    due_date: '',
});

const filteredTerms = computed(() => {
    let result = props.payment_terms;

    if (filterStudentId.value) {
        result = result.filter((t) => t.student_id.toLowerCase().includes(filterStudentId.value.toLowerCase()));
    }

    if (filterStatus.value === 'unset') {
        result = result.filter((t) => !t.due_date);
    } else if (filterStatus.value === 'set') {
        result = result.filter((t) => t.due_date);
    }

    return result;
});

const unsetTermsCount = computed(() => {
    return props.payment_terms.filter((t) => !t.due_date).length;
});

const openEditDialog = (term: PaymentTerm) => {
    selectedTerm.value = term;
    editForm.due_date = term.due_date ? new Date(term.due_date).toISOString().split('T')[0] : '';
    showEditDialog.value = true;
};

const submitDueDate = () => {
    if (!selectedTerm.value || !editForm.due_date) return;

    router.post(
        route('admin.payment-terms.update-due-date', selectedTerm.value.id),
        { due_date: editForm.due_date },
        {
            onSuccess: () => {
                showEditDialog.value = false;
                editForm.reset();
            },
        },
    );
};

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);

const formatDate = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-PH', { dateStyle: 'medium' });
};
</script>

<template>
    <Head title="Payment Terms Management" />

    <AppLayout>
        <div class="w-full space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <div>
                <h1 class="text-3xl font-bold">Payment Terms Due Date Management</h1>
                <p class="mt-1 text-gray-600">Set and manage payment term due dates for students</p>
            </div>

            <!-- Alert for unset due dates -->
            <div v-if="unsetTermsCount > 0" class="flex gap-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                <AlertCircle class="flex-shrink-0 text-amber-600" :size="24" />
                <div>
                    <h3 class="mb-1 font-semibold text-amber-900">{{ unsetTermsCount }} Payment Terms Missing Due Dates</h3>
                    <p class="text-sm text-amber-800">
                        Some students have payment terms without set due dates. Please review and set appropriate deadlines.
                    </p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Total Payment Terms</p>
                                <p class="text-3xl font-bold text-blue-600">{{ props.payment_terms.length }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Missing Due Dates</p>
                                <p class="text-3xl font-bold text-amber-600">{{ unsetTermsCount }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-4 md:flex-row">
                <input
                    v-model="filterStudentId"
                    type="text"
                    placeholder="Filter by student ID..."
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                />

                <select
                    v-model="filterStatus"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm focus:border-transparent focus:ring-2 focus:ring-blue-500"
                >
                    <option value="all">All Terms</option>
                    <option value="unset">Missing Due Dates</option>
                    <option value="set">Has Due Dates</option>
                </select>
            </div>

            <!-- Payment Terms List -->
            <div class="space-y-3">
                <div v-if="filteredTerms.length === 0" class="py-12 text-center text-gray-400">No payment terms found matching your filters.</div>

                <Card v-for="term in filteredTerms" :key="term.id">
                    <CardContent class="pt-6">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex-1 space-y-2">
                                <div class="flex items-center gap-3">
                                    <h3 class="font-semibold">{{ term.student_name }} ({{ term.student_id }})</h3>
                                    <span
                                        class="rounded-full px-2 py-1 text-xs font-medium"
                                        :class="term.due_date ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                                    >
                                        {{ term.due_date ? 'Set' : 'Missing' }}
                                    </span>
                                </div>

                                <p class="text-sm text-gray-600">
                                    {{ term.term_order }}. {{ term.term_name }} —
                                    {{ formatCurrency(term.amount) }}
                                </p>

                                <div class="text-sm">
                                    <span class="text-gray-600">Due Date:</span>
                                    <span class="ml-2 font-mono font-semibold" :class="term.due_date ? 'text-gray-900' : 'text-amber-600'">
                                        {{ formatDate(term.due_date) }}
                                    </span>
                                </div>
                            </div>

                            <Button @click="openEditDialog(term)" variant="outline" class="flex-shrink-0">
                                <Edit2 :size="16" class="mr-2" />
                                Set Date
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Edit Due Date Dialog -->
        <Dialog v-model:open="showEditDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Set Payment Term Due Date</DialogTitle>
                    <DialogDescription v-if="selectedTerm"> {{ selectedTerm.student_name }} - {{ selectedTerm.term_name }} </DialogDescription>
                </DialogHeader>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Due Date *</label>
                        <input
                            v-model="editForm.due_date"
                            type="date"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                            required
                        />
                        <p v-if="editForm.errors.due_date" class="mt-2 text-sm text-red-600">{{ editForm.errors.due_date }}</p>
                    </div>

                    <div v-if="selectedTerm" class="rounded-lg border border-blue-200 bg-blue-50 p-3">
                        <p class="text-sm text-blue-900"><strong>Amount:</strong> {{ formatCurrency(selectedTerm.amount) }}</p>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button variant="outline" @click="showEditDialog = false">Cancel</Button>
                        <Button
                            @click="submitDueDate"
                            :disabled="editForm.processing || !editForm.due_date"
                            class="bg-blue-600 text-white hover:bg-blue-700"
                        >
                            <Check :size="16" class="mr-2" />
                            Set Due Date
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
