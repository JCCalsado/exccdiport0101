<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, ChevronDown, ChevronRight, PenLine, Search, Trash2, User } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Student {
    id: number;
    account_id: string;
    name: string;
    email: string;
    course: string | null;
    year_level: string | null;
    status: string;
    is_irregular: boolean;
    // Added by the controller for next-assessment pre-fill (Bug #3 fix)
    suggested_year_level: string | null;
    suggested_semester: string | null;
    latest_assessment: {
        year_level: string;
        semester: string;
        school_year: string;
    } | null;
}

interface FeeLineItem {
    category: string;
    name: string;
    amount: number;
}

interface SubjectItem {
    id: number;
    code: string;
    name: string;
    units: number;
    price_per_unit: number;
    has_lab: boolean;
    lab_fee: number;
    total_cost: number;
    year_level: string;
    semester: string;
}

// feePresets[course][yearLevel][semester] = FeeLineItem[]
type FeePresets = Record<string, Record<string, Record<string, FeeLineItem[]>>>;

// subjectMap[course][yearLevel][semester] = SubjectItem[]
type SubjectMap = Record<string, Record<string, Record<string, SubjectItem[]>>>;

interface SelectedSubject {
    id: number;
    code: string;
    name: string;
    units: number;
    price_per_unit: number;
    has_lab: boolean;
    lab_fee: number;
    amount: number;
    year_level: string;
    semester: string;
}

interface Props {
    students:    Student[];
    yearLevels:  string[];
    semesters:   string[];
    schoolYears: string[];
    feePresets:  FeePresets;
    subjectMap:  SubjectMap;
    courses:     string[];
}

const props = defineProps<Props>();

// ─── Breadcrumbs ──────────────────────────────────────────────────────────────

const breadcrumbs = [
    { title: 'Dashboard',              href: route('dashboard') },
    { title: 'Student Fee Management', href: route('student-fees.index') },
    { title: 'Create Assessment' },
];

// ─── Step & Student ───────────────────────────────────────────────────────────

const currentStep     = ref<1 | 2>(1);
const selectedStudent = ref<Student | null>(null);
const studentSearch   = ref('');

// ─── Inline course editing ─────────────────────────────────────────────────────────────────────
// When a student has no course (newly registered), staff can assign one
// directly from the Create Assessment form without navigating elsewhere.
const editableCourse  = ref<string>('');

// True when the student has no real course yet (null or seeder placeholder 'N/A')
const needsCourse = computed<boolean>(() => {
    const c = selectedStudent.value?.course;
    return !c || c.trim() === '' || c === 'N/A';
});


// ─── Assessment type ──────────────────────────────────────────────────────────

const assessmentType = ref<'regular' | 'irregular'>('regular');

// ─── Term fields ──────────────────────────────────────────────────────────────

const yearLevel        = ref('');
const semester         = ref('');
const schoolYear       = ref(props.schoolYears[2] || '');   // default = current year (index 2 of 5)
const customSchoolYear = ref('');
const useCustomYear    = ref(false);

const effectiveSchoolYear = computed(() =>
    useCustomYear.value ? customSchoolYear.value.trim() : schoolYear.value,
);

// Custom year format validation: YYYY-YYYY
const customYearValid = computed(() => {
    if (!useCustomYear.value) return true;
    return /^\d{4}-\d{4}$/.test(customSchoolYear.value.trim());
});

// ─── REGULAR: editable fee breakdown from preset ──────────────────────────────

interface RegularFeeRow {
    category: string;
    name: string;
    amount: number;
}

const regularFeeRows = ref<RegularFeeRow[]>([]);

// Populate preset rows whenever course/year/semester changes
const presetLines = computed<FeeLineItem[]>(() => {
    // Use editableCourse if student has no assigned course yet
    const course = needsCourse.value ? editableCourse.value : selectedStudent.value?.course;
    if (!course || !yearLevel.value || !semester.value) return [];
    return props.feePresets?.[course]?.[yearLevel.value]?.[semester.value] ?? [];
});

watch([() => selectedStudent.value?.course, editableCourse, yearLevel, semester, assessmentType], () => {
    if (assessmentType.value !== 'regular') return;
    if (presetLines.value.length > 0) {
        regularFeeRows.value = presetLines.value.map((l) => ({
            category: l.category,
            name:     l.name,
            amount:   l.amount,
        }));
    } else {
        // No preset — give one blank Tuition row
        regularFeeRows.value = [{ category: 'Tuition', name: 'Tuition Fee', amount: 0 }];
    }
});

function addRegularRow() {
    regularFeeRows.value.push({ category: 'Tuition', name: '', amount: 0 });
}

