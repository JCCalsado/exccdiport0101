<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminForm from './Form.vue';

const breadcrumbs = [
    { title: 'Admin', href: route('admin.dashboard') },
    { title: 'Admin Users', href: route('users.index') },
    { title: 'Create New Staff', href: route('users.create') },
];

const selectedDepartment = ref<'Administrator' | 'Accounting' | null>(null);

const adminTypes = [
    {
        id: 'Administrator',
        title: 'Administrator',
        description: 'Full system administrator with all permissions',
        icon: '👤',
    },
    {
        id: 'Accounting',
        title: 'Accounting Staff',
        description: 'Accounting department user with financial permissions',
        icon: '💰',
    },
];

const selectDepartment = (dept: 'Administrator' | 'Accounting') => {
    selectedDepartment.value = dept;
};
</script>

<template>
    <Head title="Create Admin User" />

    <AppLayout>
        <div class="w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <div v-if="!selectedDepartment" class="max-w-4xl">
                <div class="mb-6">
                    <h1 class="mb-2 text-2xl font-bold text-gray-900">Create New Staff</h1>
                    <p class="text-gray-600">Select the type of staff member you want to create</p>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div
                        v-for="type in adminTypes"
                        :key="type.id"
                        @click="selectDepartment(type.id as 'Administrator' | 'Accounting')"
                        class="cursor-pointer rounded-lg border-2 border-gray-200 bg-white p-6 transition-all hover:border-blue-500 hover:shadow-lg"
                    >
                        <div class="mb-4 text-4xl">{{ type.icon }}</div>
                        <h3 class="mb-2 text-lg font-bold text-gray-900">{{ type.title }}</h3>
                        <p class="text-sm text-gray-600">{{ type.description }}</p>
                    </div>
                </div>
            </div>

            <div v-else class="max-w-2xl">
                <div class="mb-4 flex items-center">
                    <button @click="selectedDepartment = null" class="mr-4 text-blue-600 hover:text-blue-800">← Back to Selection</button>
                </div>

                <div class="overflow-hidden rounded-lg bg-white p-6 shadow-md">
                    <h1 class="mb-6 text-2xl font-bold text-gray-900">
                        Create {{ selectedDepartment === 'Administrator' ? 'Administrator' : 'Accounting' }} User
                    </h1>

                    <AdminForm :is-editing="false" :department="selectedDepartment" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
