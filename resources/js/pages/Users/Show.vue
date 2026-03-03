<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl p-6">
            <!-- Breadcrumbs -->
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Page Heading -->
            <h1 class="mb-6 text-2xl font-semibold text-gray-800">User Details</h1>

            <!-- User Card -->
            <div class="space-y-4 rounded-xl bg-white p-6 shadow-md">
                <div>
                    <p class="text-sm text-gray-500">Name</p>
                    <p class="text-lg font-medium text-gray-800">{{ user.name }}</p>
                </div>

                <div>
                    <p class="text-sm text-gray-500">Email</p>
                    <p class="text-lg font-medium text-gray-800">{{ user.email }}</p>
                </div>

                <div>
                    <p class="text-sm text-gray-500">Role</p>
                    <span
                        class="inline-flex rounded-full px-3 py-1 text-sm font-medium"
                        :class="{
                            'bg-blue-100 text-blue-800': user.role === 'admin',
                            'bg-green-100 text-green-800': user.role === 'student',
                            'bg-purple-100 text-purple-800': user.role === 'accounting',
                            'bg-gray-100 text-gray-800': user.role === 'super_admin',
                        }"
                    >
                        {{ formatRole(user.role) }}
                    </span>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-end gap-3">
                <Link :href="route('users.edit', user.id)" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"> Edit </Link>
                <Link :href="route('users.index')" class="rounded-lg border px-4 py-2 text-gray-600 hover:bg-gray-50"> Back to Users </Link>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const { user } = defineProps<{
    user: any;
}>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Users', href: route('users.index') },
    { title: user.name, href: '#' },
];

// Format role for display
function formatRole(role: string) {
    return role.charAt(0).toUpperCase() + role.slice(1).replace('_', ' ');
}
</script>
