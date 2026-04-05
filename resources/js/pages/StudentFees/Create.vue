<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useDataFormatting } from '@/composables/useDataFormatting';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, BookOpen, ChevronDown, ChevronRight, PenLine, Search, Trash2, User } from 'lucide-vue-next';
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
    suggested_year_level: string | null;
    suggested_semester: string | null;
    latest_assessment: { year_level: string; semester: string; school_year: string } | null;
    activeAssessmentInfo: {
        id: number;
        assessment_number: string;
        year_level: string;
        semester: string;
        school_year: string;
        total_assessment: number;
        remaining_balance: number;
        unpaid_term_count: number;
    } | null;
}

interface SubjectItem {
    id: number;
    code: string;
    name: string;
    course: string;
    lec_units: number;
    lab_units: number;
    total_units: number;
    tuition_cost: number;
    lab_cost: number;
    has_lab: boolean;
    year_level: string;
    semester: string;
}

interface MiscItem {
    name: string;
    category: string;
    amount: number;
}

// subjectMap[course][yearLevel][semester] = SubjectItem[]
type SubjectMap = Record<string, Record<string, Record<string, SubjectItem[]>>>;

// enrollmentsMap[userId][schoolYear] = subjectId[]
//
// Keyed by school year ONLY (not semester) so that both Regular and Irregular
// assessment creation correctly block subjects the student is already enrolled
// in — regardless of which semester the Irregular picker is currently browsing.
type EnrollmentsMap = Record<number, Record<string, number[]>>;

interface Props {
    students: Student[];
    yearLevels: string[];
    semesters: string[];
    schoolYears: string[];
    subjectMap: SubjectMap;
    courses: string[];
    enrollmentsMap: EnrollmentsMap;
    // New props from updated controller — safe defaults prevent crash if old controller is active
    tuitionPerUnit?: number;
    labFeePerSubject?: number;
    miscItems?: MiscItem[];
    miscTotal?: number;
}

const props = withDefaults(defineProps<Props>(), {
    tuitionPerUnit: 364.0,
    labFeePerSubject: 1656.0,
    miscItems: () => [],
    miscTotal: 6956.0,
    enrollmentsMap: () => ({}),
});

// ─── Breadcrumbs ──────────────────────────────────────────────────────────────

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Student Fee Management', href: route('student-fees.index') },
    { title: 'Create Assessment' },
];

// ─── Step & Student ───────────────────────────────────────────────────────────

const currentStep = ref<1 | 2>(1);
const selectedStudent = ref<Student | null>(null);
const studentSearch = ref('');

// ─── Inline course editing ────────────────────────────────────────────────────

const editableCourse = ref<string>('');

const needsCourse = computed<boolean>(() => {
    const c = selectedStudent.value?.course;
    return !c || c.trim() === '' || c === 'N/A';
});

// The effective course for subject browsing
const activeCourse = computed(() => (needsCourse.value ? editableCourse.value : (selectedStudent.value?.course ?? '')));

// ─── Assessment type ──────────────────────────────────────────────────────────

const assessmentType = ref<'regular' | 'irregular'>('regular');

// ─── Active Assessment Guard ─────────────────────────────────────────────────
// Check if selected student has an existing active assessment with unpaid balance.
// This prevents duplicate active assessments per the single-active-per-student rule.

const hasActiveAssessmentWithBalance = computed(() => {
    return selectedStudent.value?.activeAssessmentInfo !== null;
});

const activeAssessmentInfo = computed(() => {
    return selectedStudent.value?.activeAssessmentInfo ?? null;
});

// ─── Already-enrolled subjects — year-scoped ──────────────────────────────────
//
// Reads from enrollmentsMap[userId][schoolYear] — a flat list of subject IDs
// the student is enrolled in across ALL semesters of the selected school year.
//
// Keying on school year (not semester) is the critical fix for Irregular
// assessments: the Irregular picker browses subjects from any semester, so a
// semester-scoped check would miss enrollments from different semesters.
//
// Updates reactively whenever the student or school year changes.
// The semester dropdown does NOT affect this — intentionally.

const alreadyEnrolledIds = computed<Set<number>>(() => {
    if (!selectedStudent.value || !effectiveSchoolYear.value) return new Set();
    const ids = props.enrollmentsMap?.[selectedStudent.value.id]?.[effectiveSchoolYear.value] ?? [];
    return new Set(ids);
});

function isAlreadyEnrolled(subjectId: number): boolean {
    return alreadyEnrolledIds.value.has(subjectId);
}

// ─── Term fields ──────────────────────────────────────────────────────────────

const yearLevel = ref('');
const semester = ref('');
const schoolYear = ref(props.schoolYears[2] || '');
const customSchoolYear = ref('');
const useCustomYear = ref(false);

const effectiveSchoolYear = computed(() => (useCustomYear.value ? customSchoolYear.value.trim() : schoolYear.value));

const customYearValid = computed(() => {
    if (!useCustomYear.value) return true;
    return /^\d{4}-\d{4}$/.test(customSchoolYear.value.trim());
});

// ─── Subject selection ────────────────────────────────────────────────────────

const selectedSubjectIds = ref<number[]>([]);
const subjectSearch = ref('');
const expandedGroups = ref<Set<string>>(new Set());

// For regular: subjects from student's own course × year × semester
// For irregular: subjects from any course the picker browses

