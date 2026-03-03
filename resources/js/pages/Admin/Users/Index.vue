<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

interface Props {
    admins: any;
    stats?: any;
}

defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: route('admin.dashboard') },
    { title: 'Users', href: route('users.index') },
];

const statusBadgeClass = (status: boolean) => {
    return status ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
};

const adminTypeBadgeClass = (type: string) => {
    const classes: Record<string, string> = {
        super: 'bg-purple-100 text-purple-800',
        manager: 'bg-blue-100 text-blue-800',
        operator: 'bg-gray-100 text-gray-800',
    };
    return classes[type] || 'bg-gray-100 text-gray-800';
};

const getAdminTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
        super: 'Super Admin',
        manager: 'Manager',
        operator: 'Operator',
    };
    return labels[type] || type;
};
</script>

<template>
    <Head title="Admin Users" />

    <AppLayout>
        <div class="w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Admin Users</h1>
                    <p class="mt-2 text-gray-600">Manage administrator accounts and permissions</p>
                </div>
                <Link :href="route('users.create')">
                    <Button>+ Create Admin</Button>
                </Link>
            </div>

            <!-- Statistics -->
            <div v-if="stats" class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="rounded-lg bg-white p-6 shadow">
                    <div class="text-sm font-medium text-gray-600">Total Active Admins</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900">{{ stats.total_active_admins }}</div>
                </div>
                <div class="rounded-lg bg-white p-6 shadow">
                    <div class="text-sm font-medium text-gray-600">Super Admins</div>
                    <div class="mt-2 text-3xl font-bold text-purple-600">{{ stats.super_admins }}</div>
                </div>
                <div class="rounded-lg bg-white p-6 shadow">
                    <div class="text-sm font-medium text-gray-600">Managers</div>
                    <div class="mt-2 text-3xl font-bold text-blue-600">{{ stats.managers }}</div>
                </div>
                <div class="rounded-lg bg-white p-6 shadow">
                    <div class="text-sm font-medium text-gray-600">Operators</div>
                    <div class="mt-2 text-3xl font-bold text-gray-600">{{ stats.operators }}</div>
                </div>
            </div>

            <!-- Admins Table -->
            <div class="overflow-hidden rounded-lg bg-white shadow-md">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Terms</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="admin in admins.data" :key="admin.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">{{ admin.last_name }}, {{ admin.first_name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-gray-600">{{ admin.email }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="['rounded-full px-3 py-1 text-xs font-medium', adminTypeBadgeClass(admin.admin_type)]">
                                        {{ getAdminTypeLabel(admin.admin_type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-gray-600">{{ admin.department || '—' }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="['rounded-full px-3 py-1 text-xs font-medium', statusBadgeClass(admin.is_active)]">
                                        {{ admin.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span v-if="admin.terms_accepted_at" class="text-sm text-green-600">✓ Accepted</span>
                                    <span v-else class="text-sm text-red-600">✗ Pending</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <Link :href="route('users.show', admin.id)">
                                        <Button variant="ghost" size="sm">View</Button>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="admins.links" class="border-t bg-gray-50 px-6 py-4">
                    <div class="flex justify-center space-x-2">
                        <Link
                            v-for="link in admins.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            :class="['rounded px-3 py-2', link.active ? 'bg-blue-600 text-white' : 'border bg-white text-gray-700']"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
