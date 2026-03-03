<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Edit, Eye, Plus, Search, TrendingDown, TrendingUp, UserPlus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface PaymentTerm {
    id: number;
    term_name: string;
    term_order: number;
    amount: number;
    balance: number;
    status: string;
    due_date: string | null;
}

interface Assessment {
    id: number;
    total_assessment: number;
    paymentTerms: PaymentTerm[];
}

interface Student {
    id: number;
    student_id: string;
    name: string;
    course: string;
    year_level: string;
    status: string;
    account: { balance: number } | null;
    latestAssessment: Assessment | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    students: {
        data: Student[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
    };
    filters: {
        search?: string;
        course?: string;
        year_level?: string;
        status?: string;
    };
    courses: string[];
    yearLevels: string[];
    statuses: Record<string, string>;
}

const props = defineProps<Props>();

const breadcrumbs = [{ title: 'Dashboard', href: route('dashboard') }, { title: 'Student Fee Management' }];

const search = ref(props.filters.search || '');
const selectedCourse = ref(props.filters.course || '');
const selectedYearLevel = ref(props.filters.year_level || '');
const selectedStatus = ref(props.filters.status || '');

let searchTimeout: ReturnType<typeof setTimeout>;
const performSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            route('student-fees.index'),
            {
                search: search.value,
                course: selectedCourse.value,
                year_level: selectedYearLevel.value,
                status: selectedStatus.value,
            },
            { preserveState: true, replace: true },
        );
    }, 300);
};

watch([search, selectedCourse, selectedYearLevel, selectedStatus], () => {
    performSearch();
});

const getStatusColor = (status: string) => {
    switch (status) {
        case 'active':
            return 'bg-green-500 text-white';
        case 'graduated':
            return 'bg-blue-500 text-white';
        case 'dropped':
            return 'bg-red-500 text-white';
        default:
            return 'bg-gray-500 text-white';
    }
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
};

/**
 * Accurate remaining balance — resolved in priority order:
 *
 * 1. account.balance  (most authoritative when > 0)
 *    AccountService keeps this as: SUM(charge txns) - SUM(paid payment txns)
 *
 * 2. SUM(latestAssessment.paymentTerms[].balance)  (fallback)
 *    Covers students whose assessment was created without charge transactions
 *    (e.g. jcdc742713@gmail.com seeded via StudentFirstPaymentSeeder).
 *    Without this fallback, their balance shows as ₱0 even though ₱15,540 is owed.
 */
const getRemainingBalance = (student: Student): number => {
    const accountBal = parseFloat(String(student.account?.balance ?? 0));
    if (accountBal > 0) return accountBal;

    // Fallback: sum unpaid payment term balances from the eager-loaded assessment
    const terms = student.latestAssessment?.paymentTerms ?? [];
    if (terms.length > 0) {
        const termsTotal = terms.reduce((sum: number, t: PaymentTerm) => sum + parseFloat(String(t.balance)), 0);
        if (termsTotal > 0) return termsTotal;
    }

    return 0;
};

/**
 * Payment timing status:
 * - Count total terms and how many are paid.
 * - If the first term (Upon Registration / Prelim) is unpaid → RED (behind).
 * - If at least the first term is paid → GREEN (on track).
 * - Returns null when there are no payment terms yet.
 */
const getBalanceTimingStatus = (student: Student): 'red' | 'green' | 'zero' | null => {
    const balance = getRemainingBalance(student);

    if (balance === 0) return 'zero';

    const terms = student.latestAssessment?.paymentTerms;
    if (!terms || terms.length === 0) return null;

    const sorted = [...terms].sort((a, b) => a.term_order - b.term_order);
    const firstTerm = sorted[0];

    // If the very first term has not been paid at all → behind schedule
    if (firstTerm.status === 'pending' || parseFloat(String(firstTerm.balance)) >= parseFloat(String(firstTerm.amount))) {
        return 'red';
    }

    // First term is at least partially paid → on schedule
    return 'green';
};

const getBalanceClasses = (student: Student): string => {
    const timing = getBalanceTimingStatus(student);
    switch (timing) {
        case 'red':
            return 'text-red-600 font-bold';
        case 'green':
            return 'text-green-600 font-bold';
        case 'zero':
            return 'text-green-600 font-semibold';
        default:
            return 'text-gray-900 font-medium';
    }
};

const getBalanceIcon = (student: Student) => {
    const timing = getBalanceTimingStatus(student);
    if (timing === 'red') return TrendingDown;
    if (timing === 'green') return TrendingUp;
    return null;
};

const getBalanceBadge = (student: Student): { label: string; cls: string } | null => {
    const timing = getBalanceTimingStatus(student);
    if (timing === 'red') return { label: 'Behind', cls: 'bg-red-100 text-red-700 border border-red-200' };
    if (timing === 'green') return { label: 'On Track', cls: 'bg-green-100 text-green-700 border border-green-200' };
    if (timing === 'zero') return { label: 'Fully Paid', cls: 'bg-blue-100 text-blue-700 border border-blue-200' };
    return null;
};