// ── Irregular picker state ────────────────────────────────────────────────────
const pickerCourse = ref('');
const pickerYearLevel = ref('');
const pickerSemester = ref('');
const pickerExpanded = ref(false);

// Subjects available in the irregular picker combination
const pickerSubjects = computed<SubjectItem[]>(() => {
    if (!pickerCourse.value || !pickerYearLevel.value || !pickerSemester.value) return [];
    return props.subjectMap?.[pickerCourse.value]?.[pickerYearLevel.value]?.[pickerSemester.value] ?? [];
});

// ── Regular: subjects for current student/term ────────────────────────────────
// Grouped accordion for the regular subject browser
const regularGroups = computed(() => {
    const course = activeCourse.value;
    if (!course || !props.subjectMap[course]) return {};
    const search = (subjectSearch.value ?? '').toLowerCase();
    const result: Record<string, Record<string, SubjectItem[]>> = {};
    for (const yl of Object.keys(props.subjectMap[course])) {
        for (const sem of Object.keys(props.subjectMap[course][yl])) {
            const items = props.subjectMap[course][yl][sem].filter(
                (s) => !search || s.code.toLowerCase().includes(search) || s.name.toLowerCase().includes(search),
            );
            if (!items.length) continue;
            if (!result[yl]) result[yl] = {};
            result[yl][sem] = items;
        }
    }
    return result;
});

// All selected SubjectItem objects (for display in summary)
const selectedSubjects = computed<SubjectItem[]>(() => {
    const all: SubjectItem[] = [];
    for (const course of Object.keys(props.subjectMap)) {
        for (const yl of Object.keys(props.subjectMap[course])) {
            for (const sem of Object.keys(props.subjectMap[course][yl])) {
                for (const s of props.subjectMap[course][yl][sem]) {
                    if (selectedSubjectIds.value.includes(s.id)) all.push(s);
                }
            }
        }
    }
    return all;
});

function isSelected(id: number) {
    return selectedSubjectIds.value.includes(id);
}

function toggleSubject(id: number) {
    // Already-enrolled subjects are blocked — clicking them is a no-op
    if (isAlreadyEnrolled(id)) return;
    if (isSelected(id)) {
        selectedSubjectIds.value = selectedSubjectIds.value.filter((x) => x !== id);
    } else {
        selectedSubjectIds.value.push(id);
    }
}

function removeSubject(id: number) {
    selectedSubjectIds.value = selectedSubjectIds.value.filter((x) => x !== id);
}

function addAllPickerSubjects() {
    for (const s of pickerSubjects.value) {
        if (!isSelected(s.id) && !isAlreadyEnrolled(s.id)) {
            selectedSubjectIds.value.push(s.id);
        }
    }
}

function toggleGroup(key: string) {
    if (expandedGroups.value.has(key)) expandedGroups.value.delete(key);
    else expandedGroups.value.add(key);
}

// ── Pre-load regular subjects for current term ────────────────────────────────

function preloadRegularSubjects() {
    if (!activeCourse.value || !yearLevel.value || !semester.value) return;
    const subjectsForTerm = props.subjectMap?.[activeCourse.value]?.[yearLevel.value]?.[semester.value] ?? [];
    // Exclude subjects the student is already enrolled in (any semester of this school year)
    selectedSubjectIds.value = subjectsForTerm.filter((s) => !isAlreadyEnrolled(s.id)).map((s) => s.id);
    // Auto-expand current term group
    const key = `${yearLevel.value}||${semester.value}`;
    expandedGroups.value = new Set([key]);
}

// Watch year/semester changes for regular mode
watch([yearLevel, semester], () => {
    if (assessmentType.value === 'regular') {
        selectedSubjectIds.value = [];
        expandedGroups.value = new Set();
        preloadRegularSubjects();
    }
});

// When school year changes, strip any already-enrolled subjects from the current
// selection that are now blocked under the newly selected year.
watch(effectiveSchoolYear, () => {
    if (selectedSubjectIds.value.length === 0) return;
    selectedSubjectIds.value = selectedSubjectIds.value.filter((id) => !isAlreadyEnrolled(id));
});

// When switching to irregular, clear preloaded selection
watch(assessmentType, (newType) => {
    selectedSubjectIds.value = [];
    expandedGroups.value = new Set();
    subjectSearch.value = '';
    if (newType === 'irregular') {
        // Pre-populate picker with student's course
        pickerCourse.value = activeCourse.value;
        pickerYearLevel.value = yearLevel.value;
        pickerSemester.value = semester.value;
        pickerExpanded.value = true;
    } else {
        preloadRegularSubjects();
    }
});

// ─── Fee calculation ──────────────────────────────────────────────────────────

const tuitionTotal = computed(() => selectedSubjects.value.reduce((sum, s) => sum + s.tuition_cost, 0));

const labTotal = computed(() => selectedSubjects.value.reduce((sum, s) => sum + s.lab_cost, 0));

const grandTotal = computed(() => tuitionTotal.value + labTotal.value + props.miscTotal);

// Total units (LEC + LAB combined) — used in summary header and grand total banner.
const totalUnits = computed(() => selectedSubjects.value.reduce((sum, s) => sum + s.total_units, 0));

