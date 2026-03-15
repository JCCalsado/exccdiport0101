<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface Student {
    id: number;
    student_id: string;
    student_number: string | null;
    first_name: string;
    last_name: string;
    middle_initial: string | null;
    email: string;
    course: string;
    year_level: string;
    enrollment_status: string;
    updated_at: string;
    account?: { balance: number } | null;
}

interface Props {
    students: { data: Student[]; links: any[] };
    filters: { search?: string; status?: string };
    counts: { graduated: number; dropped: number; inactive: number };
}

const props = defineProps<Props>();

const search     = ref(props.filters.search  || '');
const statusFilter = ref(props.filters.status || '');

let timeout: ReturnType<typeof setTimeout>;
watch([search, statusFilter], () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            route('students.archive'),
            { search: search.value, status: statusFilter.value },
            { preserveState: true, replace: true }
        );
    }, 300);
});

const breadcrumbs = [
    { title: 'Dashboard', href: route('admin.dashboard') },
    { title: 'Archives' },
];

const formatDate = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';

const formatCurrency = (amount: number) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);

const statusConfig: Record<string, { label: string; classes: string }> = {
    graduated: { label: 'Graduated', classes: 'bg-blue-100 text-blue-800' },
    dropped:   { label: 'Dropped',   classes: 'bg-red-100 text-red-800'  },
    inactive:  { label: 'Inactive',  classes: 'bg-gray-100 text-gray-700' },
};

const totalArchived = props.counts.graduated + props.counts.dropped + props.counts.inactive;
</script>

<template>
    <Head title="Student Archives" />
    <AppLayout>
        <div class="w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Student Archives</h1>
                <p class="mt-1 text-sm text-gray-500">Graduated, dropped, and inactive students</p>
            </div>

            <!-- Summary cards -->
            <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-lg bg-white p-5 shadow-sm border border-gray-100">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Archived</p>
                    <p class="mt-1 text-3xl font-bold text-gray-800">{{ totalArchived }}</p>
                </div>
                <div
                    class="rounded-lg p-5 shadow-sm border cursor-pointer transition-colors"
                    :class="statusFilter === 'graduated' ? 'bg-blue-600 border-blue-600' : 'bg-white border-gray-100 hover:border-blue-300'"
                    @click="statusFilter = statusFilter === 'graduated' ? '' : 'graduated'"
                >
                    <p :class="['text-xs font-medium uppercase tracking-wide', statusFilter === 'graduated' ? 'text-blue-100' : 'text-gray-500']">Graduated</p>
                    <p :class="['mt-1 text-3xl font-bold', statusFilter === 'graduated' ? 'text-white' : 'text-blue-700']">{{ counts.graduated }}</p>
                </div>
                <div
                    class="rounded-lg p-5 shadow-sm border cursor-pointer transition-colors"
                    :class="statusFilter === 'dropped' ? 'bg-red-600 border-red-600' : 'bg-white border-gray-100 hover:border-red-300'"
                    @click="statusFilter = statusFilter === 'dropped' ? '' : 'dropped'"
                >
                    <p :class="['text-xs font-medium uppercase tracking-wide', statusFilter === 'dropped' ? 'text-red-100' : 'text-gray-500']">Dropped</p>
                    <p :class="['mt-1 text-3xl font-bold', statusFilter === 'dropped' ? 'text-white' : 'text-red-700']">{{ counts.dropped }}</p>
                </div>
                <div
                    class="rounded-lg p-5 shadow-sm border cursor-pointer transition-colors"
                    :class="statusFilter === 'inactive' ? 'bg-gray-700 border-gray-700' : 'bg-white border-gray-100 hover:border-gray-400'"
                    @click="statusFilter = statusFilter === 'inactive' ? '' : 'inactive'"
                >
                    <p :class="['text-xs font-medium uppercase tracking-wide', statusFilter === 'inactive' ? 'text-gray-200' : 'text-gray-500']">Inactive</p>
                    <p :class="['mt-1 text-3xl font-bold', statusFilter === 'inactive' ? 'text-white' : 'text-gray-700']">{{ counts.inactive }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name, ID, email, course…"
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                />
                <select
                    v-model="statusFilter"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                >
                    <option value="">All archived statuses</option>
                    <option value="graduated">Graduated</option>
                    <option value="dropped">Dropped</option>
                    <option value="inactive">Inactive</option>
                </select>
                <button
                    v-if="search || statusFilter"
                    @click="search = ''; statusFilter = ''"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
                >
                    Clear
                </button>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-lg bg-white shadow-sm border border-gray-100">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium text-gray-600">Student ID</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-600">Name</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-600">Email</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-600">Course</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-600">Year Level</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-600">Status</th>
                            <th class="px-5 py-3 text-right font-medium text-gray-600">Balance</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-600">Last updated</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="student in students.data" :key="student.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-4 font-mono text-gray-700 text-xs">{{ student.student_id }}</td>
                            <td class="px-5 py-4 font-medium text-gray-900">
                                {{ student.last_name }}, {{ student.first_name }}
                                <span v-if="student.middle_initial" class="text-gray-400"> {{ student.middle_initial }}.</span>
                            </td>
                            <td class="px-5 py-4 text-gray-600">{{ student.email }}</td>
                            <td class="px-5 py-4 text-gray-700">{{ student.course }}</td>
                            <td class="px-5 py-4 text-gray-700">{{ student.year_level }}</td>
                            <td class="px-5 py-4">
                                <span
                                    v-if="statusConfig[student.enrollment_status]"
                                    :class="['rounded-full px-2.5 py-1 text-xs font-medium', statusConfig[student.enrollment_status].classes]"
                                >
                                    {{ statusConfig[student.enrollment_status].label }}
                                </span>
                                <span v-else class="text-gray-400 text-xs">{{ student.enrollment_status }}</span>
                            </td>
                            <td class="px-5 py-4 text-right text-gray-700">
                                {{ formatCurrency(Math.abs(student.account?.balance ?? 0)) }}
                            </td>
                            <td class="px-5 py-4 text-gray-500">{{ formatDate(student.updated_at) }}</td>
                            <td class="px-5 py-4">
                                <Link
                                    :href="route('students.show', student.id)"
                                    class="text-blue-600 hover:text-blue-800 font-medium"
                                >View</Link>
                            </td>
                        </tr>
                        <tr v-if="students.data.length === 0">
                            <td colspan="9" class="px-5 py-12 text-center text-gray-400">
                                <p class="font-medium">No archived students found.</p>
                                <p class="mt-1 text-xs">Try adjusting your search or filter.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="students.links?.length > 3" class="border-t bg-gray-50 px-5 py-3 flex justify-center gap-1">
                    <Link
                        v-for="link in students.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        :class="[
                            'rounded px-3 py-1.5 text-xs font-medium transition-colors',
                            link.active ? 'bg-blue-600 text-white' : 'border bg-white text-gray-600 hover:bg-gray-100',
                            !link.url ? 'pointer-events-none opacity-40' : '',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>