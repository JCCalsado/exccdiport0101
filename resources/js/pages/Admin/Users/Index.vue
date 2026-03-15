<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

interface Admin {
    id: number;
    last_name: string;
    first_name: string;
    middle_initial: string | null;
    email: string;
    admin_type: string;
    department: string | null;
    is_active: boolean;
    terms_accepted_at: string | null;
    last_login_at: string | null;
    created_at: string;
}

interface Props {
    admins: {
        data: Admin[];
        links: any[];
    };
    stats?: {
        total_admins: number;
        total_active_admins: number;
        super_admins: number;
        managers: number;
        operators: number;
    };
    canManage: boolean;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: route('admin.dashboard') },
    { title: 'Admin Users', href: route('users.index') },
];

const typeBadge = (type: string) => {
    const map: Record<string, string> = {
        super:    'bg-purple-100 text-purple-800',
        manager:  'bg-blue-100 text-blue-800',
        operator: 'bg-gray-100 text-gray-700',
    };
    return map[type] ?? 'bg-gray-100 text-gray-700';
};

const typeLabel = (type: string) => {
    const map: Record<string, string> = {
        super: 'Super Admin', manager: 'Manager', operator: 'Operator',
    };
    return map[type] ?? type;
};

const deactivate = (id: number) => {
    if (confirm('Deactivate this admin?')) {
        router.post(route('admin.users.deactivate', id));
    }
};

const reactivate = (id: number) => {
    router.post(route('admin.users.reactivate', id));
};
</script>

<template>
    <Head title="Admin Users" />
    <AppLayout>
        <div class="w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Admin Users</h1>
                    <p class="mt-1 text-gray-500 text-sm">Manage administrator accounts and permissions</p>
                </div>
                <Link v-if="canManage" :href="route('users.create')">
                    <Button>+ Create Admin</Button>
                </Link>
            </div>

            <!-- Stats -->
            <div v-if="stats" class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-lg bg-white p-5 shadow-sm border border-gray-100">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Active</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ stats.total_active_admins }}</p>
                    <p class="mt-0.5 text-xs text-gray-400">of {{ stats.total_admins }} total</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm border border-gray-100">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Super Admins</p>
                    <p class="mt-1 text-3xl font-bold text-purple-600">{{ stats.super_admins }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm border border-gray-100">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Managers</p>
                    <p class="mt-1 text-3xl font-bold text-blue-600">{{ stats.managers }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm border border-gray-100">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Operators</p>
                    <p class="mt-1 text-3xl font-bold text-gray-600">{{ stats.operators }}</p>
                </div>
            </div>

            <!-- Read-only notice -->
            <div v-if="!canManage" class="mb-4 flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                You have view-only access. Only Super Admins can create, edit, or deactivate accounts.
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-lg bg-white shadow-sm border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-600">Name</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-600">Email</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-600">Type</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-600">Department</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-600">Status</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-600">Terms</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="admin in admins.data" :key="admin.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-4">
                                    <span class="font-medium text-gray-900">{{ admin.last_name }}, {{ admin.first_name }}</span>
                                    <span v-if="admin.middle_initial" class="text-gray-400"> {{ admin.middle_initial }}.</span>
                                </td>
                                <td class="px-5 py-4 text-gray-600">{{ admin.email }}</td>
                                <td class="px-5 py-4">
                                    <span :class="['rounded-full px-2.5 py-1 text-xs font-medium', typeBadge(admin.admin_type)]">
                                        {{ typeLabel(admin.admin_type) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-gray-500">{{ admin.department || '—' }}</td>
                                <td class="px-5 py-4">
                                    <span :class="['rounded-full px-2.5 py-1 text-xs font-medium', admin.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700']">
                                        {{ admin.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span v-if="admin.terms_accepted_at" class="text-green-600 text-xs font-medium">✓ Accepted</span>
                                    <span v-else class="text-red-500 text-xs">✗ Pending</span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <Link :href="route('users.show', admin.id)">
                                            <Button variant="ghost" size="sm">View</Button>
                                        </Link>
                                        <template v-if="canManage">
                                            <Link :href="route('users.edit', admin.id)">
                                                <Button variant="outline" size="sm">Edit</Button>
                                            </Link>
                                            <Button
                                                v-if="admin.is_active"
                                                variant="destructive"
                                                size="sm"
                                                @click="deactivate(admin.id)"
                                            >Deactivate</Button>
                                            <Button
                                                v-else
                                                variant="outline"
                                                size="sm"
                                                @click="reactivate(admin.id)"
                                            >Reactivate</Button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="admins.data.length === 0">
                                <td colspan="7" class="px-5 py-10 text-center text-gray-400">No admin users found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="admins.links?.length > 3" class="border-t bg-gray-50 px-5 py-3 flex justify-center gap-1">
                    <Link
                        v-for="link in admins.links"
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