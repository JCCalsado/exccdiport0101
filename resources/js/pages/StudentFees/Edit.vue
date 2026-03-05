<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Student {
    id: number;
    student_id: string;
    name: string;
    email: string;
    course: string;
    year_level: string;
    status: string;
}

interface Assessment {
    id: number;
    assessment_number: string;
    year_level: string;
    semester: string;
    school_year: string;
    tuition_fee: number;
    other_fees: number;
    total_assessment: number;
    subjects: AssessmentSubject[];
    fee_breakdown: FeeBreakdownItem[];
    status: string;
}

interface AssessmentSubject {
    id: number;
    units: number;
    amount: number;
}

interface FeeBreakdownItem {
    id: number;
    amount: number;
}

interface Subject {
    id: number;
    code: string;
    name: string;
    units: number;
    price_per_unit: number;
    has_lab: boolean;
    lab_fee: number;
    total_cost: number;
}

interface Fee {
    id: number;
    name: string;
    category: string;
    amount: number;
}

interface Props {
    student: Student;
    assessment: Assessment;
    subjects: Subject[];
    fees: Fee[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Student Fee Management', href: route('student-fees.index') },
    { title: props.student.name, href: route('student-fees.show', props.student.id) },
    { title: 'Edit Assessment' },
];

// Initialize selected items from existing assessment
const selectedSubjects = ref<AssessmentSubject[]>([...(props.assessment.subjects || [])]);
const selectedFees = ref<FeeBreakdownItem[]>([...(props.assessment.fee_breakdown || [])]);

// Form
const form = useForm({
    year_level: props.assessment.year_level,
    semester: props.assessment.semester,
    school_year: props.assessment.school_year,
    subjects: selectedSubjects.value,
    other_fees: selectedFees.value,
});

// Year levels and semesters
const yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
const semesters = ['1st Sem', '2nd Sem', 'Summer'];

// Calculate totals
const tuitionTotal = computed(() => {
    return selectedSubjects.value.reduce((sum, s) => sum + (s.amount || 0), 0);
});

const otherFeesTotal = computed(() => {
    return selectedFees.value.reduce((sum, f) => sum + (f.amount || 0), 0);
});

const grandTotal = computed(() => {
    return tuitionTotal.value + otherFeesTotal.value;
});

// Subject management
const addSubject = (subject: Subject) => {
    const exists = selectedSubjects.value.find((s) => s.id === subject.id);
    if (!exists) {
        selectedSubjects.value.push({
            id: subject.id,
            units: subject.units,
            amount: parseFloat(String(subject.total_cost)) || 0,
        });
    }
};

const removeSubject = (subjectId: number) => {
    selectedSubjects.value = selectedSubjects.value.filter((s) => s.id !== subjectId);
};

const getSubjectDetails = (subjectId: number) => {
    return props.subjects.find((s) => s.id === subjectId);
};

const isSubjectSelected = (subjectId: number) => {
    return selectedSubjects.value.some((s) => s.id === subjectId);
};

// Fee management
const addFee = (fee: Fee) => {
    const exists = selectedFees.value.find((f) => f.id === fee.id);
    if (!exists) {
        selectedFees.value.push({
            id: fee.id,
            amount: parseFloat(String(fee.amount)) || 0,
        });
    }
};

const removeFee = (feeId: number) => {
    selectedFees.value = selectedFees.value.filter((f) => f.id !== feeId);
};

const getFeeDetails = (feeId: number) => {
    return props.fees.find((f) => f.id === feeId);
};

const isFeeSelected = (feeId: number) => {
    return selectedFees.value.some((f) => f.id === feeId);
};

// Watch for changes to update form
watch(
    [selectedSubjects, selectedFees],
    () => {
        form.subjects = selectedSubjects.value;
        form.other_fees = selectedFees.value;
    },
    { deep: true },
);

// Submit form
const submit = () => {
    form.put(route('student-fees.update', props.student.id), {
        preserveScroll: true,
    });
};

// Format currency
const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};

