<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

type Subject = {
    id: number;
    code: string;
    name: string;
    units: number;
    price_per_unit: number;
    year_level: string;
    semester: string;
    course: string;
    has_lab: boolean;
    lab_fee: number;
    is_active: boolean;
    total_cost: number;
};

const props = defineProps<{
    subjects: {
        data: Subject[];
        links?: any[];
        meta?: any;
    };
    filters: {
        search?: string;
        year_level?: string;
        semester?: string;
        course?: string;
    };
    yearLevels: string[];
    semesters: string[];
    courses: string[];
}>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Subjects', href: route('subjects.index') },
];

const searchForm = useForm({
    search: props.filters.search || '',
    year_level: props.filters.year_level || '',
    semester: props.filters.semester || '',
    course: props.filters.course || '',
});

const search = () => {
    searchForm.get(route('subjects.index'), {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    searchForm.reset();
    search();
};

const deleteSubject = (subjectId: number) => {
    if (confirm('Are you sure you want to delete this subject?')) {
        router.delete(route('subjects.destroy', subjectId), {
            preserveScroll: true,
        });
    }
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};
</script>

<template>
    <AppLayout>
        <Head title="Subject Management" />

        <div class="w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-3xl font-bold">Subject Management</h1>
                <Link :href="route('subjects.create')" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                    Create New Subject
                </Link>
            </div>

            <!-- Filters -->
            <div class="mb-6 rounded-lg bg-white p-6 shadow-md">
                <h2 class="mb-4 text-lg font-semibold">Filters</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Search</label>
                        <input
                            v-model="searchForm.search"
                            type="text"
                            placeholder="Code, name, course..."
                            class="w-full rounded border px-3 py-2"
                            @keyup.enter="search"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Year Level</label>
                        <select v-model="searchForm.year_level" class="w-full rounded border px-3 py-2" @change="search">
                            <option value="">All</option>
                            <option v-for="level in yearLevels" :key="level" :value="level">
                                {{ level }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Semester</label>
                        <select v-model="searchForm.semester" class="w-full rounded border px-3 py-2" @change="search">
                            <option value="">All</option>
                            <option v-for="sem in semesters" :key="sem" :value="sem">
                                {{ sem }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Course</label>
                        <select v-model="searchForm.course" class="w-full rounded border px-3 py-2" @change="search">
                            <option value="">All</option>
                            <option v-for="course in courses" :key="course" :value="course">
                                {{ course }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <button @click="search" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Search</button>
                    <button @click="clearFilters" class="rounded bg-gray-500 px-4 py-2 text-white hover:bg-gray-600">Clear Filters</button>
                </div>
            </div>

            <!-- Subjects Table -->
            <div class="overflow-hidden rounded-lg bg-white shadow-md">
                <div v-if="subjects.data && subjects.data.length > 0">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Units</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price/Unit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lab Fee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Cost</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="subject in subjects.data" :key="subject.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-medium">{{ subject.code }}</td>
                                <td class="px-6 py-4 text-sm">
                                    {{ subject.name }}
                                    <br />
                                    <span class="text-xs text-gray-500"> {{ subject.year_level }} - {{ subject.semester }} </span>
                                </td>
                                <td class="px-6 py-4 text-sm">{{ subject.units }}</td>
                                <td class="px-6 py-4 text-sm">{{ formatCurrency(subject.price_per_unit) }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span v-if="subject.has_lab">{{ formatCurrency(subject.lab_fee) }}</span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold text-blue-600">
                                    {{ formatCurrency(subject.total_cost) }}
                                </td>
                                <td class="px-6 py-4 text-sm">{{ subject.course }}</td>
                                <td class="space-x-2 px-6 py-4 text-right text-sm">
                                    <Link :href="route('subjects.edit', subject.id)" class="text-green-600 hover:text-green-900"> Edit </Link>
                                    <button @click="deleteSubject(subject.id)" class="text-red-600 hover:text-red-900">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div v-if="subjects.meta" class="border-t px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                <span v-if="subjects.meta.from && subjects.meta.to && subjects.meta.total">
                                    Showing {{ subjects.meta.from }} to {{ subjects.meta.to }} of {{ subjects.meta.total }} results
                                </span>
                                <span v-else> Showing {{ subjects.data.length }} result(s) </span>
                            </div>
                            <div v-if="subjects.links && subjects.links.length > 3" class="flex gap-2">
                                <Link
                                    v-for="(link, index) in subjects.links"
                                    :key="index"
                                    :href="link.url || '#'"
                                    v-html="link.label"
                                    :class="[
                                        'rounded border px-3 py-1',
                                        link.active ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-50',
                                        !link.url ? 'cursor-not-allowed opacity-50' : '',
                                    ]"
                                    :disabled="!link.url"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No Data State -->
                <div v-else class="py-12 text-center">
                    <p class="mb-4 text-lg text-gray-500">No subjects found</p>
                    <Link :href="route('subjects.create')" class="inline-block rounded-lg bg-blue-600 px-6 py-2 text-white hover:bg-blue-700">
                        Create Your First Subject
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
