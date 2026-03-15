<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

interface Props {
    admin: any;
    canManage: boolean;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: route('admin.dashboard') },
    { title: 'Admin Users', href: route('users.index') },
    { title: `${props.admin.last_name}, ${props.admin.first_name}`, href: route('users.show', props.admin.id) },
];

const typeLabel = (type: string) => {
    const map: Record<string, string> = { super: 'Super Admin', manager: 'Manager', operator: 'Operator' };
    return map[type] ?? type;
};

const typeBadge = (type: string) => {
    const map: Record<string, string> = {
        super: 'bg-purple-100 text-purple-800',
        manager: 'bg-blue-100 text-blue-800',
        operator: 'bg-gray-100 text-gray-700',
    };
    return map[type] ?? 'bg-gray-100 text-gray-700';
};

const formatDate = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';

const deactivate = () => {
    if (confirm('Deactivate this admin account?')) {
        router.post(route('admin.users.deactivate', props.admin.id));
    }
};

const reactivate = () => {
    router.post(route('admin.users.reactivate', props.admin.id));
};
</script>

<template>
    <Head :title="`Admin: ${admin.last_name}, ${admin.first_name}`" />
    <AppLayout>
        <div class="w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <div class="max-w-4xl space-y-5">
                <!-- Header card -->
                <div class="rounded-lg bg-white p-6 shadow-sm border border-gray-100">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">
                                {{ admin.last_name }}, {{ admin.first_name }}{{ admin.middle_initial ? ' ' + admin.middle_initial + '.' : '' }}
                            </h1>
                            <p class="mt-1 text-gray-500">{{ admin.email }}</p>
                            <div class="mt-3 flex items-center gap-2">
                                <span :class="['rounded-full px-2.5 py-1 text-xs font-medium', typeBadge(admin.admin_type)]">
                                    {{ typeLabel(admin.admin_type) }}
                                </span>
                                <span :class="['rounded-full px-2.5 py-1 text-xs font-medium', admin.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700']">
                                    {{ admin.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>

                        <div v-if="canManage" class="flex shrink-0 gap-2">
                            <Link :href="route('users.edit', admin.id)">
                                <Button>Edit</Button>
                            </Link>
                            <Button v-if="admin.is_active" variant="destructive" @click="deactivate">Deactivate</Button>
                            <Button v-else variant="outline" @click="reactivate">Reactivate</Button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <!-- Admin info -->
                    <div class="rounded-lg bg-white p-6 shadow-sm border border-gray-100">
                        <h2 class="mb-4 font-semibold text-gray-800">Admin information</h2>
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Type</dt>
                                <dd><span :class="['rounded-full px-2.5 py-1 text-xs font-medium', typeBadge(admin.admin_type)]">{{ typeLabel(admin.admin_type) }}</span></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Department</dt>
                                <dd class="text-gray-900">{{ admin.department || '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Status</dt>
                                <dd>
                                    <span :class="['rounded-full px-2.5 py-1 text-xs font-medium', admin.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700']">
                                        {{ admin.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Terms accepted</dt>
                                <dd>
                                    <span v-if="admin.terms_accepted_at" class="text-green-600 text-xs font-medium">✓ {{ formatDate(admin.terms_accepted_at) }}</span>
                                    <span v-else class="text-red-500 text-xs">✗ Not accepted</span>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Account details -->
                    <div class="rounded-lg bg-white p-6 shadow-sm border border-gray-100">
                        <h2 class="mb-4 font-semibold text-gray-800">Account details</h2>
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">User ID</dt>
                                <dd class="font-mono text-gray-700">{{ admin.id }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Created</dt>
                                <dd class="text-gray-900">{{ formatDate(admin.created_at) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Last updated</dt>
                                <dd class="text-gray-900">{{ formatDate(admin.updated_at) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Last login</dt>
                                <dd class="text-gray-900">{{ formatDate(admin.last_login_at) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Audit trail -->
                <div class="rounded-lg bg-white p-6 shadow-sm border border-gray-100">
                    <h2 class="mb-4 font-semibold text-gray-800">Audit trail</h2>
                    <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-gray-500">Created by</dt>
                            <dd class="mt-1 text-gray-900">
                                <span v-if="admin.createdByUser">{{ admin.createdByUser.last_name }}, {{ admin.createdByUser.first_name }}</span>
                                <span v-else class="text-gray-400">System</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Last updated by</dt>
                            <dd class="mt-1 text-gray-900">
                                <span v-if="admin.updatedByUser">{{ admin.updatedByUser.last_name }}, {{ admin.updatedByUser.first_name }}</span>
                                <span v-else class="text-gray-400">—</span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="flex">
                    <Link :href="route('users.index')">
                        <Button variant="outline">← Back to Admin Users</Button>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>