// ─── FIX #1: lecUnitsTotal — LEC-only unit count for the tuition breakdown label ──
//
// WHY: tuitionTotal is computed from s.tuition_cost, which is (lec_units × rate).
// Lab units are NOT billed as tuition — they are billed as a flat lab_cost per subject.
// Displaying "totalUnits × rate" in the breakdown label was factually wrong:
//   e.g. 3 LEC + 1 LAB = 4 totalUnits, but tuition = 3 × ₱364 = ₱1,092 — not 4 × ₱364.
// The label must show LEC units only so the formula matches the displayed amount.
const lecUnitsTotal = computed(() => selectedSubjects.value.reduce((sum, s) => sum + s.lec_units, 0));

const labUnitsTotal = computed(() => selectedSubjects.value.reduce((sum, s) => sum + s.lab_units, 0));

const labSubjectCount = computed(() => selectedSubjects.value.filter((s) => s.lab_cost > 0).length);

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

// ─── Student selection ────────────────────────────────────────────────────────

function selectStudent(student: Student) {
    selectedStudent.value = student;
    const existingCourse = student.course;
    editableCourse.value = existingCourse && existingCourse !== 'N/A' ? existingCourse : '';
    yearLevel.value = student.suggested_year_level || student.year_level || '';
    if (student.suggested_semester) semester.value = student.suggested_semester;
    assessmentType.value = student.is_irregular ? 'irregular' : 'regular';
    selectedSubjectIds.value = [];
    expandedGroups.value = new Set();
    subjectSearch.value = '';
    pickerCourse.value = needsCourse.value ? '' : (student.course ?? '');
    pickerYearLevel.value = '';
    pickerSemester.value = '';
    pickerExpanded.value = false;
    currentStep.value = 2;
}

function backToStudentSelection() {
    currentStep.value = 1;
    selectedStudent.value = null;
    editableCourse.value = '';
    selectedSubjectIds.value = [];
    yearLevel.value = '';
    semester.value = '';
    expandedGroups.value = new Set();
    pickerCourse.value = '';
    pickerYearLevel.value = '';
    pickerSemester.value = '';
    pickerExpanded.value = false;
}

// ─── Submit ───────────────────────────────────────────────────────────────────

const form: any = useForm({});
const formErrors = ref<Record<string, string>>({});

function submit() {
    formErrors.value = {};

    // Guard: prevent submission if student has active assessment with unpaid balance
    if (hasActiveAssessmentWithBalance.value) {
        formErrors.value.assessment =
            'Student already has an active assessment with remaining balance. Please complete the current assessment before creating a new one.';
        return;
    }

    if (!yearLevel.value || !semester.value) {
        formErrors.value.term = 'Please select a year level and semester.';
        return;
    }

    if (useCustomYear.value && !customYearValid.value) {
        formErrors.value.school_year = 'School year must be in YYYY-YYYY format (e.g. 2025-2026).';
        return;
    }

    if (needsCourse.value && !editableCourse.value.trim()) {
        formErrors.value.course = 'Please assign a course to this student before creating an assessment.';
        return;
    }

    if (selectedSubjectIds.value.length === 0) {
        formErrors.value.selected_subjects = 'Please select at least one subject.';
        return;
    }

    // Client-side guard: catch any already-enrolled subject that snuck in
    const blocked = selectedSubjectIds.value.filter((id) => isAlreadyEnrolled(id));
    if (blocked.length > 0) {
        formErrors.value.selected_subjects = 'One or more selected subjects are already enrolled for this school year. Please remove them.';
        return;
    }

    const payload = {
        user_id: selectedStudent.value!.id,
        year_level: yearLevel.value,
        semester: semester.value,
        school_year: effectiveSchoolYear.value,
        assessment_type: assessmentType.value,
        course: needsCourse.value ? editableCourse.value.trim() : (selectedStudent.value!.course ?? ''),
        selected_subjects: selectedSubjectIds.value,
    };

    form.transform(() => payload).post(route('student-fees.store'), {
        preserveScroll: true,
        onError: (errors: any) => {
            formErrors.value = errors;
        },
    });
}

const { formatCurrency } = useDataFormatting();

// ─── Helpers ──────────────────────────────────────────────────────────────────

function statusColor(status: string) {
    return status === 'active' ? 'bg-green-100 text-green-800' : status === 'graduated' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800';
}
</script>

