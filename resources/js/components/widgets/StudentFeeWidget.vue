<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertCircle, CreditCard, DollarSign, FileText, Receipt, TrendingUp, Users } from 'lucide-vue-next';

interface StudentFeeStats {
    total_assessments: number;
    total_assessment_amount: number;
    pending_assessments: number;
    recent_assessments: number;
    recent_payments_amount: number;
}

interface Props {
    stats: StudentFeeStats;
}

defineProps<Props>();

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};
</script>

<template>
    <div class="rounded-lg border-2 border-blue-200 bg-gradient-to-br from-blue-50 to-indigo-50 p-6 shadow-md">
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
            <!-- Total Assessments -->
            <div class="rounded-lg bg-white p-4 shadow transition-shadow hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="mb-1 text-sm text-gray-600">Total Assessments</p>
                        <p class="text-2xl font-bold text-gray-900">{{ stats.total_assessments }}</p>
                        <p class="mt-1 text-xs text-blue-600">Active enrollments</p>
                    </div>
                    <div class="rounded-lg bg-blue-100 p-2">
                        <Users :size="20" class="text-blue-600" />
                    </div>
                </div>
            </div>

            <!-- Total Assessment Amount -->
            <div class="rounded-lg bg-white p-4 shadow transition-shadow hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="mb-1 text-sm text-gray-600">Total Assessment</p>
                        <p class="text-xl font-bold text-indigo-600">
                            {{ formatCurrency(stats.total_assessment_amount) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">Current term</p>
                    </div>
                    <div class="rounded-lg bg-indigo-100 p-2">
                        <TrendingUp :size="20" class="text-indigo-600" />
                    </div>
                </div>
            </div>

            <!-- Pending Assessments -->
            <div class="rounded-lg bg-white p-4 shadow transition-shadow hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="mb-1 text-sm text-gray-600">Pending Balance</p>
                        <p class="text-xl font-bold text-red-600">
                            {{ formatCurrency(stats.pending_assessments) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">Outstanding</p>
                    </div>
                    <div class="rounded-lg bg-red-100 p-2">
                        <AlertCircle :size="20" class="text-red-600" />
                    </div>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="rounded-lg bg-white p-4 shadow transition-shadow hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="mb-1 text-sm text-gray-600">Recent Payments</p>
                        <p class="text-xl font-bold text-green-600">
                            {{ formatCurrency(stats.recent_payments_amount) }}
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
                        <p class="text-xs text-gray-600">{{ stats.pending_assessments > 0 ? 'Needs attention' : 'All clear' }}</p>
                    </div>
                </Link>
            </div>
        </div>
    </div>
</template>
