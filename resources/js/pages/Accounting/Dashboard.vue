<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowDownRight,
    ArrowUpRight,
    CheckCircle,
    Clock,
    CreditCard,
    DollarSign,
    FileText,
    Receipt,
    RefreshCw,
    TrendingUp,
    Users,
} from 'lucide-vue-next';
import { ref } from 'vue';

type Stats = {
    total_students: number;
    active_students: number;
    total_charges: number;
    total_payments: number;
    total_pending: number;
    collection_rate: number;
    active_fees: number;
    total_fee_amount: number;
};

type Student = {
    id: number;
    name: string;
    email: string;
    student_id: string;
    course: string;
    year_level: string;
    balance: number;
};

type Payment = {
    id: number;
    reference: string;
    student_name: string;
    amount: number;
    status: string;
    paid_at: string;
    created_at: string;
};

type PaymentTrend = {
    month: string;
    total: number;
    count: number;
};

type PaymentMethod = {
    method: string;
    count: number;
    total: number;
};

type YearLevel = {
    year_level: string;
    count: number;
};

type StudentFeeStats = {
    total_assessments: number;
    total_assessment_amount: number;
    pending_assessments: number;
    recent_assessments: number;
    recent_payments_amount: number;
};

const props = defineProps<{
    stats: Stats;
    studentsWithBalance: Student[];
    recentPayments: Payment[];
    paymentTrends: PaymentTrend[];
    paymentByMethod: PaymentMethod[];
    studentsByYearLevel: YearLevel[];
    currentTerm: {
        year: number;
        semester: string;
    };
    studentFeeStats?: StudentFeeStats;
}>();

const breadcrumbs = [{ title: 'Dashboard', href: route('dashboard') }, { title: 'Accounting Dashboard' }];