// Get status color
const getStatusColor = (status: string) => {
    switch (status) {
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'completed':
            return 'bg-blue-100 text-blue-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <Head :title="`Edit Assessment - ${student.name}`" />

    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center gap-4">
                <Link :href="route('student-fees.show', student.id)">
                    <Button variant="outline" size="sm" class="flex items-center gap-2">
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </Button>
                </Link>
                <div>
                    <h1 class="text-3xl font-bold">Edit Assessment</h1>
                    <p class="mt-2 text-gray-600">Modify the assessment for {{ student.name }}</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Student Info -->
                <div class="rounded-lg border-2 border-blue-200 bg-blue-50 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Student Information</h3>
                            <div class="mt-2 grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                                <div>
                                    <span class="text-gray-600">Account ID:</span>
                                    <p class="font-medium">{{ student.student_id }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-600">Name:</span>
                                    <p class="font-medium">{{ student.name }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-600">Course:</span>
                                    <p class="font-medium">{{ student.course }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-600">Year Level:</span>
                                    <p class="font-medium">{{ student.year_level }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-600">Assessment Number</p>
                            <p class="text-lg font-bold text-blue-600">{{ assessment.assessment_number }}</p>
                            <span class="mt-2 inline-block rounded-full px-3 py-1 text-xs font-semibold" :class="getStatusColor(assessment.status)">
                                {{ assessment.status }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Term Information -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <h2 class="mb-4 text-lg font-semibold">Term Information</h2>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <Label for="year_level">Year Level</Label>
                            <select
                                id="year_level"
                                v-model="form.year_level"
                                required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">Select year level</option>
                                <option v-for="year in yearLevels" :key="year" :value="year">
                                    {{ year }}
                                </option>
                            </select>
                            <p v-if="form.errors?.year_level" class="text-sm text-red-500">
                                {{ form.errors.year_level }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="semester">Semester</Label>
                            <select
                                id="semester"
                                v-model="form.semester"
                                required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">Select semester</option>
                                <option v-for="sem in semesters" :key="sem" :value="sem">
                                    {{ sem }}
                                </option>
                            </select>
                            <p v-if="form.errors?.semester" class="text-sm text-red-500">
                                {{ form.errors.semester }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="school_year">School Year</Label>
                            <Input id="school_year" v-model="form.school_year" placeholder="2025-2026" required />
                            <p v-if="form.errors?.school_year" class="text-sm text-red-500">
                                {{ form.errors.school_year }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Subjects Section -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <h2 class="mb-4 text-lg font-semibold">Subjects</h2>

                    <!-- Available Subjects -->
                    <div class="mb-4 space-y-2">
                        <Label>Available Subjects</Label>
                        <div class="grid max-h-48 grid-cols-1 gap-2 overflow-y-auto rounded-lg border p-2">
                            <div
                                v-for="subject in subjects"
                                :key="subject.id"
                                class="flex items-center justify-between rounded border p-3 transition-colors"
                                :class="isSubjectSelected(subject.id) ? 'border-green-200 bg-green-50' : 'cursor-pointer hover:bg-gray-50'"
                                @click="!isSubjectSelected(subject.id) && addSubject(subject)"
                            >
                                <div>
                                    <p class="font-medium">{{ subject.code }} - {{ subject.name }}</p>
                                    <p class="text-sm text-gray-600">
                                        {{ subject.units }} units × {{ formatCurrency(subject.price_per_unit) }}
                                        <span v-if="subject.has_lab">+ Lab Fee {{ formatCurrency(subject.lab_fee) }}</span>
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-blue-600">
                                        {{ formatCurrency(subject.total_cost) }}
                                    </span>
                                    <span v-if="isSubjectSelected(subject.id)" class="text-sm font-medium text-green-600"> ✓ Added </span>
                                </div>
                            </div>
                            <div v-if="subjects.length === 0" class="py-4 text-center text-gray-500">No subjects available</div>
                        </div>
                    </div>

                    <!-- Selected Subjects -->
                    <div class="space-y-2">
                        <Label>Selected Subjects</Label>
                        <div class="space-y-2">
                            <div
                                v-for="selected in selectedSubjects"
                                :key="selected.id"
                                class="flex items-center justify-between rounded-lg border bg-gray-50 p-3"
                            >
                                <div class="flex-1">
                                    <p class="font-medium">
                                        {{ getSubjectDetails(selected.id)?.code }} -
                                        {{ getSubjectDetails(selected.id)?.name }}
                                    </p>
                                    <p class="text-sm text-gray-600">{{ selected.units }} units</p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="font-medium">{{ formatCurrency(selected.amount) }}</span>
                                    <button
                                        type="button"
                                        class="rounded p-1 text-red-500 transition-colors hover:bg-red-50 hover:text-red-700"
                                        @click="removeSubject(selected.id)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <div v-if="selectedSubjects.length === 0" class="rounded-lg border bg-gray-50 py-8 text-center text-gray-500">
                                No subjects selected. Click on subjects above to add them.
                            </div>
                        </div>
                        <p v-if="form.errors?.subjects" class="text-sm text-red-500">
                            {{ form.errors.subjects }}
                        </p>
                    </div>

                    <!-- Tuition Total -->
                    <div class="mt-4 flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <span class="text-lg font-medium">Total Tuition Fee</span>
                        <span class="text-2xl font-bold text-blue-600">{{ formatCurrency(tuitionTotal) }}</span>
                    </div>
                </div>

                <!-- Other Fees Section -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <h2 class="mb-4 text-lg font-semibold">Other Fees</h2>

                    <!-- Available Fees -->
                    <div class="mb-4 space-y-2">
                        <Label>Available Fees</Label>
                        <div class="grid max-h-48 grid-cols-1 gap-2 overflow-y-auto rounded-lg border p-2">
                            <div
                                v-for="fee in fees"
                                :key="fee.id"
                                class="flex items-center justify-between rounded border p-3 transition-colors"
                                :class="isFeeSelected(fee.id) ? 'border-green-200 bg-green-50' : 'cursor-pointer hover:bg-gray-50'"
                                @click="!isFeeSelected(fee.id) && addFee(fee)"
                            >
                                <div>
                                    <p class="font-medium">{{ fee.name }}</p>
                                    <p class="text-sm text-gray-600">{{ fee.category }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-blue-600">
                                        {{ formatCurrency(fee.amount) }}
                                    </span>
                                    <span v-if="isFeeSelected(fee.id)" class="text-sm font-medium text-green-600"> ✓ Added </span>
                                </div>
                            </div>
                            <div v-if="fees.length === 0" class="py-4 text-center text-gray-500">No fees available</div>
                        </div>
                    </div>

                    <!-- Selected Fees -->
                    <div class="space-y-2">
                        <Label>Selected Fees</Label>
                        <div class="space-y-2">
                            <div
                                v-for="selected in selectedFees"
                                :key="selected.id"
                                class="flex items-center justify-between rounded-lg border bg-gray-50 p-3"
                            >
                                <div class="flex-1">
                                    <p class="font-medium">{{ getFeeDetails(selected.id)?.name }}</p>
                                    <p class="text-sm text-gray-600">
                                        {{ getFeeDetails(selected.id)?.category }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="font-medium">{{ formatCurrency(selected.amount) }}</span>
                                    <button
                                        type="button"
                                        class="rounded p-1 text-red-500 transition-colors hover:bg-red-50 hover:text-red-700"
                                        @click="removeFee(selected.id)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <div v-if="selectedFees.length === 0" class="rounded-lg border bg-gray-50 py-8 text-center text-gray-500">
                                No fees selected. Click on fees above to add them.
                            </div>
                        </div>
                    </div>

                    <!-- Other Fees Total -->
                    <div class="mt-4 flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <span class="text-lg font-medium">Total Other Fees</span>
                        <span class="text-2xl font-bold text-blue-600">{{ formatCurrency(otherFeesTotal) }}</span>
                    </div>
                </div>

                <!-- Grand Total -->
                <div class="rounded-lg bg-gradient-to-r from-blue-600 to-blue-700 p-6 text-white shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="mb-1 text-sm tracking-wide text-blue-100 uppercase">Total Assessment Fee Amount</p>
                            <p class="text-4xl font-bold">{{ formatCurrency(grandTotal) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-blue-100">Tuition: {{ formatCurrency(tuitionTotal) }}</p>
                            <p class="text-sm text-blue-100">Other Fees: {{ formatCurrency(otherFeesTotal) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Change Summary (if amounts changed) -->
                <div v-if="grandTotal !== assessment.total_assessment" class="rounded-lg border-2 border-yellow-200 bg-yellow-50 p-4">
                    <p class="mb-2 font-medium text-yellow-800">⚠️ Assessment Amount Changed</p>
                    <div class="space-y-1 text-sm text-yellow-700">
                        <p>Previous Total: {{ formatCurrency(assessment.total_assessment) }}</p>
                        <p>New Total: {{ formatCurrency(grandTotal) }}</p>
                        <p class="font-medium">
                            Difference:
                            <span :class="grandTotal > assessment.total_assessment ? 'text-red-600' : 'text-green-600'">
                                {{ grandTotal > assessment.total_assessment ? '+' : ''
                                }}{{ formatCurrency(grandTotal - assessment.total_assessment) }}
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between gap-4 border-t pt-4">
                    <Link :href="route('student-fees.show', student.id)">
                        <Button type="button" variant="outline"> Cancel </Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing || selectedSubjects.length === 0" class="flex min-w-[200px] items-center gap-2">
                        <Save class="h-4 w-4" />
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