function removeRegularRow(index: number) {
    regularFeeRows.value.splice(index, 1);
}

const regularTotal = computed(() =>
    regularFeeRows.value.reduce((sum, r) => sum + (parseFloat(String(r.amount)) || 0), 0),
);

const FEE_CATEGORIES = ['Tuition', 'Laboratory', 'Miscellaneous', 'Other'];

// ─── IRREGULAR: subject picker ────────────────────────────────────────────────

const selectedSubjects = ref<SelectedSubject[]>([]);
const subjectSearch    = ref('');
const expandedGroups   = ref<Set<string>>(new Set());

function toggleGroup(key: string) {
    if (expandedGroups.value.has(key)) {
        expandedGroups.value.delete(key);
    } else {
        expandedGroups.value.add(key);
    }
}

// When student is selected and type is Irregular, pre-load their current year/sem subjects
function preloadIrregularSubjects() {
    const course = needsCourse.value ? editableCourse.value : selectedStudent.value?.course;
    if (!course || !yearLevel.value || !semester.value) return;
    const subjectsForCurrentTerm =
        props.subjectMap?.[course]?.[yearLevel.value]?.[semester.value] ?? [];

    // Auto-expand the current year/sem group
    const groupKey = `${yearLevel.value}||${semester.value}`;
    expandedGroups.value.add(groupKey);

    // Pre-select all subjects for the student's current term
    selectedSubjects.value = subjectsForCurrentTerm.map((s) => ({
        id:             s.id,
        code:           s.code,
        name:           s.name,
        units:          s.units,
        price_per_unit: s.price_per_unit,
        has_lab:        s.has_lab,
        lab_fee:        s.lab_fee,
        amount:         s.total_cost,
        year_level:     s.year_level,
        semester:       s.semester,
    }));
}

// All subjects for the current student's course (any year/sem)
const allSubjectsForCourse = computed((): SubjectItem[] => {
    const course = needsCourse.value ? editableCourse.value : selectedStudent.value?.course;
    if (!course || !props.subjectMap[course]) return [];
    const result: SubjectItem[] = [];
    for (const yl of Object.keys(props.subjectMap[course])) {
        for (const sem of Object.keys(props.subjectMap[course][yl])) {
            result.push(...props.subjectMap[course][yl][sem]);
        }
    }
    return result;
});

// Grouped & search-filtered subjects for the browser panel
const groupedSubjects = computed(() => {
    const course = needsCourse.value ? editableCourse.value : selectedStudent.value?.course;
    if (!course || !props.subjectMap[course]) return {};
    const search  = subjectSearch.value.toLowerCase();
    const byYear  = props.subjectMap[course];
    const result: Record<string, Record<string, SubjectItem[]>> = {};
    for (const yl of Object.keys(byYear)) {
        for (const sem of Object.keys(byYear[yl])) {
            const items = byYear[yl][sem].filter(
                (s) => !search || s.code.toLowerCase().includes(search) || s.name.toLowerCase().includes(search),
            );
            if (!items.length) continue;
            if (!result[yl]) result[yl] = {};
            result[yl][sem] = items;
        }
    }
    return result;
});

function isSubjectSelected(id: number) {
    return selectedSubjects.value.some((s) => s.id === id);
}

function toggleSubject(subject: SubjectItem) {
    if (isSubjectSelected(subject.id)) {
        selectedSubjects.value = selectedSubjects.value.filter((s) => s.id !== subject.id);
    } else {
        selectedSubjects.value.push({
            id: subject.id, code: subject.code, name: subject.name,
            units: subject.units, price_per_unit: subject.price_per_unit,
            has_lab: subject.has_lab, lab_fee: subject.lab_fee,
            amount: subject.total_cost,
            year_level: subject.year_level, semester: subject.semester,
        });
    }
}

function removeSelectedSubject(id: number) {
    selectedSubjects.value = selectedSubjects.value.filter((s) => s.id !== id);
}

// ─── Student list filtering ───────────────────────────────────────────────────

const filteredStudents = computed(() => {
    if (!studentSearch.value) return props.students;
    const s = studentSearch.value.toLowerCase();
    return props.students.filter(
        (st) =>
            st.account_id.toLowerCase().includes(s) ||
            st.name.toLowerCase().includes(s) ||
            st.email.toLowerCase().includes(s) ||
            (st.course ?? '').toLowerCase().includes(s),
    );
});

// ─── Grand total ──────────────────────────────────────────────────────────────

const grandTotal = computed(() =>
    assessmentType.value === 'irregular'
        ? selectedSubjects.value.reduce((sum, s) => sum + s.amount, 0)
        : regularTotal.value,
);

// ─── Student selection ────────────────────────────────────────────────────────

