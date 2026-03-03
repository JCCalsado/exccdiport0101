<template>
    <AppLayout>
        <div class="w-full p-6">
            <!-- Header -->
            <Breadcrumbs :items="breadcrumbs" />

            <div class="p-6">
                <h1 class="mb-6 text-2xl font-bold">User Management</h1>

                <div class="mb-6 flex items-center justify-between">
                    <p class="text-gray-600">{{ message }}</p>
                    <Link href="/users/create" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"> ➕ Add User </Link>
                </div>

                <!-- Users Table -->
                <div class="overflow-hidden rounded-lg bg-white shadow-md">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ user.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ user.email }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 capitalize">{{ user.role?.replace('_', ' ') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ new Date(user.created_at).toLocaleDateString() }}</td>
                                <td class="flex gap-2 px-6 py-4 text-sm">
                                    <!-- View button -->
                                    <Link
                                        :href="`/users/${user.id}`"
                                        as="button"
                                        class="rounded-lg bg-blue-500 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-blue-600"
                                    >
                                        View
                                    </Link>

                                    <!-- Edit button -->
                                    <Link
                                        :href="`/users/${user.id}/edit`"
                                        as="button"
                                        class="rounded-lg bg-green-500 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-green-600"
                                    >
                                        Edit
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td colspan="5" class="px-6 py-6 text-center text-gray-500">No users found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-6 flex justify-center space-x-2">
                    <Link
                        v-for="link in users.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="rounded-lg border px-4 py-2 text-sm transition-colors"
                        :class="{
                            'border-blue-600 bg-blue-600 text-white': link.active,
                            'text-gray-600 hover:bg-gray-100': !link.active,
                            'cursor-not-allowed opacity-50': !link.url,
                        }"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    users: any;
    userRoles: any[];
    message: string;
}>();
const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Users', href: route('users.index') },
];
</script>
