<template>
    <Head title="User Activity Log" />

    <AppLayout>
        <div class="w-full space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">User Activity Log</h1>
                    <p class="text-gray-500">View system activity and user interactions</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="grid grid-cols-4 gap-3">
                <input
                    v-model="searchUser"
                    type="text"
                    placeholder="Search by user email or name..."
                    class="rounded-lg border p-3 outline-none focus:border-transparent focus:ring-2 focus:ring-blue-500"
                />
                <select v-model="filterRole" class="rounded-lg border p-3 outline-none focus:border-transparent focus:ring-2 focus:ring-blue-500">
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="accounting">Accounting</option>
                    <option value="student">Student</option>
                </select>
                <select v-model="filterAction" class="rounded-lg border p-3 outline-none focus:border-transparent focus:ring-2 focus:ring-blue-500">
                    <option value="">All Actions</option>
                    <option value="login">Login</option>
                    <option value="logout">Logout</option>
                    <option value="created">Created</option>
                    <option value="updated">Updated</option>
                    <option value="deleted">Deleted</option>
                </select>
                <input
                    v-model="filterDate"
                    type="date"
                    class="rounded-lg border p-3 outline-none focus:border-transparent focus:ring-2 focus:ring-blue-500"
                />
            </div>

            <!-- Activity Table -->
            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <table class="w-full">
                    <thead class="border-b bg-gray-100">
                        <tr class="text-left text-sm font-semibold text-gray-700">
                            <th class="p-4">User</th>
                            <th class="p-4">Role</th>
                            <th class="p-4">Action</th>
                            <th class="p-4">Target</th>
                            <th class="p-4">Details</th>
                            <th class="p-4">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="activity in filteredActivities" :key="activity.id" class="border-b text-sm transition-colors hover:bg-gray-50">
                            <td class="p-4">
                                <div>
                                    <p class="font-medium">{{ activity.user_name }}</p>
                                    <p class="text-xs text-gray-500">{{ activity.user_email }}</p>
                                </div>
                            </td>
                            <td class="p-4">
                                <span
                                    :class="{
                                        'bg-purple-100 text-purple-800': activity.user_role === 'admin',
                                        'bg-blue-100 text-blue-800': activity.user_role === 'accounting',
                                        'bg-green-100 text-green-800': activity.user_role === 'student',
                                    }"
                                    class="rounded px-2 py-1 text-xs font-semibold"
                                >
                                    {{ activity.user_role }}
                                </span>
                            </td>
                            <td class="p-4">
                                <span
                                    :class="{
                                        'text-green-600': activity.action === 'login',
                                        'text-orange-600': activity.action === 'logout',
                                        'text-blue-600': activity.action === 'created',
                                        'text-yellow-600': activity.action === 'updated',
                                        'text-red-600': activity.action === 'deleted',
                                    }"
                                    class="font-medium"
                                >
                                    {{ activity.action }}
                                </span>
                            </td>
                            <td class="p-4 text-gray-700">{{ activity.target_type }}</td>
                            <td class="p-4 text-gray-600">{{ activity.description }}</td>
                            <td class="p-4 text-gray-600">{{ formatDateTime(activity.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Empty State -->
                <div v-if="filteredActivities.length === 0" class="p-12 text-center">
                    <p class="text-lg text-gray-500">No activity found</p>
                    <p class="mt-2 text-sm text-gray-400">Try adjusting your filters</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Activity {
    id: number;
    user_name: string;
    user_email: string;
    user_role: string;
    action: string;
    target_type: string;
    description: string;
    created_at: string;
}

interface Props {
    activities: Activity[];
}

const props = defineProps<Props>();

const searchUser = ref('');
const filterRole = ref('');
const filterAction = ref('');
const filterDate = ref('');

const breadcrumbs = [{ title: 'Dashboard', href: route('admin.dashboard') }, { title: 'User Activity' }];

const filteredActivities = computed(() => {
    let filtered = props.activities;

    if (searchUser.value) {
        const query = searchUser.value.toLowerCase();
        filtered = filtered.filter((a) => a.user_name.toLowerCase().includes(query) || a.user_email.toLowerCase().includes(query));
    }

    if (filterRole.value) {
        filtered = filtered.filter((a) => a.user_role === filterRole.value);
    }

    if (filterAction.value) {
        filtered = filtered.filter((a) => a.action === filterAction.value);
    }

    if (filterDate.value) {
        filtered = filtered.filter((a) => a.created_at.startsWith(filterDate.value));
    }

    return filtered;
});

const formatDateTime = (dateTime: string) => {
    return new Date(dateTime).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>