<template>
    <Head title="Create Student Assessment" />

    <AppLayout>
        <div class="mx-auto max-w-6xl space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center gap-4">
                <Link :href="route('student-fees.index')">
                    <Button variant="outline" size="sm" class="flex items-center gap-2"> <ArrowLeft class="h-4 w-4" /> Back </Button>
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
                    <div
                        :class="[
                            'flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold',
                            currentStep >= 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500',
                        ]"
                    >
                        1
                    </div>
                    <span class="text-sm font-medium">Select Student</span>
                </div>
                <div class="h-px w-20 bg-gray-200">
                    <div :class="['h-full transition-all duration-300', currentStep >= 2 ? 'w-full bg-blue-600' : 'w-0']" />
                </div>
                <div class="flex items-center gap-2">
                    <div
                        :class="[
                            'flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold',
                            currentStep >= 2 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500',
                        ]"
                    >
                        2
                    </div>
                    <span class="text-sm font-medium">Select Subjects</span>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════
                 STEP 1 — Student Selection
                 ════════════════════════════════════════════════ -->
            <div v-if="currentStep === 1" class="space-y-4">
                <div class="rounded-lg border bg-white p-4 shadow-sm">
                    <div class="relative">
                        <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <Input v-model="studentSearch" placeholder="Search by ID, name, email, or course…" class="pl-9" />
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border bg-white shadow-sm">
                    <div class="border-b px-6 py-4">
                        <h2 class="font-semibold">Active Students</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Click a row to select</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase">
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
                                <tr
                                    v-for="st in filteredStudents"
                                    :key="st.id"
                                    class="cursor-pointer transition-colors hover:bg-blue-50"
                                    @click="selectStudent(st)"
                                >
                                    <td class="px-5 py-3 font-mono text-xs font-medium text-gray-900">{{ st.account_id }}</td>
                                    <td class="px-5 py-3">
                                        <div class="font-medium text-gray-900">{{ st.name }}</div>
                                        <div class="text-xs text-gray-400">{{ st.email }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-gray-600">{{ st.course || '—' }}</td>
                                    <td class="px-5 py-3 text-xs">
                                        <span
                                            v-if="st.suggested_year_level && st.suggested_year_level !== st.year_level"
                                            class="font-semibold text-blue-700"
                                            >{{ st.suggested_year_level }}</span
                                        >
                                        <span v-else class="text-gray-600">{{ st.year_level || '—' }}</span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span
                                            :class="[
                                                'rounded-full px-2 py-0.5 text-xs font-semibold',
                                                st.is_irregular ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700',
                                            ]"
                                        >
                                            {{ st.is_irregular ? 'Irregular' : 'Regular' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusColor(st.status)">
                                            {{ st.status }}
                                        </span>
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
                <!-- Student banner -->
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
                                    <span v-if="needsCourse" class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-700"
                                        >Required</span
                                    >
                                </p>
                                <p v-if="!needsCourse" class="font-semibold text-gray-900">{{ selectedStudent?.course }}</p>
                                <div v-else class="mt-1">
                                    <select
                                        v-model="editableCourse"
                                        class="w-full rounded-md border border-amber-400 bg-white px-2 py-1.5 text-sm font-semibold text-gray-900 focus:ring-2 focus:ring-amber-400 focus:outline-none"
                                    >
                                        <option value="">— Select Course —</option>
                                        <option v-for="c in courses" :key="c" :value="c">{{ c }}</option>
                                    </select>
                                    <p v-if="formErrors.course" class="mt-1 text-xs text-red-600">{{ formErrors.course }}</p>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Current Year</p>
                                <p class="font-semibold text-gray-900">
                                    {{ selectedStudent?.latest_assessment?.year_level || selectedStudent?.year_level || '—' }}
                                </p>
                            </div>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="backToStudentSelection" class="flex-shrink-0">Change</Button>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-4 border-t border-blue-200 pt-3 text-xs">
                        <div v-if="selectedStudent?.latest_assessment">
                            <span class="text-gray-500">Last Assessment: </span>
                            <span class="font-semibold text-gray-700">
                                {{ selectedStudent.latest_assessment.year_level }}
                                {{ selectedStudent.latest_assessment.semester }}
                                {{ selectedStudent.latest_assessment.school_year }}
                            </span>
                        </div>
                        <div v-else class="text-gray-400">No previous assessment on record.</div>
                        <div v-if="selectedStudent?.suggested_year_level" class="rounded-full bg-green-100 px-3 py-1 font-medium text-green-700">
                            ✓ Auto-filled: {{ selectedStudent.suggested_year_level }}
                            <span v-if="selectedStudent.suggested_semester"> · {{ selectedStudent.suggested_semester }}</span>
                        </div>
                    </div>
                </div>

                <!-- Rate info banner -->
                <div class="flex flex-wrap gap-6 rounded-lg border border-blue-100 bg-blue-50 px-5 py-3 text-xs text-blue-800">
                    <span
                        >Tuition rate: <strong>{{ formatCurrency(tuitionPerUnit ?? 364) }}/unit</strong></span
                    >
                    <span
                        >Lab fee: <strong>{{ formatCurrency(labFeePerSubject ?? 1656) }}/lab subject</strong></span
                    >
                    <span
                        >Fixed misc: <strong>{{ formatCurrency(miscTotal ?? 6956) }}/semester</strong></span
                    >
                    <span class="text-blue-500">Rate of Conduct of Consultation, April 2025 (AY 2025-2026)</span>
                </div>

                <!-- Term Information -->
                <div class="rounded-lg border bg-white p-5 shadow-sm">
                    <h2 class="mb-4 font-semibold text-gray-900">Term Information</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="space-y-1">
                            <Label>Year Level</Label>
                            <select
                                v-model="yearLevel"
                                required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            >
                                <option value="">Select year level</option>
                                <option v-for="y in yearLevels" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <Label>Semester</Label>
                            <select
                                v-model="semester"
                                required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            >
                                <option value="">Select semester</option>
                                <option v-for="s in semesters" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <Label class="flex items-center gap-2">
                                School Year
                                <button
                                    type="button"
                                    :class="[
                                        'rounded px-1.5 py-0.5 text-xs font-medium transition-colors',
                                        useCustomYear ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500 hover:bg-gray-200',
                                    ]"
                                    @click="
                                        useCustomYear = !useCustomYear;
                                        customSchoolYear = '';
                                    "
                                >
                                    <PenLine class="mr-0.5 inline h-3 w-3" />
                                    {{ useCustomYear ? 'Custom ✓' : 'Custom' }}
                                </button>
                            </Label>
                            <select
                                v-if="!useCustomYear"
                                v-model="schoolYear"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            >
                                <option v-for="sy in schoolYears" :key="sy" :value="sy">{{ sy }}</option>
                            </select>
                            <div v-else>
                                <input
                                    v-model="customSchoolYear"
                                    type="text"
                                    placeholder="e.g. 2026-2027"
                                    :class="[
                                        'w-full rounded-lg border px-3 py-2 text-sm outline-none focus:ring-2',
                                        customYearValid
                                            ? 'border-blue-400 focus:border-blue-500 focus:ring-blue-200'
                                            : 'border-red-400 focus:border-red-500 focus:ring-red-200',
                                    ]"
                                />
                                <p v-if="!customYearValid && customSchoolYear" class="mt-1 text-xs text-red-500">Format: YYYY-YYYY</p>
                            </div>
                            <p v-if="formErrors.school_year" class="mt-1 text-xs text-red-500">{{ formErrors.school_year }}</p>
                        </div>
                    </div>
                    <p v-if="formErrors.term" class="mt-2 text-xs text-red-500">{{ formErrors.term }}</p>

                    <!-- Already-enrolled notice — visible when school year is selected and student has enrollments -->
                    <div
                        v-if="alreadyEnrolledIds.size > 0"
                        class="mt-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                    >
                        <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        <span>
                            <strong>{{ alreadyEnrolledIds.size }} subject{{ alreadyEnrolledIds.size !== 1 ? 's' : '' }} already enrolled</strong>
                            in <strong>{{ effectiveSchoolYear }}</strong> — greyed out in both Regular and Irregular selection and cannot be
                            re-selected.
                        </span>
                    </div>
                </div>

                <!-- Assessment Type Toggle -->
                <div class="rounded-lg border bg-white p-5 shadow-sm">
                    <h2 class="mb-1 font-semibold text-gray-900">Assessment Type</h2>
                    <p class="mb-4 text-xs text-gray-500">
                        Regular = standard full-term subject load. Irregular = custom mix of subjects across courses.
                    </p>
                    <div class="flex gap-3">
                        <button
                            type="button"
                            :class="[
                                'flex-1 rounded-lg border-2 px-5 py-4 text-left transition-all',
                                assessmentType === 'regular' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300',
                            ]"
                            @click="assessmentType = 'regular'"
                        >
                            <p :class="['font-bold', assessmentType === 'regular' ? 'text-blue-700' : 'text-gray-700']">Regular</p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                All subjects for the student's course, year, and semester are pre-selected. Remove any they are not taking.
                            </p>
                        </button>
                        <button
                            type="button"
                            :class="[
                                'flex-1 rounded-lg border-2 px-5 py-4 text-left transition-all',
                                assessmentType === 'irregular' ? 'border-amber-500 bg-amber-50' : 'border-gray-200 hover:border-gray-300',
                            ]"
                            @click="assessmentType = 'irregular'"
                        >
                            <p :class="['font-bold', assessmentType === 'irregular' ? 'text-amber-700' : 'text-gray-700']">Irregular</p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                Manually pick subjects from any course. Use this when a student takes subjects from multiple programs.
                            </p>
                        </button>
                    </div>
                </div>

                <!-- ════════════════════════════════════════
                     REGULAR — subject browser from student's course
                     ════════════════════════════════════════ -->
                <div v-if="assessmentType === 'regular'" class="rounded-lg border bg-white shadow-sm">
                    <div class="border-b px-5 py-4">
                        <div class="flex items-center gap-2">
                            <BookOpen class="h-4 w-4 text-blue-500" />
                            <h2 class="font-semibold text-gray-900">Regular Assessment</h2>
                        </div>
                        <div class="mt-2 flex items-center justify-between">
                            <div class="space-y-1">
                                <p class="text-sm font-medium text-gray-700">{{ totalUnits }} Units = {{ lecUnitsTotal }} LEC · {{ labUnitsTotal }} LAB</p>
                                <p class="text-xs text-gray-600">School Year: {{ effectiveSchoolYear }}</p>
                                <p class="text-xs text-gray-600">5 payment terms will be generated</p>
                            </div>
                            <p v-if="activeCourse && yearLevel && semester" class="text-xs text-green-600">
                                ✓ Pre-selected — uncheck any subject the student is NOT taking
                            </p>
                            <p v-else class="text-xs text-gray-400">Select year level and semester to load subjects</p>
                        </div>
                    </div>

                    <div class="border-b px-5 py-3">
                        <div class="relative">
                            <Search class="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                            <input
                                v-model="subjectSearch"
                                type="text"
                                placeholder="Search subjects…"
                                class="w-full rounded-lg border border-gray-200 py-1.5 pr-3 pl-8 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-200"
                            />
                        </div>
                    </div>

                    <div v-if="!activeCourse || !yearLevel || !semester" class="px-5 py-10 text-center text-sm text-gray-400">
                        Select course, year level, and semester above to load subjects.
                    </div>
                    <div v-else-if="Object.keys(regularGroups).length === 0" class="px-5 py-10 text-center text-sm text-gray-400">
                        No subjects found for <strong>{{ activeCourse }}</strong
                        >. Run the subject seeder.
                    </div>
                    <div v-else class="divide-y divide-gray-100">
                        <template v-for="(bySem, yl) in regularGroups" :key="yl">
                            <template v-for="(subjects, sem) in bySem" :key="sem">
                                <button
                                    type="button"
                                    :class="[
                                        'flex w-full items-center justify-between px-5 py-2.5 text-left transition-colors hover:bg-gray-100',
                                        yl === yearLevel && sem === semester ? 'bg-blue-50' : 'bg-gray-50',
                                    ]"
                                    @click="toggleGroup(`${yl}||${sem}`)"
                                >
                                    <span class="flex items-center gap-2 text-xs font-semibold tracking-wide text-gray-600 uppercase">
                                        {{ yl }} — {{ sem }}
                                        <span
                                            v-if="yl === yearLevel && sem === semester"
                                            class="rounded-full bg-blue-200 px-1.5 py-0.5 font-normal text-blue-700 normal-case"
                                            >current term</span
                                        >
                                        <span class="font-normal text-gray-400">({{ subjects.length }})</span>
                                    </span>
                                    <ChevronDown v-if="expandedGroups.has(`${yl}||${sem}`)" class="h-4 w-4 text-gray-400" />
                                    <ChevronRight v-else class="h-4 w-4 text-gray-400" />
                                </button>
                                <div v-if="expandedGroups.has(`${yl}||${sem}`)" class="divide-y divide-gray-50">
                                    <div
                                        v-for="s in subjects"
                                        :key="s.id"
                                        :class="[
                                            'flex items-center justify-between px-5 py-3 transition-colors',
                                            isAlreadyEnrolled(s.id)
                                                ? 'cursor-not-allowed bg-gray-50 opacity-60'
                                                : isSelected(s.id)
                                                  ? 'cursor-pointer bg-blue-50'
                                                  : 'cursor-pointer hover:bg-gray-50',
                                        ]"
                                        @click="toggleSubject(s.id)"
                                    >
                                        <div class="flex items-start gap-3">
                                            <div
                                                :class="[
                                                    'mt-0.5 flex h-4 w-4 flex-shrink-0 items-center justify-center rounded border text-xs font-bold',
                                                    isAlreadyEnrolled(s.id)
                                                        ? 'border-gray-300 bg-gray-200 text-gray-400'
                                                        : isSelected(s.id)
                                                          ? 'border-blue-500 bg-blue-500 text-white'
                                                          : 'border-gray-300 bg-white',
                                                ]"
                                            >
                                                <span v-if="isAlreadyEnrolled(s.id) || isSelected(s.id)">✓</span>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">
                                                    {{ s.code }} — {{ s.name }}
                                                    <span
                                                        v-if="isAlreadyEnrolled(s.id)"
                                                        class="ml-2 inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-500"
                                                    >
                                                        Already Enrolled
                                                    </span>
                                                </p>
                                                <p class="text-xs text-gray-400">
                                                    {{ s.lec_units }} LEC {{ s.lab_units > 0 ? '+ ' + s.lab_units + ' LAB' : '' }} units ·
                                                    Tuition: {{ formatCurrency(s.tuition_cost) }}
                                                    <span v-if="s.lab_cost > 0" class="ml-1 text-purple-600">· Lab {{ formatCurrency(s.lab_cost) }}</span>
                                                </p>
                                            </div>
                                        </div>
                                        <span
                                            class="ml-4 flex-shrink-0 text-sm font-semibold"
                                            :class="isAlreadyEnrolled(s.id) ? 'text-gray-400' : 'text-blue-700'"
                                        >
                            {{ formatCurrency(s.tuition_cost + s.lab_cost) }}
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </template>
                    </div>
                </div>

                <!-- ════════════════════════════════════════
                     IRREGULAR — multi-course subject picker
                     ════════════════════════════════════════ -->
                <div v-if="assessmentType === 'irregular'" class="rounded-lg border bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b px-5 py-4">
                        <div class="flex items-center gap-2">
                            <BookOpen class="h-4 w-4 text-amber-500" />
                            <h2 class="font-semibold text-gray-900">Subject Picker — Irregular</h2>
                        </div>
                        <span class="text-xs text-amber-700">Select subjects from any course, year, and semester</span>
                    </div>

                    <!-- Assessment Summary -->
                    <div class="border-b px-5 py-4">
                        <div class="flex items-center justify-between">
                            <div class="space-y-1">
                                <p class="text-sm font-medium text-gray-700">{{ totalUnits }} Units = {{ lecUnitsTotal }} LEC · {{ labUnitsTotal }} LAB</p>
                                <p class="text-xs text-gray-600">School Year: {{ effectiveSchoolYear }}</p>
                                <p class="text-xs text-gray-600">5 payment terms will be generated</p>
                            </div>
                        </div>
                    </div>

                    <!-- Picker selectors -->
                    <div class="space-y-3 border-b bg-amber-50 px-5 py-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-amber-800">Browse Subjects by Course</p>
                            <button
                                type="button"
                                class="text-xs text-amber-700 underline hover:no-underline"
                                @click="pickerExpanded = !pickerExpanded"
                            >
                                {{ pickerExpanded ? 'Collapse' : 'Expand' }}
                            </button>
                        </div>

                        <div v-if="pickerExpanded" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Course</label>
                                <select
                                    v-model="pickerCourse"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-200"
                                >
                                    <option value="">— Select Course —</option>
                                    <option v-for="c in courses" :key="c" :value="c">{{ c }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Year Level</label>
                                <select
                                    v-model="pickerYearLevel"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-200"
                                >
                                    <option value="">— Select Year —</option>
                                    <option v-for="y in yearLevels" :key="y" :value="y">{{ y }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Semester</label>
                                <select
                                    v-model="pickerSemester"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-200"
                                >
                                    <option value="">— Select Semester —</option>
                                    <option v-for="s in semesters" :key="s" :value="s">{{ s }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Subjects from selected picker combination -->
                        <div v-if="pickerExpanded && pickerSubjects.length > 0" class="overflow-hidden rounded-lg border border-amber-200">
                            <div class="flex items-center justify-between bg-amber-100 px-4 py-2">
                                <span class="text-xs font-semibold text-amber-800">
                                    {{ pickerCourse }} · {{ pickerYearLevel }} · {{ pickerSemester }} ({{ pickerSubjects.length }} subjects)
                                </span>
                                <button
                                    type="button"
                                    class="text-xs font-medium text-amber-700 underline hover:no-underline"
                                    @click="addAllPickerSubjects"
                                >
                                    Add All
                                </button>
                            </div>
                            <div class="divide-y divide-amber-100">
                                <div
                                    v-for="s in pickerSubjects"
                                    :key="s.id"
                                    :class="[
                                        'flex items-center justify-between px-4 py-2.5 transition-colors',
                                        isAlreadyEnrolled(s.id)
                                            ? 'cursor-not-allowed bg-gray-50 opacity-60'
                                            : isSelected(s.id)
                                              ? 'cursor-pointer bg-amber-50'
                                              : 'cursor-pointer bg-white hover:bg-amber-50',
                                    ]"
                                    @click="toggleSubject(s.id)"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            :class="[
                                                'flex h-4 w-4 flex-shrink-0 items-center justify-center rounded border text-xs font-bold',
                                                isAlreadyEnrolled(s.id)
                                                    ? 'border-gray-300 bg-gray-200 text-gray-400'
                                                    : isSelected(s.id)
                                                      ? 'border-amber-500 bg-amber-500 text-white'
                                                      : 'border-gray-300 bg-white',
                                            ]"
                                        >
                                            <span v-if="isAlreadyEnrolled(s.id) || isSelected(s.id)">✓</span>
                                        </div>
                                        <div>
                                            <span class="text-sm font-medium text-gray-900">
                                                {{ s.code }} — {{ s.name }}
                                                <span
                                                    v-if="isAlreadyEnrolled(s.id)"
                                                    class="ml-2 inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-500"
                                                >
                                                    Already Enrolled
                                                </span>
                                            </span>
                                            <p class="text-xs text-gray-400">
                                                {{ s.lec_units }} LEC {{ s.lab_units > 0 ? '+ ' + s.lab_units + ' LAB' : '' }} units ·
                                                Tuition: {{ formatCurrency(s.tuition_cost) }}
                                                <span v-if="s.lab_cost > 0" class="text-purple-600"> · Lab {{ formatCurrency(s.lab_cost) }}</span>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="text-sm font-semibold" :class="isAlreadyEnrolled(s.id) ? 'text-gray-400' : 'text-amber-700'">
                                        {{ formatCurrency(s.tuition_cost + s.lab_cost) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <p v-else-if="pickerExpanded && pickerCourse && pickerYearLevel && pickerSemester" class="text-xs text-gray-400 italic">
                            No subjects found for this combination.
                        </p>
                        <p v-else-if="!pickerExpanded" class="text-xs text-amber-700">
                            Click "Expand" to browse and select subjects from any course.
                        </p>
                    </div>
                </div>

                <!-- ════════════════════════════════════════
                     SELECTED SUBJECTS SUMMARY (both types)
                     ════════════════════════════════════════ -->
                <div class="rounded-lg border bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b px-5 py-4">
                        <div>
                            <h2 class="font-semibold text-gray-900">Selected Subjects</h2>
                            <p class="mt-0.5 text-xs text-gray-500">
                                {{ selectedSubjects.length }} subject{{ selectedSubjects.length !== 1 ? 's' : '' }} · {{ lecUnitsTotal }} LEC {{ labUnitsTotal > 0 ? '+ ' + labUnitsTotal + ' LAB' : '' }} = {{ totalUnits }} units
                            </p>
                        </div>
                    </div>

                    <div v-if="selectedSubjects.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                        No subjects selected. Use the browser above.
                    </div>

                    <div v-else class="divide-y divide-gray-100">
                        <div v-for="s in selectedSubjects" :key="s.id" class="flex items-center justify-between px-5 py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ s.code }} — {{ s.name }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ s.course }} · {{ s.year_level }} · {{ s.semester }} · {{ s.lec_units }} LEC {{ s.lab_units > 0 ? '+ ' + s.lab_units + ' LAB' : '' }} units ·
                                    Tuition {{ formatCurrency(s.tuition_cost) }}
                                    <span v-if="s.lab_cost > 0" class="text-purple-600"> · Lab {{ formatCurrency(s.lab_cost) }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="font-semibold text-gray-900">{{ formatCurrency(s.tuition_cost + s.lab_cost) }}</span>
                                <button type="button" class="text-gray-300 transition-colors hover:text-red-500" @click="removeSubject(s.id)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <p v-if="formErrors.selected_subjects" class="px-5 pb-3 text-xs text-red-500">
                        {{ formErrors.selected_subjects }}
                    </p>
                </div>

                <!-- Fee breakdown summary -->
                <div v-if="selectedSubjects.length > 0" class="overflow-hidden rounded-lg border bg-white shadow-sm">
                    <div class="border-b bg-gray-50 px-5 py-3">
                        <h2 class="text-sm font-semibold text-gray-700">Assessment Breakdown</h2>
                    </div>
                    <div class="space-y-2 px-5 py-4 text-sm">
                        <div class="flex justify-between">
                            <!--
                                FIX #1: Use lecUnitsTotal (LEC-only) instead of totalUnits (LEC+LAB combined).
                                Tuition is charged per lecture unit only. Lab units have their own flat fee.
                                The formula in this label must match the tuitionTotal value shown on the right:
                                  lecUnitsTotal × tuitionPerUnit = tuitionTotal  ✓
                                  totalUnits    × tuitionPerUnit ≠ tuitionTotal  ✗ (was wrong before this fix)
                            -->
                            <span class="text-gray-600">Tuition ({{ lecUnitsTotal }} LEC units × {{ formatCurrency(tuitionPerUnit ?? 364) }})</span>
                            <span class="font-medium">{{ formatCurrency(tuitionTotal) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600"
                                >Laboratory ({{ labUnitsTotal }} LAB units × {{ formatCurrency(labFeePerSubject ?? 1656) }})</span
                            >
                            <span class="font-medium text-purple-700">{{ formatCurrency(labTotal) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Miscellaneous fees (fixed)</span>
                            <span class="font-medium">{{ formatCurrency(miscTotal) }}</span>
                        </div>
                        <div class="flex justify-between border-t pt-2 text-base font-bold">
                            <span>Total Assessment</span>
                            <span>{{ formatCurrency(grandTotal) }}</span>
                        </div>
                        <!-- Misc breakdown -->
                        <details class="mt-1 text-xs text-gray-400">
                            <summary class="cursor-pointer hover:text-gray-600">View misc breakdown ({{ miscItems.length }} items)</summary>
                            <div class="mt-2 space-y-1 pl-3">
                                <div v-for="item in miscItems" :key="item.name" class="flex justify-between">
                                    <span>{{ item.name }}</span>
                                    <span>{{ formatCurrency(item.amount) }}</span>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>

                <!-- Grand Total Banner -->
                <div
                    :class="[
                        'rounded-xl px-6 py-5 text-white shadow-lg',
                        assessmentType === 'irregular'
                            ? 'bg-gradient-to-r from-amber-500 to-amber-600'
                            : 'bg-gradient-to-r from-blue-600 to-blue-700',
                    ]"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="mb-1 text-xs font-medium tracking-widest uppercase opacity-80">Total Assessment Amount</p>
                            <p class="text-4xl font-bold tabular-nums">{{ formatCurrency(grandTotal) }}</p>
                        </div>
                        <div class="space-y-0.5 text-right text-xs opacity-75">
                            <p>{{ assessmentType === 'irregular' ? 'Irregular' : 'Regular' }} Assessment</p>
                            <p>{{ totalUnits }} Units = {{ lecUnitsTotal }} LEC · {{ labUnitsTotal }} LAB</p>
                            <p>School Year: {{ effectiveSchoolYear || '—' }}</p>
                            <p>5 payment terms will be generated</p>
                        </div>
                    </div>
                </div>

                <p v-if="formErrors.error" class="text-sm font-medium text-red-600">{{ formErrors.error }}</p>

                <!-- Active Assessment Warning -->
                <div
                    v-if="hasActiveAssessmentWithBalance && activeAssessmentInfo"
                    class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3"
                >
                    <AlertCircle class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600" />
                    <div class="text-sm">
                        <p class="font-semibold text-red-900">Cannot Create New Assessment</p>
                        <p class="mt-1 text-red-800">This student has an active assessment with an outstanding balance:</p>
                        <div class="mt-2 rounded bg-white/50 px-3 py-2 text-xs text-red-700">
                            <p><strong>Assessment:</strong> {{ activeAssessmentInfo.assessment_number }}</p>
                            <p>
                                <strong>Term:</strong> {{ activeAssessmentInfo.year_level }} — {{ activeAssessmentInfo.semester }} ({{
                                    activeAssessmentInfo.school_year
                                }})
                            </p>
                            <p>
                                <strong>Outstanding Balance:</strong> ₱{{
                                    activeAssessmentInfo.remaining_balance.toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    })
                                }}
                            </p>
                            <p class="mt-1"><strong>Unpaid Terms:</strong> {{ activeAssessmentInfo.unpaid_term_count }} of 5</p>
                        </div>
                        <p class="mt-2 text-red-800">Please complete the current assessment before creating a new one.</p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between pt-2">
                    <Button type="button" variant="outline" @click="backToStudentSelection"> ← Back to Student Selection </Button>
                    <div class="flex gap-3">
                        <Link :href="route('student-fees.index')">
                            <Button type="button" variant="outline">Cancel</Button>
                        </Link>
                        <Button
                            type="button"
                            :disabled="
                                form.processing ||
                                hasActiveAssessmentWithBalance ||
                                !yearLevel ||
                                !semester ||
                                !effectiveSchoolYear ||
                                !customYearValid ||
                                selectedSubjectIds.length === 0
                            "
                            :class="[assessmentType === 'irregular' ? 'border-0 bg-amber-500 text-white hover:bg-amber-600' : '', 'min-w-[180px]']"
                            @click="submit"
                        >
                            {{ form.processing ? 'Creating…' : 'Create Assessment' }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>