function selectStudent(student: Student) {
    selectedStudent.value  = student;
    // Pre-populate editableCourse from the student's existing course (if any).
    // If they have no course or a placeholder, leave empty so staff must pick one.
    const existingCourse = student.course;
    editableCourse.value = (existingCourse && existingCourse !== 'N/A') ? existingCourse : '';
    // Use the suggested (next-term) year level, not the current stored year_level.
    // The controller computes this based on the student's last completed assessment.
    yearLevel.value        = student.suggested_year_level || student.year_level || '';
    // Pre-populate semester if the controller resolved a clear next step
    if (student.suggested_semester) {
        semester.value = student.suggested_semester;
    }
    assessmentType.value   = student.is_irregular ? 'irregular' : 'regular';
    selectedSubjects.value = [];
    regularFeeRows.value   = [];
    expandedGroups.value   = new Set();
    subjectSearch.value    = '';
    currentStep.value      = 2;
}

function backToStudentSelection() {
    currentStep.value      = 1;
    selectedStudent.value  = null;
    editableCourse.value   = '';
    selectedSubjects.value = [];
    regularFeeRows.value   = [];
    yearLevel.value        = '';
    semester.value         = '';
    expandedGroups.value   = new Set();
}

// When type switches to irregular and we have a student + term selected, pre-load subjects
watch(assessmentType, (newType) => {
    if (newType === 'irregular') {
        selectedSubjects.value = [];
        preloadIrregularSubjects();
    } else {
        selectedSubjects.value = [];
    }
});

// When year level or semester changes in irregular mode, refresh pre-selection
watch([yearLevel, semester], () => {
    if (assessmentType.value === 'irregular') {
        selectedSubjects.value = [];
        expandedGroups.value   = new Set();
        preloadIrregularSubjects();
    }
});

// ─── Submit ───────────────────────────────────────────────────────────────────

const form: any  = useForm({});
const formErrors = ref<Record<string, string>>({});

