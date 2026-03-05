<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    student_id: '',
    name: '',
    email: '',
    course: '',
    year_level: '',
    birthday: '',
    phone: '',
    address: '',
    total_balance: '',
});

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Students', href: route('students.index') },
    { title: 'Create', href: '#' },
];

function submit() {
    form.post(route('students.store'));
}
</script>

<template>
    <Head title="Add New Student" />

    <AppLayout>
        <div class="mx-auto max-w-2xl p-6">
            <Breadcrumbs :items="breadcrumbs" />
            <h1 class="mb-6 text-2xl font-semibold text-gray-800">Add New Student</h1>

            <form @submit.prevent="submit" class="space-y-4 rounded-xl bg-white p-6 shadow-md">
                <!-- Account ID -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Account ID *</label>
                    <input
                        v-model="form.student_id"
                        required
                        class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                        placeholder="2024-0001"
                    />
                </div>

                <!-- Name -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Full Name *</label>
                    <input
                        v-model="form.name"
                        required
                        class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                        placeholder="Juan Dela Cruz"
                    />
                </div>

                <!-- Email -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Email *</label>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                        placeholder="student@ccdi.edu.ph"
                    />
                </div>

                <!-- Course & Year Level -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Course *</label>
                        <input
                            v-model="form.course"
                            required
                            class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                            placeholder="BS Information Technology"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Year Level *</label>
                        <input
                            v-model="form.year_level"
                            required
                            class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                            placeholder="1st Year"
                        />
                    </div>
                </div>

                <!-- Total Balance -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Total Balance (₱) *</label>
                    <input
                        v-model="form.total_balance"
                        type="number"
                        step="0.01"
                        required
                        class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                        placeholder="25000.00"
                    />
                </div>

                <!-- Birthday -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Birthday</label>
                    <input v-model="form.birthday" type="date" class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500" />
                </div>

                <!-- Phone -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Phone Number</label>
                    <input
                        v-model="form.phone"
                        class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                        placeholder="+63 912 345 6789"
                    />
                </div>

                <!-- Address -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Address</label>
                    <textarea
                        v-model="form.address"
                        rows="3"
                        class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                        placeholder="Complete address"
                    ></textarea>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" @click="$router.back()" class="rounded-lg border px-4 py-2 text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        Create Student
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
