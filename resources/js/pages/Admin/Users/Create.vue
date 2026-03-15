<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import AdminForm from './Form.vue';

interface Props {
    adminTypes: Array<{ value: string; label: string }>;
}

defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: route('admin.dashboard') },
    { title: 'Admin Users', href: route('users.index') },
    { title: 'Create New Admin', href: route('users.create') },
];
</script>

<template>
    <Head title="Create Admin User" />

    <AppLayout>
        <div class="w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <div class="max-w-2xl">
                <div class="overflow-hidden rounded-lg bg-white p-6 shadow-md">
                    <h1 class="mb-6 text-2xl font-bold text-gray-900">Create New Admin User</h1>

                    <div class="mb-6 rounded-lg bg-blue-50 p-4 text-sm text-gray-600">
                        <strong>Note:</strong> New admin accounts require acceptance of Terms & Conditions before the account is fully activated.
                    </div>

                    <AdminForm :is-editing="false" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>