<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowLeft, Briefcase, Eye, EyeOff, GraduationCap, LoaderCircle, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const selectedRole = ref<'admin' | 'accounting' | 'student' | null>(null);
const showPassword = ref(false);
const isSubmitting = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
    role: null as 'admin' | 'accounting' | 'student' | null,
});

const roleOptions = [
    {
        value: 'admin',
        label: 'Admin',
        icon: User,
        color: 'bg-red-600 hover:bg-red-700',
        borderColor: 'border-red-600',
        description: 'System administrators and managers',
    },
    {
        value: 'accounting',
        label: 'Accounting',
        icon: Briefcase,
        color: 'bg-blue-600 hover:bg-blue-700',
        borderColor: 'border-blue-600',
        description: 'Accounting staff and financial officers',
    },
    {
        value: 'student',
        label: 'Student',
        icon: GraduationCap,
        color: 'bg-green-600 hover:bg-green-700',
        borderColor: 'border-green-600',
        description: 'Students and learners',
    },
] as const;

const currentRole = computed(() => roleOptions.find((r) => r.value === selectedRole.value));

const selectRole = (role: 'admin' | 'accounting' | 'student') => {
    selectedRole.value = role;
    form.role = role;
};

const backToRoleSelection = () => {
    selectedRole.value = null;
    form.reset('password');
};

const submit = async () => {
    // Get CSRF token and add it to the form
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (!csrfToken) {
        console.error('CSRF token not found in meta tag');
        return;
    }

    isSubmitting.value = true;

    try {
        // Submit form via axios with explicit CSRF token header
        const response = await axios.post(
            '/login',
            {
                email: form.email,
                password: form.password,
                remember: form.remember ? 1 : 0,
                role: form.role,
                _token: csrfToken, // Include token as POST parameter
            },
            {
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
            },
        );

        // On success, reload the page or redirect
        window.location.href = '/dashboard';
    } catch (error: any) {
        console.error('Login error:', error);

        // Handle validation errors or other errors
        if (error.response?.status === 422) {
            form.errors = error.response.data.errors || {};
        } else if (error.response?.status === 419) {
            console.error('CSRF token validation failed');
            // Reload to get a fresh token
            window.location.reload();
        }
    } finally {
        isSubmitting.value = false;
    }
};

const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
};
</script>

<template>
    <AuthBase
        :title="selectedRole ? `Log in as ${currentRole?.label}` : 'Welcome to CCDI Account Portal'"
        :description="selectedRole ? 'Enter your credentials to continue' : 'Select your role to get started'"
    >
        <Head title="Log in" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <!-- ROLE SELECTION -->
        <div v-if="!selectedRole" class="space-y-4">
            <div class="mb-6 text-center">
                <p class="text-sm text-gray-600">Choose your role to access the portal</p>
            </div>

            <div class="space-y-3">
                <button
                    v-for="role in roleOptions"
                    :key="role.value"
                    type="button"
                    @click="selectRole(role.value)"
                    :class="[
                        'w-full rounded-lg p-4 font-medium text-white transition-all',
                        'flex transform items-center gap-4 hover:scale-105',
                        role.color,
                    ]"
                >
                    <div class="rounded-lg bg-white/20 p-3">
                        <component :is="role.icon" :size="24" />
                    </div>
                    <div class="flex-1 text-left">
                        <p class="text-lg font-semibold">{{ role.label }}</p>
                        <p class="text-sm text-white/80">{{ role.description }}</p>
                    </div>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>

            <div class="border-t pt-4 text-center text-sm text-muted-foreground">
                Don't have an account?
                <TextLink :href="route('register')">Sign up as Student</TextLink>
            </div>
        </div>

        <!-- LOGIN FORM -->
        <div v-else class="space-y-6">
            <!-- ROLE BADGE -->
            <div class="flex items-center justify-between rounded-lg border-2 p-4" :class="currentRole?.borderColor">
                <div class="flex items-center gap-3">
                    <div :class="['rounded-lg p-2 text-white', currentRole?.color]">
                        <component :is="currentRole?.icon" :size="20" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Logging in as</p>
                        <p class="font-semibold text-gray-900">{{ currentRole?.label }}</p>
                    </div>
                </div>
                <button type="button" @click="backToRoleSelection" class="rounded-lg p-2 hover:bg-gray-100">
                    <ArrowLeft :size="20" class="text-gray-600" />
                </button>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <div class="grid gap-6">
                    <!-- EMAIL -->
                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="email"
                            :class="{ 'border-red-500': form.errors.email }"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <!-- PASSWORD -->
                    <div class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <Label for="password">Password</Label>
                            <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm"> Forgot password? </TextLink>
                        </div>

                        <div class="relative">
                            <Input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                v-model="form.password"
                                required
                                autocomplete="current-password"
                                class="pr-10"
                            />
                            <button type="button" @click="togglePasswordVisibility" class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-500">
                                <Eye v-if="!showPassword" :size="18" />
                                <EyeOff v-else :size="18" />
                            </button>
                        </div>

                        <InputError :message="form.errors.password" />
                    </div>

                    <!-- REMEMBER -->
                    <div class="flex items-center space-x-3">
                        <Checkbox v-model:checked="form.remember" />
                        <span>Remember me</span>
                    </div>

                    <!-- SUBMIT -->
                    <Button type="submit" class="mt-4 w-full" :class="currentRole?.color" :disabled="isSubmitting">
                        <LoaderCircle v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        Log in
                    </Button>
                </div>

                <div class="text-center text-sm text-muted-foreground">
                    <button type="button" @click="backToRoleSelection" class="text-blue-600 hover:underline">← Back to role selection</button>
                </div>
            </form>
        </div>
    </AuthBase>
</template>
