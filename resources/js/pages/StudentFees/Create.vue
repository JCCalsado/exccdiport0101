<!-- resources/js/Pages/StudentFees/Create.vue -->
<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Search, Trash2, User } from 'lucide-vue-next';
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

interface SelectedSubject {
    id: number;
    units: number;
    amount: number;
}

interface SelectedFee {
    id: number;
    amount: number;
}

interface Props {
    students: Student[];
    yearLevels: string[];
    semesters: string[];
    schoolYears: string[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Student Fee Management', href: route('student-fees.index') },
    { title: 'Create Assessment' },
];

// Step management
const currentStep = ref<1 | 2>(1);
const selectedStudent = ref<Student | null>(null);
const studentSearch = ref('');

// Available subjects and fees (loaded after student selection)
const availableSubjects = ref<Subject[]>([]);
const availableFees = ref<Fee[]>([]);
const isLoadingData = ref(false);

// Selected items
const selectedSubjects = ref<SelectedSubject[]>([]);
const selectedFees = ref<SelectedFee[]>([]);

// Filter students based on search
const filteredStudents = computed(() => {
    if (!studentSearch.value) return props.students;

    const search = studentSearch.value.toLowerCase();
    return props.students.filter(
        (student) =>
            student.student_id.toLowerCase().includes(search) ||
            student.name.toLowerCase().includes(search) ||
            student.email.toLowerCase().includes(search) ||
            student.course.toLowerCase().includes(search),
    );
});

// Form for assessment data
const form: any = useForm({
    user_id: null,
    year_level: '',
    semester: '',
    school_year: props.schoolYears[0] || '',
    subjects: [],
    other_fees: [],
});

// Calculate totals
const tuitionTotal = computed(() => {
    return selectedSubjects.value.reduce((sum, s) => {
        const amount = s.amount || 0;
        return sum + amount;
    }, 0);
});

const otherFeesTotal = computed(() => {
    return selectedFees.value.reduce((sum, f) => {
        const amount = f.amount || 0;
        return sum + amount;
    }, 0);
});

const grandTotal = computed(() => {
    return tuitionTotal.value + otherFeesTotal.value;
});

// Select student and move to next step
const selectStudent = async (student: Student) => {
    selectedStudent.value = student;
    form.user_id = student.id;
    form.year_level = student.year_level;

    // Load subjects and fees for this student
    await loadStudentData(student);

    currentStep.value = 2;
};

// Load subjects and fees based on student
const loadStudentData = async (student: Student) => {
    isLoadingData.value = true;

    try {
        // In a real scenario, you'd make an API call here
        // For now, we'll simulate it with a route call
        const response = await fetch(
            route('student-fees.create', {
                student_id: student.id,
                get_data: true,
            }),
        );

        if (response.ok) {
            const data = await response.json();
            availableSubjects.value = data.subjects || [];
            availableFees.value = data.fees || [];
        }
    } catch (error) {
        console.error('Failed to load student data:', error);
    } finally {
        isLoadingData.value = false;
    }
};

// Go back to student selection
const backToStudentSelection = () => {
    currentStep.value = 1;
    selectedStudent.value = null;
    selectedSubjects.value = [];
    selectedFees.value = [];
    form.user_id = null;
    form.year_level = '';
};

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
    return availableSubjects.value.find((s) => s.id === subjectId);
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
    return availableFees.value.find((f) => f.id === feeId);
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
    form.post(route('student-fees.store'), {
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
        case 'graduated':
            return 'bg-blue-100 text-blue-800';
        case 'dropped':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <Head title="Create Student Assessment" />

    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center gap-4">
                <Link :href="route('student-fees.index')">
                    <Button variant="outline" size="sm" class="flex items-center gap-2">
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </Button>
                </Link>
                <div>
                    <h1 class="text-3xl font-bold">Create Student Assessment</h1>
                    <p class="mt-2 text-gray-600">
                        {{ currentStep === 1 ? 'Step 1: Select Student' : 'Step 2: Create Assessment' }}
                    </p>
                </div>
            </div>

            <!-- Step Indicator -->
            <div class="flex items-center justify-center gap-4">
                <div class="flex items-center gap-2">
                    <div
                        :class="[
                            'flex h-10 w-10 items-center justify-center rounded-full font-semibold',
                            currentStep >= 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600',
                        ]"
                    >
                        1
                    </div>
                    <span class="font-medium">Select Student</span>
                </div>
                <div class="h-1 w-24 bg-gray-200">
                    <div :class="['h-full transition-all duration-300', currentStep >= 2 ? 'w-full bg-blue-600' : 'w-0']"></div>
                </div>
                <div class="flex items-center gap-2">
                    <div
                        :class="[
                            'flex h-10 w-10 items-center justify-center rounded-full font-semibold',
                            currentStep >= 2 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600',
                        ]"
                    >
                        2
                    </div>
                    <span class="font-medium">Create Assessment</span>
                </div>
            </div>

            <!-- STEP 1: Student Selection -->
            <div v-if="currentStep === 1" class="space-y-6">
                <!-- Search Bar -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <div class="relative">
                        <Search class="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 transform text-gray-400" />
                        <Input v-model="studentSearch" placeholder="Search by Student ID, Name, Email, or Course..." class="pl-10" />
                    </div>
                </div>

