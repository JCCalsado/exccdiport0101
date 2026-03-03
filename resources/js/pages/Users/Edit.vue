<template>
    <div class="w-full p-6">
        <!-- Breadcrumbs -->
        <Breadcrumbs :items="breadcrumbs" />

        <!-- Page Heading -->
        <h1 class="mb-6 text-2xl font-bold">Edit User: {{ user.name }}</h1>

        <!-- Form -->
        <form @submit.prevent="submit" class="space-y-4 rounded-xl bg-white p-6 shadow-md">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Name *</label>
                <input v-model="form.name" required class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500" />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Email *</label>
                <input v-model="form.email" type="email" required class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500" />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">New Password (leave blank to keep current)</label>
                <input v-model="form.password" type="password" class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500" />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Confirm Password</label>
                <input
                    v-model="form.password_confirmation"
                    type="password"
                    class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Role *</label>
                <select v-model="form.role" required class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option v-for="role in userRoles" :key="role" :value="role">
                        {{ role.charAt(0).toUpperCase() + role.slice(1).replace('_', ' ') }}
                    </option>
                </select>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button
                    type="button"
                    @click="$page.props.auth?.user && $router.back()"
                    class="rounded-lg border px-4 py-2 text-gray-600 hover:bg-gray-50"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                >
                    Update User
                </button>
            </div>
        </form>
    </div>
</template>

<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { useForm } from '@inertiajs/vue3';

const { user, userRoles } = defineProps<{
    user: any;
    userRoles: any[];
    message: string;
}>();

const form = useForm({
    name: user.name,
    email: user.email,
    password: '',
    password_confirmation: '',
    role: user.role,
});

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Users', href: route('users.index') },
    { title: `Edit ${user.name}`, href: '#' },
];

function submit() {
    form.put(route('users.update', user.id));
}
</script>