const activeTab = ref<'overview' | 'payments' | 'students'>('overview');

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const formatMonth = (month: string) => {
    const [year, monthNum] = month.split('-');
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${monthNames[parseInt(monthNum) - 1]} ${year}`;
};

const getCollectionRateColor = (rate: number) => {
    if (rate >= 80) return 'text-green-600';
    if (rate >= 60) return 'text-yellow-600';
    return 'text-red-600';
};

const getTrendIcon = (index: number) => {
    if (index === 0) return null;
    const current = props.paymentTrends[index]?.total || 0;
    const previous = props.paymentTrends[index - 1]?.total || 0;
    return current > previous ? ArrowUpRight : ArrowDownRight;
};

const getTrendColor = (index: number) => {
    if (index === 0) return '';
    const current = props.paymentTrends[index]?.total || 0;
    const previous = props.paymentTrends[index - 1]?.total || 0;
    return current > previous ? 'text-green-600' : 'text-red-600';
};

const refreshData = () => {
    router.reload({ only: ['stats', 'recentPayments', 'studentsWithBalance', 'studentFeeStats'] });
};

const viewStudent = (studentId: number) => {
    router.visit(route('students.show', studentId));
};
</script>

<template>
    <AppLayout>
        <Head title="Accounting Dashboard" />

        <div class="w-full space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Accounting Dashboard</h1>
                    <p class="mt-1 text-gray-600">{{ currentTerm.semester }} - {{ currentTerm.year }}-{{ currentTerm.year + 1 }}</p>
                </div>
                <div class="flex gap-2">
                    <button @click="refreshData" class="flex items-center gap-2 rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200">
                        <RefreshCw :size="16" />
                        Refresh
                    </button>
                    <!-- Manage Fees button removed (Fee Management disabled) -->
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                <!-- Total Students -->
                <div class="rounded-lg bg-white p-6 shadow-md transition-shadow hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="mb-1 text-sm text-gray-600">Total Students</p>
                            <p class="text-3xl font-bold text-gray-900">{{ stats.total_students }}</p>
                            <p class="mt-2 text-xs text-green-600">{{ stats.active_students }} active</p>
                        </div>
                        <div class="rounded-lg bg-blue-100 p-3">
                            <Users :size="24" class="text-blue-600" />
                        </div>
                    </div>
                </div>

                <!-- Total Collections -->
                <div class="rounded-lg bg-white p-6 shadow-md transition-shadow hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="mb-1 text-sm text-gray-600">Total Collections</p>
                            <p class="text-2xl font-bold text-green-600">
                                {{ formatCurrency(stats.total_payments) }}
                            </p>
                            <p class="mt-2 text-xs text-gray-500">All-time</p>
                        </div>
                        <div class="rounded-lg bg-green-100 p-3">
                            <CheckCircle :size="24" class="text-green-600" />
                        </div>
                    </div>
                </div>

                <!-- Pending Payments -->
                <div class="rounded-lg bg-white p-6 shadow-md transition-shadow hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="mb-1 text-sm text-gray-600">Pending Payments</p>
                            <p class="text-2xl font-bold text-red-600">
                                {{ formatCurrency(stats.total_pending) }}
                            </p>
                            <p class="mt-2 text-xs text-gray-500">Outstanding</p>
                        </div>
                        <div class="rounded-lg bg-red-100 p-3">
                            <Clock :size="24" class="text-red-600" />
                        </div>
                    </div>
                </div>

                <!-- Collection Rate -->
                <div class="rounded-lg bg-white p-6 shadow-md transition-shadow hover:shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="mb-1 text-sm text-gray-600">Collection Rate</p>
                            <p class="text-3xl font-bold" :class="getCollectionRateColor(stats.collection_rate)">{{ stats.collection_rate }}%</p>
                            <p class="mt-2 text-xs text-gray-500">Overall efficiency</p>
                        </div>
                        <div class="rounded-lg bg-purple-100 p-3">
                            <TrendingUp :size="24" class="text-purple-600" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Fee Management Widget -->
            <div v-if="studentFeeStats" class="rounded-lg border-2 border-blue-200 bg-gradient-to-br from-blue-50 to-indigo-50 p-6 shadow-md">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h2 class="flex items-center gap-2 text-xl font-bold text-gray-900">
                            <FileText :size="24" class="text-blue-600" />
                            Student Fee Management
                        </h2>
                        <p class="mt-1 text-sm text-gray-600">Assessment and fee tracking overview</p>
                    </div>
                    <Link
                        :href="route('student-fees.index')"
                        class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-white transition-colors hover:bg-blue-700"
                    >
                        <Receipt :size="16" />
                        Manage Assessments
                    </Link>
                </div>

                <!-- Student Fee Stats Grid -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg bg-white p-4 shadow transition-shadow hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <p class="mb-1 text-sm text-gray-600">Total Assessments</p>
                                <p class="text-2xl font-bold text-gray-900">{{ studentFeeStats.total_assessments }}</p>
                                <p class="mt-1 text-xs text-blue-600">Active enrollments</p>
                            </div>
                            <div class="rounded-lg bg-blue-100 p-2">
                                <Users :size="20" class="text-blue-600" />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow transition-shadow hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <p class="mb-1 text-sm text-gray-600">Total Assessment</p>
                                <p class="text-xl font-bold text-indigo-600">
                                    {{ formatCurrency(studentFeeStats.total_assessment_amount) }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500">Current term</p>
                            </div>
                            <div class="rounded-lg bg-indigo-100 p-2">
                                <TrendingUp :size="20" class="text-indigo-600" />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow transition-shadow hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <p class="mb-1 text-sm text-gray-600">Pending Balance</p>
                                <p class="text-xl font-bold text-red-600">
                                    {{ formatCurrency(studentFeeStats.pending_assessments) }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500">Outstanding</p>
                            </div>
                            <div class="rounded-lg bg-red-100 p-2">
                                <AlertCircle :size="20" class="text-red-600" />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow transition-shadow hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <p class="mb-1 text-sm text-gray-600">Recent Payments</p>
                                <p class="text-xl font-bold text-green-600">
                                    {{ formatCurrency(studentFeeStats.recent_payments_amount) }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500">Last 30 days</p>
                            </div>
                            <div class="rounded-lg bg-green-100 p-2">
                                <DollarSign :size="20" class="text-green-600" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions for Student Fees -->
                <div class="mt-4 border-t border-blue-200 pt-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <Link
                            :href="route('student-fees.create')"
                            class="flex items-center gap-2 rounded-lg border border-blue-200 bg-white p-3 transition-colors hover:bg-blue-50"
                        >
                            <div class="rounded bg-blue-500 p-2">
                                <FileText :size="16" class="text-white" />
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">Create Assessment</p>
                                <p class="text-xs text-gray-600">New student fee</p>
                            </div>
                        </Link>

                        <Link
                            :href="route('student-fees.index')"
                            class="flex items-center gap-2 rounded-lg border border-blue-200 bg-white p-3 transition-colors hover:bg-blue-50"
                        >
                            <div class="rounded bg-indigo-500 p-2">
                                <Users :size="16" class="text-white" />
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">View All Students</p>
                                <p class="text-xs text-gray-600">Manage fees</p>
                            </div>
                        </Link>

                        <Link
                            :href="route('student-fees.index', { filter: 'outstanding' })"
                            class="flex items-center gap-2 rounded-lg border border-blue-200 bg-white p-3 transition-colors hover:bg-blue-50"
                        >
                            <div class="rounded bg-red-500 p-2">
                                <CreditCard :size="16" class="text-white" />
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">Outstanding Balance</p>
                                <p class="text-xs text-gray-600">{{ studentFeeStats.pending_assessments > 0 ? 'Needs attention' : 'All clear' }}</p>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="rounded-lg bg-white shadow-md">
                <div class="border-b">
                    <nav class="flex gap-4 px-6">
                        <button
                            @click="activeTab = 'overview'"
                            :class="[
                                'border-b-2 px-2 py-4 text-sm font-medium transition-colors',
                                activeTab === 'overview' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700',
                            ]"
                        >
                            Overview
                        </button>
                        <button
                            @click="activeTab = 'payments'"
                            :class="[
                                'border-b-2 px-2 py-4 text-sm font-medium transition-colors',
                                activeTab === 'payments' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700',
                            ]"
                        >
                            Recent Payments
                        </button>
                        <button
                            @click="activeTab = 'students'"
                            :class="[
                                'border-b-2 px-2 py-4 text-sm font-medium transition-colors',
                                activeTab === 'students' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700',
                            ]"
                        >
                            Outstanding Balances
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="p-6">
                    <!-- Overview Tab -->
                    <div v-if="activeTab === 'overview'" class="space-y-6">
                        <div>
                            <h3 class="mb-4 text-lg font-semibold">Payment Trends (Last 6 Months)</h3>
                            <div class="rounded-lg bg-gray-50 p-4">
                                <div class="flex h-64 items-end justify-between gap-2">
                                    <div v-for="(trend, index) in paymentTrends" :key="trend.month" class="flex flex-1 flex-col items-center">
                                        <div class="mb-2 flex items-center gap-1">
                                            <component
                                                v-if="getTrendIcon(index)"
                                                :is="getTrendIcon(index)"
                                                :size="16"
                                                :class="getTrendColor(index)"
                                            />
                                        </div>
                                        <div
                                            class="group relative w-full cursor-pointer rounded-t bg-blue-500 transition-colors hover:bg-blue-600"
                                            :style="{
                                                height: `${(trend.total / Math.max(...paymentTrends.map((t) => t.total))) * 100}%`,
                                                minHeight: '20px',
                                            }"
                                        >
                                            <div
                                                class="absolute bottom-full left-1/2 mb-2 -translate-x-1/2 transform rounded bg-gray-900 px-3 py-2 text-xs whitespace-nowrap text-white opacity-0 transition-opacity group-hover:opacity-100"
                                            >
                                                {{ formatCurrency(trend.total) }}<br />
                                                {{ trend.count }} payments
                                            </div>
                                        </div>
                                        <p class="mt-2 text-xs text-gray-600">{{ formatMonth(trend.month) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <h3 class="mb-4 text-lg font-semibold">Payment Methods</h3>
                                <div class="space-y-3">
                                    <div v-for="method in paymentByMethod" :key="method.method" class="rounded-lg bg-gray-50 p-4">
                                        <div class="mb-2 flex items-center justify-between">
                                            <span class="font-medium">{{ method.method }}</span>
                                            <span class="text-sm text-gray-600">{{ method.count }} transactions</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 flex-1 rounded-full bg-gray-200">
                                                <div
                                                    class="h-2 rounded-full bg-blue-500"
                                                    :style="{
                                                        width: `${(method.total / paymentByMethod.reduce((sum, m) => sum + m.total, 0)) * 100}%`,
                                                    }"
                                                ></div>
                                            </div>
                                            <span class="text-sm font-semibold">{{ formatCurrency(method.total) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 class="mb-4 text-lg font-semibold">Students by Year Level</h3>
                                <div class="space-y-3">
                                    <div v-for="level in studentsByYearLevel" :key="level.year_level" class="rounded-lg bg-gray-50 p-4">
                                        <div class="mb-2 flex items-center justify-between">
                                            <span class="font-medium">{{ level.year_level }}</span>
                                            <span class="text-lg font-bold text-blue-600">{{ level.count }}</span>
                                        </div>
                                        <div class="h-2 flex-1 rounded-full bg-gray-200">
                                            <div
                                                class="h-2 rounded-full bg-green-500"
                                                :style="{
                                                    width: `${(level.count / stats.total_students) * 100}%`,
                                                }"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payments Tab -->
                    <div v-if="activeTab === 'payments'">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="text-lg font-semibold">Recent Payments</h3>
                            <Link :href="route('transactions.index')" class="text-sm text-blue-600 hover:text-blue-800"> View All → </Link>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="payment in recentPayments" :key="payment.id" class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-medium">{{ payment.reference }}</td>
                                        <td class="px-4 py-3 text-sm">{{ payment.student_name }}</td>
                                        <td class="px-4 py-3 text-sm font-semibold text-green-600">
                                            {{ formatCurrency(payment.amount) }}
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <span
                                                class="rounded-full px-2 py-1 text-xs"
                                                :class="payment.status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'"
                                            >
                                                {{ payment.status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(payment.created_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="!recentPayments.length" class="py-8 text-center text-gray-500">No recent payments found</div>
                    </div>

                    <!-- Students Tab -->
                    <div v-if="activeTab === 'students'">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="text-lg font-semibold">Students with Outstanding Balances</h3>
                            <Link :href="route('students.index')" class="text-sm text-blue-600 hover:text-blue-800"> View All Students → </Link>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="student in studentsWithBalance"
                                :key="student.id"
                                class="cursor-pointer rounded-lg bg-gray-50 p-4 transition-colors hover:bg-gray-100"
                                @click="viewStudent(student.id)"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-900">{{ student.name }}</p>
                                        <p class="text-sm text-gray-600">{{ student.student_id }} • {{ student.email }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ student.course }} - {{ student.year_level }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold text-red-600">{{ formatCurrency(student.balance) }}</p>
                                        <p class="text-xs text-gray-500">Outstanding</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="!studentsWithBalance.length" class="py-8 text-center text-gray-500">No students with outstanding balances</div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions — Fee/Subject links removed -->
            <div class="rounded-lg bg-white p-6 shadow-md">
                <h3 class="mb-4 text-lg font-semibold">Quick Actions</h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Link
                        :href="route('students.index')"
                        class="flex items-center gap-3 rounded-lg bg-purple-50 p-4 transition-colors hover:bg-purple-100"
                    >
                        <div class="rounded bg-purple-500 p-2">
                            <Users :size="20" class="text-white" />
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Manage Students</p>
                            <p class="text-xs text-gray-600">View all students</p>
                        </div>
                    </Link>

                    <Link
                        :href="route('transactions.index')"
                        class="flex items-center gap-3 rounded-lg bg-orange-50 p-4 transition-colors hover:bg-orange-100"
                    >
                        <div class="rounded bg-orange-500 p-2">
                            <DollarSign :size="20" class="text-white" />
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">View Transactions</p>
                            <p class="text-xs text-gray-600">All transactions</p>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