                <!-- Student List -->
                <div class="overflow-hidden rounded-lg border bg-white shadow-sm">
                    <div class="border-b p-6">
                        <h2 class="text-lg font-semibold">Select a Student</h2>
                        <p class="mt-1 text-sm text-gray-600">Choose an active student to create an assessment for</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Year Level</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-if="filteredStudents.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                        <User class="mx-auto mb-3 h-12 w-12 text-gray-300" />
                                        <p class="text-lg font-medium">No students found</p>
                                        <p class="mt-1 text-sm">Try adjusting your search criteria</p>
                                    </td>
                                </tr>
                                <tr
                                    v-for="student in filteredStudents"
                                    :key="student.id"
                                    class="cursor-pointer transition-colors hover:bg-gray-50"
                                    @click="selectStudent(student)"
                                >
                                    <td class="px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-900">
                                        {{ student.student_id }}
                                    </td>
                                    <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-900">
                                        <div>
                                            <div class="font-medium">{{ student.name }}</div>
                                            <div class="text-xs text-gray-500">{{ student.email }}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-500">
                                        {{ student.course }}
                                    </td>
                                    <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-500">
                                        {{ student.year_level }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="getStatusColor(student.status)">
                                            {{ student.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                        <Button size="sm" @click.stop="selectStudent(student)"> Select </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Assessment Form -->
            <form v-if="currentStep === 2" @submit.prevent="submit" class="space-y-6">
                <!-- Selected Student Info -->
                <div class="rounded-lg border-2 border-blue-200 bg-blue-50 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Selected Student</h3>
                            <div class="mt-2 grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                                <div>
                                    <span class="text-gray-600">Student ID:</span>
                                    <p class="font-medium">{{ selectedStudent?.student_id }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-600">Name:</span>
                                    <p class="font-medium">{{ selectedStudent?.name }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-600">Course:</span>
                                    <p class="font-medium">{{ selectedStudent?.course }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-600">Year Level:</span>
                                    <p class="font-medium">{{ selectedStudent?.year_level }}</p>
                                </div>
                            </div>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="backToStudentSelection"> Change Student </Button>
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
                            <select
                                id="school_year"
                                v-model="form.school_year"
                                required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">Select school year</option>
                                <option v-for="sy in schoolYears" :key="sy" :value="sy">
                                    {{ sy }}
                                </option>
                            </select>
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
                        <div v-if="isLoadingData" class="py-8 text-center">
                            <p class="text-gray-500">Loading subjects...</p>
                        </div>
                        <div v-else class="grid max-h-48 grid-cols-1 gap-2 overflow-y-auto rounded-lg border p-2">
                            <div
                                v-for="subject in availableSubjects"
                                :key="subject.id"
                                class="flex cursor-pointer items-center justify-between rounded border p-3 hover:bg-gray-50"
                                @click="addSubject(subject)"
                            >
                                <div>
                                    <p class="font-medium">{{ subject.code }} - {{ subject.name }}</p>
                                    <p class="text-sm text-gray-600">
                                        {{ subject.units }} units × {{ formatCurrency(subject.price_per_unit) }}
                                        <span v-if="subject.has_lab">+ Lab Fee {{ formatCurrency(subject.lab_fee) }}</span>
                                    </p>
                                </div>
                                <div class="font-medium text-blue-600">
                                    {{ formatCurrency(subject.total_cost) }}
                                </div>
                            </div>
                            <div v-if="availableSubjects.length === 0 && !isLoadingData" class="py-4 text-center text-gray-500">
                                No subjects available for this student
                            </div>
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
                                    <button type="button" class="text-red-500 hover:text-red-700" @click="removeSubject(selected.id)">
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
                        <div v-if="isLoadingData" class="py-8 text-center">
                            <p class="text-gray-500">Loading fees...</p>
                        </div>
                        <div v-else class="grid max-h-48 grid-cols-1 gap-2 overflow-y-auto rounded-lg border p-2">
                            <div
                                v-for="fee in availableFees"
                                :key="fee.id"
                                class="flex cursor-pointer items-center justify-between rounded border p-3 hover:bg-gray-50"
                                @click="addFee(fee)"
                            >
                                <div>
                                    <p class="font-medium">{{ fee.name }}</p>
                                    <p class="text-sm text-gray-600">{{ fee.category }}</p>
                                </div>
                                <div class="font-medium text-blue-600">
                                    {{ formatCurrency(fee.amount) }}
                                </div>
                            </div>
                            <div v-if="availableFees.length === 0 && !isLoadingData" class="py-4 text-center text-gray-500">No fees available</div>
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
                                    <button type="button" class="text-red-500 hover:text-red-700" @click="removeFee(selected.id)">
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

                <!-- Actions -->
                <div class="flex items-center justify-between gap-4 pt-4">
                    <Button type="button" variant="outline" @click="backToStudentSelection"> Back to Student Selection </Button>
                    <div class="flex gap-4">
                        <Link :href="route('student-fees.index')">
                            <Button type="button" variant="outline"> Cancel </Button>
                        </Link>
                        <Button type="submit" :disabled="form.processing || !form.user_id || selectedSubjects.length === 0" class="min-w-[200px]">
                            {{ form.processing ? 'Creating...' : 'Create Assessment' }}
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
