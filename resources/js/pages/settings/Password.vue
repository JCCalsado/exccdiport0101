<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Password settings',
        href: route('profile.edit'),
    },
];

const passwordInput = ref<HTMLInputElement | null>(null);
const currentPasswordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const recentlySuccessful = ref(false);

const submit = () => {
    form.put(route('password.update'), {
        preserveScroll: true,

        onSuccess: () => {
            // Clear all three fields so the form visually confirms completion.
            // Without this the user sees no change and assumes the submit failed.
            form.reset();
            recentlySuccessful.value = true;
            setTimeout(() => {
                recentlySuccessful.value = false;
            }, 4000);
        },

        onError: () => {
            // Wrong new password / confirmation mismatch — clear new password
            // fields and refocus so the user can re-type without extra clicks.
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            // Wrong current password — clear that field and refocus.
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Password settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Update password" description="Ensure your account is using a long, random password to stay secure" />

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Current Password -->
                    <div class="grid gap-2">
                        <Label for="current_password">Current password</Label>
                        <Input
                            id="current_password"
                            ref="currentPasswordInput"
                            name="current_password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="current-password"
                            placeholder="Current password"
                            v-model="form.current_password"
                        />
                        <InputError :message="form.errors.current_password" />
                    </div>

                    <!-- New Password -->
                    <div class="grid gap-2">
                        <Label for="password">New password</Label>
                        <Input
                            id="password"
                            ref="passwordInput"
                            name="password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="New password"
                            v-model="form.password"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <!-- Confirm New Password -->
                    <div class="grid gap-2">
                        <Label for="password_confirmation">Confirm password</Label>
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="Confirm password"
                            v-model="form.password_confirmation"
                        />
                        <InputError :message="form.errors.password_confirmation" />
                    </div>

                    <!-- Submit + feedback -->
                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing" type="submit">
                            <span v-if="form.processing">Saving…</span>
                            <span v-else>Save password</span>
                        </Button>

                        <Transition
                            enter-active-class="transition ease-in-out duration-300"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out duration-300"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="recentlySuccessful" class="text-sm font-medium text-green-600">✓ Password updated successfully.</p>
                        </Transition>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