// Summary stats
const totalStudents = computed(() => props.students.data.length);
const totalOutstanding = computed(() => props.students.data.reduce((sum, s) => sum + getRemainingBalance(s), 0));
const fullyPaidCount = computed(() => props.students.data.filter((s) => getRemainingBalance(s) === 0).length);
const behindCount = computed(() => props.students.data.filter((s) => getBalanceTimingStatus(s) === 'red').length);
</script>

<template>
    <Head title="Student Fee Management" />

    <AppLayout>
        <div class="space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Student Fee Management</h1>
                    <p class="mt-1 text-gray-600">Manage student assessments and fees</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('student-fees.create-student')">
                        <Button variant="outline" class="flex items-center gap-2">
                            <UserPlus class="h-4 w-4" />
                            Add Student
                        </Button>
                    </Link>
                    <Link :href="route('student-fees.create')">
                        <Button class="flex items-center gap-2">
                            <Plus class="h-4 w-4" />
                            Create Assessment
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-xl border bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Shown Students</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ totalStudents }}</p>
                </div>
                <div class="rounded-xl border bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Total Outstanding</p>
                    <p class="mt-1 text-2xl font-bold text-red-600">{{ formatCurrency(totalOutstanding) }}</p>
                </div>
                <div class="rounded-xl border bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Fully Paid</p>
                    <p class="mt-1 text-2xl font-bold text-green-600">{{ fullyPaidCount }}</p>
                </div>
                <div class="rounded-xl border bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Behind Schedule</p>
                    <p class="mt-1 text-2xl font-bold text-red-500">{{ behindCount }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="rounded-xl border bg-white p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div class="relative">
                        <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <Input v-model="search" placeholder="Search by ID or name..." class="pl-10" />
                    </div>
                    <select
                        v-model="selectedCourse"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-transparent focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All Courses</option>
                        <option v-for="course in courses" :key="course" :value="course">{{ course }}</option>
                    </select>
                    <select
                        v-model="selectedYearLevel"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-transparent focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All Year Levels</option>
                        <option v-for="year in yearLevels" :key="year" :value="year">{{ year }}</option>
                    </select>
                    <select
                        v-model="selectedStatus"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-transparent focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All Statuses</option>
                        <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Student ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Course</th>
                                <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Year Level</th>
                                <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase">Remaining Balance</th>
                                <th class="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-if="students.data.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                                    <Search class="mx-auto mb-2 h-8 w-8 opacity-40" />
                                    <p class="font-medium">No students found</p>
                                </td>
                            </tr>
                            <tr v-for="student in students.data" :key="student.id" class="transition-colors hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-900">
                                    {{ student.student_id }}
                                </td>
                                <td class="px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-900">
                                    {{ student.name }}
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

                                <!-- Remaining Balance with timing indicator -->
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <div class="flex flex-col items-end gap-1">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <component
                                                v-if="getBalanceIcon(student)"
                                                :is="getBalanceIcon(student)"
                                                class="h-3.5 w-3.5"
                                                :class="getBalanceTimingStatus(student) === 'red' ? 'text-red-500' : 'text-green-500'"
                                            />
                                            <span class="text-sm" :class="getBalanceClasses(student)">
                                                {{ formatCurrency(getRemainingBalance(student)) }}
                                            </span>
                                        </div>
                                        <span
                                            v-if="getBalanceBadge(student)"
                                            class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                                            :class="getBalanceBadge(student)!.cls"
                                        >
                                            {{ getBalanceBadge(student)!.label }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link :href="route('student-fees.show', student.id)">
                                            <button
                                                class="rounded-lg p-1.5 text-blue-600 transition-colors hover:bg-blue-50 hover:text-blue-900"
                                                title="View Details"
                                            >
                                                <Eye class="h-4 w-4" />
                                            </button>
                                        </Link>
                                        <Link :href="route('student-fees.edit', student.id)">
                                            <button
                                                class="rounded-lg p-1.5 text-green-600 transition-colors hover:bg-green-50 hover:text-green-900"
                                                title="Edit Assessment"
                                            >
                                                <Edit class="h-4 w-4" />
                                            </button>
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="students.last_page > 1" class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-6 py-4">
                    <div class="text-sm text-gray-600">Page {{ students.current_page }} of {{ students.last_page }}</div>
                    <div class="flex gap-2">
                        <template v-for="(link, index) in students.links" :key="index">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                :class="[
                                    'rounded border px-3 py-1 text-sm transition-colors',
                                    link.active
                                        ? 'border-blue-600 bg-blue-600 text-white'
                                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50',
                                ]"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="cursor-not-allowed rounded border border-gray-300 bg-gray-100 px-3 py-1 text-sm text-gray-400 opacity-60"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div class="flex items-center gap-6 px-1 text-xs text-gray-500">
                <div class="flex items-center gap-1.5">
                    <span class="inline-block h-2 w-2 rounded-full bg-red-500"></span>
                    <span><strong class="text-red-600">Behind</strong> — 1st term unpaid</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
                    <span><strong class="text-green-600">On Track</strong> — paying on schedule</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="inline-block h-2 w-2 rounded-full bg-blue-500"></span>
                    <span><strong class="text-blue-600">Fully Paid</strong> — no outstanding balance</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
