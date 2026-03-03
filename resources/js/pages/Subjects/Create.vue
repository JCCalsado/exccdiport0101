<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    yearLevels: string[];
    semesters: string[];
    courses: string[];
}>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Subjects', href: route('subjects.index') },
    { title: 'Create Subject' },
];

const form = useForm({
    code: '',
    name: '',
    units: 3,
    price_per_unit: 350,
    year_level: '',
    semester: '',
    course: '',
    description: '',
    has_lab: false,
    lab_fee: 0,
    is_active: true,
});

const submit = () => {
    form.post(route('subjects.store'));
};
</script>

<template>
    <AppLayout>
        <Head title="Create Subject" />

        <div class="w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <div class="mx-auto max-w-3xl">
                <h1 class="mb-6 text-3xl font-bold">Create New Subject</h1>

                <form @submit.prevent="submit" class="space-y-6 rounded-lg bg-white p-6 shadow-md">
                    <!-- Subject Code -->
                    <div>
                        <label class="mb-2 block text-sm font-medium">Subject Code *</label>
                        <input v-model="form.code" type="text" class="w-full rounded border px-4 py-2" placeholder="e.g., CS101" required />
                        <div v-if="form.errors.code" class="mt-1 text-sm text-red-500">
                            {{ form.errors.code }}
                        </div>
                    </div>

                    <!-- Subject Name -->
                    <div>
                        <label class="mb-2 block text-sm font-medium">Subject Name *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full rounded border px-4 py-2"
                            placeholder="e.g., Introduction to Programming"
                            required
                        />
                        <div v-if="form.errors.name" class="mt-1 text-sm text-red-500">
                            {{ form.errors.name }}
                        </div>
                    </div>

                    <!-- Units and Price -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium">Units *</label>
                            <input v-model.number="form.units" type="number" min="1" max="10" class="w-full rounded border px-4 py-2" required />
                            <div v-if="form.errors.units" class="mt-1 text-sm text-red-500">
                                {{ form.errors.units }}
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium">Price per Unit *</label>
                            <input
                                v-model.number="form.price_per_unit"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full rounded border px-4 py-2"
                                required
                            />
                            <div v-if="form.errors.price_per_unit" class="mt-1 text-sm text-red-500">
                                {{ form.errors.price_per_unit }}
                            </div>
                        </div>
                    </div>

                    <!-- Year Level and Semester -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium">Year Level *</label>
                            <select v-model="form.year_level" class="w-full rounded border px-4 py-2" required>
                                <option value="">Select Year Level</option>
                                <option v-for="level in yearLevels" :key="level" :value="level">
                                    {{ level }}
                                </option>
                            </select>
                            <div v-if="form.errors.year_level" class="mt-1 text-sm text-red-500">
                                {{ form.errors.year_level }}
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium">Semester *</label>
                            <select v-model="form.semester" class="w-full rounded border px-4 py-2" required>
                                <option value="">Select Semester</option>
                                <option v-for="sem in semesters" :key="sem" :value="sem">
                                    {{ sem }}
                                </option>
                            </select>
                            <div v-if="form.errors.semester" class="mt-1 text-sm text-red-500">
                                {{ form.errors.semester }}
                            </div>
                        </div>
                    </div>

                    <!-- Course -->
                    <div>
                        <label class="mb-2 block text-sm font-medium">Course *</label>
                        <select v-model="form.course" class="w-full rounded border px-4 py-2" required>
                            <option value="">Select Course</option>
                            <option v-for="course in courses" :key="course" :value="course">
                                {{ course }}
                            </option>
                        </select>
                        <div v-if="form.errors.course" class="mt-1 text-sm text-red-500">
                            {{ form.errors.course }}
                        </div>
                    </div>

                    <!-- Has Lab -->
                    <div class="flex items-center">
                        <input v-model="form.has_lab" type="checkbox" id="has_lab" class="mr-2" />
                        <label for="has_lab" class="text-sm font-medium">Has Laboratory Component</label>
                    </div>

                    <!-- Lab Fee -->
                    <div v-if="form.has_lab">
                        <label class="mb-2 block text-sm font-medium">Laboratory Fee</label>
                        <input
                            v-model.number="form.lab_fee"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-full rounded border px-4 py-2"
                            placeholder="0.00"
                        />
                        <div v-if="form.errors.lab_fee" class="mt-1 text-sm text-red-500">
                            {{ form.errors.lab_fee }}
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="mb-2 block text-sm font-medium">Description</label>
                        <textarea
                            v-model="form.description"
                            class="w-full rounded border px-4 py-2"
                            rows="3"
                            placeholder="Optional description..."
                        ></textarea>
                    </div>

                    <!-- Is Active -->
                    <div class="flex items-center">
                        <input v-model="form.is_active" type="checkbox" id="is_active" class="mr-2" />
                        <label for="is_active" class="text-sm font-medium">Active</label>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between border-t pt-4">
                        <Link :href="route('subjects.index')" class="rounded bg-gray-500 px-4 py-2 text-white hover:bg-gray-600"> Cancel </Link>
                        <button type="submit" class="rounded bg-blue-600 px-6 py-2 text-white hover:bg-blue-700" :disabled="form.processing">
                            {{ form.processing ? 'Creating...' : 'Create Subject' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