function submit() {
    formErrors.value = {};

    if (!yearLevel.value || !semester.value) {
        formErrors.value.term = 'Please select a year level and semester.';
        return;
    }

    if (useCustomYear.value && !customYearValid.value) {
        formErrors.value.school_year = 'School year must be in YYYY-YYYY format (e.g. 2025-2026).';
        return;
    }

    if (assessmentType.value === 'regular') {
        if (regularFeeRows.value.length === 0) {
            formErrors.value.regular_fees = 'Please add at least one fee line.';
            return;
        }
        if (regularTotal.value <= 0) {
            formErrors.value.regular_fees = 'Total assessment must be greater than zero.';
            return;
        }
        const emptyName = regularFeeRows.value.some((r) => !r.name.trim());
        if (emptyName) {
            formErrors.value.regular_fees = 'All fee line items must have a name.';
            return;
        }
    }

    if (assessmentType.value === 'irregular' && selectedSubjects.value.length === 0) {
        formErrors.value.selected_subjects = 'Please select at least one subject.';
        return;
    }

    // If student has no course, staff must assign one before submitting
    if (needsCourse.value && !editableCourse.value.trim()) {
        formErrors.value.course = 'Please assign a course to this student before creating an assessment.';
        return;
    }

    const payload: Record<string, any> = {
        user_id:         selectedStudent.value!.id,
        year_level:      yearLevel.value,
        semester:        semester.value,
        school_year:     effectiveSchoolYear.value,
        assessment_type: assessmentType.value,
        // Include course so the controller can update the student's record if needed
        course:          needsCourse.value ? editableCourse.value.trim() : (selectedStudent.value!.course ?? ''),
    };

    if (assessmentType.value === 'regular') {
        payload.fee_items = regularFeeRows.value.map((r) => ({
            category: r.category,
            name:     r.name,
            amount:   parseFloat(String(r.amount)) || 0,
        }));
    } else {
        payload.selected_subjects = selectedSubjects.value.map((s) => ({
            id:     s.id,
            units:  s.units,
            amount: s.amount,
        }));
    }

    form.transform(() => payload).post(route('student-fees.store'), {
        preserveScroll: true,
        onError: (errors: any) => { formErrors.value = errors; },
    });
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const fmt = (n: number) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(n);

function statusColor(status: string) {
    return status === 'active'
        ? 'bg-green-100 text-green-800'
        : status === 'graduated'
          ? 'bg-blue-100 text-blue-800'
          : 'bg-gray-100 text-gray-800';
}

const categoryColor: Record<string, string> = {
    Tuition:       'bg-blue-100 text-blue-700',
    Laboratory:    'bg-purple-100 text-purple-700',
    Miscellaneous: 'bg-yellow-100 text-yellow-700',
    Other:         'bg-gray-100 text-gray-700',
};
</script>

<template>
    <Head title="Create Student Assessment" />

    <AppLayout>
        <div class="mx-auto max-w-6xl space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center gap-4">
                <Link :href="route('student-fees.index')">
                    <Button variant="outline" size="sm" class="flex items-center gap-2">
                        <ArrowLeft class="h-4 w-4" /> Back
                    </Button>
                </Link>
                <div>
                    <h1 class="text-3xl font-bold">Create Student Assessment</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ currentStep === 1 ? 'Step 1 of 2 — Select Student' : 'Step 2 of 2 — Configure Assessment' }}
                    </p>
                </div>
            </div>

            <!-- Step indicator -->
            <div class="flex items-center justify-center gap-4">
                <div class="flex items-center gap-2">
                    <div :class="['flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold',
                                  currentStep >= 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500']">1</div>
                    <span class="text-sm font-medium">Select Student</span>
                </div>
                <div class="h-px w-20 bg-gray-200">
                    <div :class="['h-full transition-all duration-300', currentStep >= 2 ? 'w-full bg-blue-600' : 'w-0']" />
                </div>
                <div class="flex items-center gap-2">
                    <div :class="['flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold',
                                  currentStep >= 2 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500']">2</div>
                    <span class="text-sm font-medium">Configure Assessment</span>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════
                 STEP 1 — Student Selection
                 ════════════════════════════════════════════════ -->
            <div v-if="currentStep === 1" class="space-y-4">

                <div class="rounded-lg border bg-white p-4 shadow-sm">
                    <div class="relative">
                        <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <Input v-model="studentSearch"
                               placeholder="Search by ID, name, email, or course…"
                               class="pl-9" />
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border bg-white shadow-sm">
                    <div class="border-b px-6 py-4">
                        <h2 class="font-semibold">Active Students</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Click a row to select</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-xs font-medium uppercase text-gray-500">
                                <tr>
                                    <th class="px-5 py-3 text-left">Account ID</th>
                                    <th class="px-5 py-3 text-left">Name</th>
                                    <th class="px-5 py-3 text-left">Course</th>
                                    <th class="px-5 py-3 text-left">Year</th>
                                    <th class="px-5 py-3 text-left">Type</th>
                                    <th class="px-5 py-3 text-left">Status</th>
                                    <th class="px-5 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-if="filteredStudents.length === 0">
                                    <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                                        <User class="mx-auto mb-2 h-10 w-10 text-gray-300" />
                                        <p>No students found</p>
                                    </td>
                                </tr>
                                <tr v-for="st in filteredStudents" :key="st.id"
                                    class="cursor-pointer transition-colors hover:bg-blue-50"
                                    @click="selectStudent(st)">
                                    <td class="px-5 py-3 font-mono text-xs font-medium text-gray-900">{{ st.account_id }}</td>
                                    <td class="px-5 py-3">
                                        <div class="font-medium text-gray-900">{{ st.name }}</div>
                                        <div class="text-xs text-gray-400">{{ st.email }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-gray-600">{{ st.course || '—' }}</td>
                                    <td class="px-5 py-3 text-xs">
                                        <!-- Show the suggested next-term year level when available.
                                             This reflects where the student is GOING, not where they were. -->
                                        <span v-if="st.suggested_year_level && st.suggested_year_level !== st.year_level"
                                              class="font-semibold text-blue-700">
                                            {{ st.suggested_year_level }}
                                        </span>
                                        <span v-else class="text-gray-600">
                                            {{ st.year_level || '—' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold',
                                                       st.is_irregular ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700']">
                                            {{ st.is_irregular ? 'Irregular' : 'Regular' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                              :class="statusColor(st.status)">{{ st.status }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <Button size="sm" @click.stop="selectStudent(st)">Select</Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════
                 STEP 2 — Assessment Configuration
                 ════════════════════════════════════════════════ -->
            <div v-if="currentStep === 2" class="space-y-5">

                <!-- Selected student banner -->
                <div class="rounded-lg border-2 border-blue-200 bg-blue-50 px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="grid grid-cols-2 gap-x-8 gap-y-1 text-sm sm:grid-cols-4">
                            <div>
                                <p class="text-xs text-gray-500">Account ID</p>
                                <p class="font-semibold text-gray-900">{{ selectedStudent?.account_id }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Name</p>
                                <p class="font-semibold text-gray-900">{{ selectedStudent?.name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">
                                    Course
                                    <span v-if="needsCourse" class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-amber-700 font-semibold text-xs">
                                        Required
                                    </span>
                                </p>
                                <!-- Read-only when student already has a course assigned -->
                                <p v-if="!needsCourse" class="font-semibold text-gray-900">
                                    {{ selectedStudent?.course }}
                                </p>
                                <!-- Editable dropdown when student has no course (newly registered) -->
                                <div v-else class="mt-1">
                                    <select
                                        v-model="editableCourse"
                                        class="w-full rounded-md border border-amber-400 bg-white px-2 py-1.5 text-sm font-semibold text-gray-900 focus:outline-none focus:ring-2 focus:ring-amber-400"
                                    >
                                        <option value="">— Select Course —</option>
                                        <option v-for="c in courses" :key="c" :value="c">{{ c }}</option>
                                    </select>
                                    <p v-if="formErrors.course" class="mt-1 text-xs text-red-600">
                                        {{ formErrors.course }}
                                    </p>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Current Year Level</p>
                                <!-- Show the CURRENT year level from the latest assessment (accurate),
                                     not year_level from users table (may be stale). -->
                                <p class="font-semibold text-gray-900">
                                    {{ selectedStudent?.latest_assessment?.year_level || selectedStudent?.year_level || '—' }}
                                </p>
                            </div>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="backToStudentSelection" class="flex-shrink-0">
                            Change
                        </Button>
                    </div>
                    <!-- Last completed assessment context + auto-fill notice -->
                    <div class="mt-3 border-t border-blue-200 pt-3 flex flex-wrap items-center gap-4 text-xs">
                        <div v-if="selectedStudent?.latest_assessment">
                            <span class="text-gray-500">Last Assessment: </span>
                            <span class="font-semibold text-gray-700">
                                {{ selectedStudent.latest_assessment.year_level }}
                                {{ selectedStudent.latest_assessment.semester }}
                                {{ selectedStudent.latest_assessment.school_year }}
                            </span>
                        </div>
                        <div v-else>
                            <span class="text-gray-400">No previous assessment on record.</span>
                        </div>
                        <div v-if="selectedStudent?.suggested_year_level"
                             class="rounded-full bg-green-100 px-3 py-1 text-green-700 font-medium">
                            ✓ Auto-filled: {{ selectedStudent.suggested_year_level }}
                            <span v-if="selectedStudent.suggested_semester"> · {{ selectedStudent.suggested_semester }}</span>
                        </div>
                    </div>
                </div>

                <!-- ── Term Information ─────────────────────────────── -->
                <div class="rounded-lg border bg-white p-5 shadow-sm">
                    <h2 class="mb-4 font-semibold text-gray-900">Term Information</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <!-- Year Level -->
                        <div class="space-y-1">
                            <Label>Year Level</Label>
                            <select v-model="yearLevel" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="">Select year level</option>
                                <option v-for="y in yearLevels" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>

                        <!-- Semester -->
                        <div class="space-y-1">
                            <Label>Semester</Label>
                            <select v-model="semester" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="">Select semester</option>
                                <option v-for="s in semesters" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>

                        <!-- School Year — select + optional custom override -->
                        <div class="space-y-1">
                            <Label class="flex items-center gap-2">
                                School Year
                                <button type="button"
                                        :class="['rounded px-1.5 py-0.5 text-xs font-medium transition-colors',
                                                 useCustomYear
                                                   ? 'bg-blue-100 text-blue-700'
                                                   : 'bg-gray-100 text-gray-500 hover:bg-gray-200']"
                                        :title="useCustomYear ? 'Using custom year — click to use preset' : 'Enter a custom school year'"
                                        @click="useCustomYear = !useCustomYear; customSchoolYear = ''">
                                    <PenLine class="inline h-3 w-3 mr-0.5" />
                                    {{ useCustomYear ? 'Custom ✓' : 'Custom' }}
                                </button>
                            </Label>

                            <!-- Preset dropdown -->
                            <select v-if="!useCustomYear"
                                    v-model="schoolYear" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option v-for="sy in schoolYears" :key="sy" :value="sy">{{ sy }}</option>
                            </select>

                            <!-- Custom text input -->
                            <div v-else>
                                <input v-model="customSchoolYear"
                                       type="text"
                                       placeholder="e.g. 2026-2027"
                                       :class="['w-full rounded-lg border px-3 py-2 text-sm outline-none focus:ring-2',
                                                customYearValid
                                                  ? 'border-blue-400 focus:border-blue-500 focus:ring-blue-200'
                                                  : 'border-red-400 focus:border-red-500 focus:ring-red-200']" />
                                <p v-if="!customYearValid && customSchoolYear" class="mt-1 text-xs text-red-500">
                                    Format: YYYY-YYYY (e.g. 2026-2027)
                                </p>
                            </div>

                            <!-- Effective value preview when custom -->
                            <p v-if="useCustomYear && customYearValid && customSchoolYear"
                               class="text-xs text-green-600">
                                ✓ Using: {{ effectiveSchoolYear }}
                            </p>

                            <p v-if="formErrors.school_year" class="mt-1 text-xs text-red-500">
                                {{ formErrors.school_year }}
                            </p>
                        </div>
                    </div>
                    <p v-if="formErrors.term" class="mt-2 text-xs text-red-500">{{ formErrors.term }}</p>
                </div>

                <!-- ── Assessment Type Toggle ───────────────────────── -->
                <div class="rounded-lg border bg-white p-5 shadow-sm">
                    <h2 class="mb-1 font-semibold text-gray-900">Assessment Type</h2>
                    <p class="mb-4 text-xs text-gray-500">
                        Defaulted from the student's record. You can override it for this assessment.
                    </p>
                    <div class="flex gap-3">
                        <button type="button"
                                :class="['flex-1 rounded-lg border-2 px-5 py-4 text-left transition-all',
                                         assessmentType === 'regular'
                                           ? 'border-blue-500 bg-blue-50'
                                           : 'border-gray-200 hover:border-gray-300']"
                                @click="assessmentType = 'regular'">
                            <p :class="['font-bold', assessmentType === 'regular' ? 'text-blue-700' : 'text-gray-700']">
                                Regular
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                Fixed fee breakdown auto-loaded from course preset. Each line is editable.
                            </p>
                        </button>
                        <button type="button"
                                :class="['flex-1 rounded-lg border-2 px-5 py-4 text-left transition-all',
                                         assessmentType === 'irregular'
                                           ? 'border-amber-500 bg-amber-50'
                                           : 'border-gray-200 hover:border-gray-300']"
                                @click="assessmentType = 'irregular'">
                            <p :class="['font-bold', assessmentType === 'irregular' ? 'text-amber-700' : 'text-gray-700']">
                                Irregular
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                Current-term subjects pre-loaded. Add or remove subjects from any year &amp; semester.
                            </p>
                        </button>
                    </div>
                </div>

                <!-- ════════════════════════════════════════
                     REGULAR PATH — editable fee breakdown
                     ════════════════════════════════════════ -->
                <div v-if="assessmentType === 'regular'" class="rounded-lg border bg-white shadow-sm">
                    <div class="border-b px-5 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <BookOpen class="h-4 w-4 text-blue-500" />
                            <h2 class="font-semibold text-gray-900">Fee Breakdown</h2>
                        </div>
                        <div class="flex items-center gap-3">
                            <p v-if="presetLines.length > 0" class="text-xs text-green-600">
                                ✓ Preset loaded — edit amounts as needed
                            </p>
                            <p v-else-if="yearLevel && semester" class="text-xs text-amber-600">
                                ⚠ No preset for this course/term — enter amounts manually
                            </p>
                            <p v-else class="text-xs text-gray-400">
                                Select year level and semester to load preset
                            </p>
                        </div>
                    </div>

                    <!-- Fee table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-xs font-medium uppercase text-gray-500">
                                <tr>
                                    <th class="px-4 py-2.5 text-left w-36">Category</th>
                                    <th class="px-4 py-2.5 text-left">Fee Name</th>
                                    <th class="px-4 py-2.5 text-right w-40">Amount (₱)</th>
                                    <th class="px-4 py-2.5 w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-if="regularFeeRows.length === 0">
                                    <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-400">
                                        No fee lines yet. Add rows below or select a year level &amp; semester above.
                                    </td>
                                </tr>
                                <tr v-for="(row, idx) in regularFeeRows" :key="idx"
                                    class="hover:bg-gray-50">
                                    <td class="px-4 py-2">
                                        <select v-model="row.category"
                                                class="w-full rounded border border-gray-200 px-2 py-1.5 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-200">
                                            <option v-for="cat in FEE_CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-2">
                                        <input v-model="row.name"
                                               type="text"
                                               placeholder="Fee description"
                                               class="w-full rounded border border-gray-200 px-2 py-1.5 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-200" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <input v-model="row.amount"
                                               type="number" min="0" step="0.01"
                                               class="w-full rounded border border-gray-200 px-2 py-1.5 text-right text-sm font-medium outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-200" />
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <button type="button"
                                                class="text-gray-300 hover:text-red-500 transition-colors"
                                                :disabled="regularFeeRows.length === 1"
                                                @click="removeRegularRow(idx)">
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Add row + subtotals -->
                    <div class="border-t px-5 py-3 flex items-center justify-between">
                        <button type="button"
                                class="flex items-center gap-1 rounded-lg border border-dashed border-blue-300 px-3 py-1.5 text-xs font-medium text-blue-600 hover:bg-blue-50 transition-colors"
                                @click="addRegularRow">
                            + Add Fee Line
                        </button>

                        <!-- Per-category subtotals -->
                        <div class="flex flex-wrap items-center gap-3">
                            <span v-for="cat in FEE_CATEGORIES"
                                  :key="cat"
                                  v-show="regularFeeRows.some(r => r.category === cat)"
                                  :class="['rounded-full px-2 py-0.5 text-xs font-medium', categoryColor[cat]]">
                                {{ cat }}: {{ fmt(regularFeeRows.filter(r => r.category === cat).reduce((s, r) => s + (parseFloat(String(r.amount)) || 0), 0)) }}
                            </span>
                        </div>
                    </div>

                    <p v-if="formErrors.regular_fees" class="px-5 pb-3 text-xs text-red-500">
                        {{ formErrors.regular_fees }}
                    </p>
                </div>

                <!-- ════════════════════════════════════════
                     IRREGULAR PATH — subject picker
                     ════════════════════════════════════════ -->
                <div v-if="assessmentType === 'irregular'" class="space-y-4">

                    <!-- Info banner -->
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                        <strong>Irregular Assessment:</strong>
                        Subjects from the student's current year &amp; semester are pre-selected.
                        Use the browser below to <strong>add subjects from other years/semesters</strong>
                        or <strong>remove</strong> subjects the student doesn't need.
                    </div>

                    <!-- Subject browser -->
                    <div class="rounded-lg border bg-white shadow-sm">
                        <div class="border-b px-5 py-4">
                            <h2 class="font-semibold text-gray-900">Subject Browser</h2>
                            <p class="mt-0.5 text-xs text-gray-500">
                                All subjects for <strong>{{ selectedStudent?.course || 'this course' }}</strong>.
                                Click a subject to add or remove it.
                            </p>
                        </div>

                        <!-- Search -->
                        <div class="border-b px-5 py-3">
                            <div class="relative">
                                <Search class="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                                <input v-model="subjectSearch" type="text"
                                       placeholder="Search by code or name…"
                                       class="w-full rounded-lg border border-gray-200 py-1.5 pr-3 pl-8 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-200" />
                            </div>
                        </div>

                        <!-- No subjects -->
                        <div v-if="allSubjectsForCourse.length === 0"
                             class="px-5 py-10 text-center text-sm text-gray-400">
                            No subjects found for <strong>{{ selectedStudent?.course || 'this course' }}</strong>.
                            Ask the admin to populate the subjects table.
                        </div>

                        <!-- Grouped accordion -->
                        <div v-else class="divide-y divide-gray-100">
                            <template v-for="(bySem, yl) in groupedSubjects" :key="yl">
                                <template v-for="(subjects, sem) in bySem" :key="sem">

                                    <!-- Group header -->
                                    <button type="button"
                                            :class="['flex w-full items-center justify-between px-5 py-2.5 text-left transition-colors hover:bg-gray-100',
                                                     yl === selectedStudent?.year_level && sem === semester
                                                       ? 'bg-amber-50'
                                                       : 'bg-gray-50']"
                                            @click="toggleGroup(`${yl}||${sem}`)">
                                        <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-600">
                                            {{ yl }} — {{ sem }}
                                            <span v-if="yl === selectedStudent?.year_level && sem === semester"
                                                  class="rounded-full bg-amber-200 px-1.5 py-0.5 text-amber-700 normal-case font-normal">
                                                current term
                                            </span>
                                            <span class="font-normal text-gray-400">({{ subjects.length }})</span>
                                        </span>
                                        <ChevronDown v-if="expandedGroups.has(`${yl}||${sem}`)"
                                                     class="h-4 w-4 text-gray-400" />
                                        <ChevronRight v-else class="h-4 w-4 text-gray-400" />
                                    </button>

                                    <!-- Subject rows -->
                                    <div v-if="expandedGroups.has(`${yl}||${sem}`)"
                                         class="divide-y divide-gray-50">
                                        <div v-for="subject in subjects" :key="subject.id"
                                             :class="['flex cursor-pointer items-center justify-between px-5 py-3 transition-colors',
                                                      isSubjectSelected(subject.id)
                                                        ? 'bg-amber-50'
                                                        : 'hover:bg-gray-50']"
                                             @click="toggleSubject(subject)">
                                            <div class="flex items-start gap-3">
                                                <div :class="['mt-0.5 flex h-4 w-4 flex-shrink-0 items-center justify-center rounded border text-xs font-bold',
                                                              isSubjectSelected(subject.id)
                                                                ? 'border-amber-500 bg-amber-500 text-white'
                                                                : 'border-gray-300 bg-white']">
                                                    <span v-if="isSubjectSelected(subject.id)">✓</span>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">
                                                        {{ subject.code }} — {{ subject.name }}
                                                    </p>
                                                    <p class="text-xs text-gray-400">
                                                        {{ subject.units }} units × {{ fmt(subject.price_per_unit) }}/unit
                                                        <span v-if="subject.has_lab">+ Lab {{ fmt(subject.lab_fee) }}</span>
                                                    </p>
                                                </div>
                                            </div>
                                            <span class="ml-4 flex-shrink-0 text-sm font-semibold text-amber-600">
                                                {{ fmt(subject.total_cost) }}
                                            </span>
                                        </div>
                                    </div>

                                </template>
                            </template>
                        </div>
                    </div>

                    <!-- Selected subjects summary -->
                    <div class="rounded-lg border bg-white shadow-sm">
                        <div class="border-b px-5 py-4 flex items-center justify-between">
                            <div>
                                <h2 class="font-semibold text-gray-900">Selected Subjects</h2>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    {{ selectedSubjects.length }} subject{{ selectedSubjects.length !== 1 ? 's' : '' }} · click a subject or use trash icon to remove
                                </p>
                            </div>
                        </div>

                        <div v-if="selectedSubjects.length === 0"
                             class="px-5 py-8 text-center text-sm text-gray-400">
                            No subjects selected. Use the browser above.
                        </div>

                        <div v-else class="divide-y divide-gray-100">
                            <div v-for="s in selectedSubjects" :key="s.id"
                                 class="flex items-center justify-between px-5 py-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ s.code }} — {{ s.name }}</p>
                                    <p class="text-xs text-gray-400">
                                        {{ s.year_level }} · {{ s.semester }} ·
                                        {{ s.units }} units × {{ fmt(s.price_per_unit) }}
                                        <span v-if="s.has_lab">+ Lab {{ fmt(s.lab_fee) }}</span>
                                    </p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="font-semibold text-gray-900">{{ fmt(s.amount) }}</span>
                                    <button type="button"
                                            class="text-gray-300 transition-colors hover:text-red-500"
                                            @click="removeSelectedSubject(s.id)">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p v-if="formErrors.selected_subjects"
                           class="px-5 pb-3 text-xs text-red-500">
                            {{ formErrors.selected_subjects }}
                        </p>
                    </div>
                </div>

                <!-- Grand Total Banner -->
                <div :class="['rounded-xl px-6 py-5 text-white shadow-lg',
                              assessmentType === 'irregular'
                                ? 'bg-gradient-to-r from-amber-500 to-amber-600'
                                : 'bg-gradient-to-r from-blue-600 to-blue-700']">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="mb-1 text-xs font-medium uppercase tracking-widest opacity-80">
                                Total Assessment Amount
                            </p>
                            <p class="text-4xl font-bold tabular-nums">{{ fmt(grandTotal) }}</p>
                        </div>
                        <div class="space-y-0.5 text-right text-xs opacity-75">
                            <p>{{ assessmentType === 'irregular' ? 'Irregular' : 'Regular' }} Assessment</p>
                            <p>School Year: {{ effectiveSchoolYear || '—' }}</p>
                            <p v-if="assessmentType === 'irregular'">
                                {{ selectedSubjects.length }} subject{{ selectedSubjects.length !== 1 ? 's' : '' }}
                            </p>
                            <p v-else>
                                {{ regularFeeRows.length }} fee line{{ regularFeeRows.length !== 1 ? 's' : '' }}
                            </p>
                            <p>5 payment terms will be generated</p>
                        </div>
                    </div>
                </div>

                <!-- Global errors -->
                <p v-if="formErrors.error" class="text-sm font-medium text-red-600">{{ formErrors.error }}</p>

                <!-- Actions -->
                <div class="flex items-center justify-between pt-2">
                    <Button type="button" variant="outline" @click="backToStudentSelection">
                        ← Back to Student Selection
                    </Button>
                    <div class="flex gap-3">
                        <Link :href="route('student-fees.index')">
                            <Button type="button" variant="outline">Cancel</Button>
                        </Link>
                        <Button
                            type="button"
                            :disabled="form.processing
                                || !yearLevel
                                || !semester
                                || !effectiveSchoolYear
                                || !customYearValid
                                || grandTotal <= 0
                                || (assessmentType === 'irregular' && selectedSubjects.length === 0)
                                || (assessmentType === 'regular' && regularFeeRows.length === 0)"
                            :class="[assessmentType === 'irregular'
                                        ? 'bg-amber-500 hover:bg-amber-600 text-white border-0'
                                        : '',
                                     'min-w-[180px]']"
                            @click="submit">
                            {{ form.processing ? 'Creating…' : 'Create Assessment' }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>