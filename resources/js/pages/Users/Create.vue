<template>
    <div class="mx-auto max-w-2xl p-6">
        <h1 class="mb-6 text-2xl font-bold">Create New User</h1>

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
                <label class="mb-1 block text-sm font-medium text-gray-700">Password *</label>
                <input v-model="form.password" type="password" required class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500" />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Confirm Password *</label>
                <input
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Role *</label>
                <select v-model="form.role" required class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option v-for="role in userRoles" :key="role.value" :value="role.value">
                        {{ role.label() }}
                    </option>
                </select>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button type="button" @click="goBack" class="rounded-lg border px-4 py-2 text-gray-600 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Create User</button>
            </div>
        </form>
    </div>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const { userRoles } = defineProps<{
    userRoles: any[];
    message: string;
}>();

const form = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'student',
});

function submit() {
    router.post('/users', form);
}
function goBack() {
    window.history.back();
}
</script>
