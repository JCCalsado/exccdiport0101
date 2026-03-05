<script setup lang="ts">
import type { StudentUser } from '@/types/user';
import type { Page } from '@inertiajs/core';
import { Form, Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}
defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: route('profile.edit'),
    },
];

type AppPageProps = Page['props'] & {
    auth: {
        user: StudentUser;
    };
};

const page = usePage<AppPageProps>();
const user = page.props.auth.user;

// Determine user role
const userRole = computed(() => {
    const role = (user as any).role;
    if (!role) return 'student';
    if (typeof role === 'string') return role;
    return role.value ?? role.name ?? 'student';
});

const isStudent = computed(() => userRole.value === 'student');
const isAccountingOrAdmin = computed(() => ['accounting', 'admin'].includes(userRole.value));
const isAdmin = computed(() => userRole.value === 'admin');

// Normalize status (supports string or enum-like object)
const initialStatus = (() => {
    const s = (user as any).status;
    if (!s) return 'active';
    if (typeof s === 'string') return s;
    return s.value ?? s.name ?? 'active';
})();

// Format birthday for date input (YYYY-MM-DD)
const formatBirthday = (birthday: any) => {
    if (!birthday) return '';
    if (typeof birthday === 'string') {
        // If it's already in YYYY-MM-DD format, return as is
        if (/^\d{4}-\d{2}-\d{2}$/.test(birthday)) return birthday;
        // If it's a different format, try to parse it
        try {
            const date = new Date(birthday);
            return date.toISOString().split('T')[0];
        } catch {
            return '';
        }
    }
    return '';
};

// Main form with split name fields
const form = useForm({
    last_name: (user as any).last_name ?? '',
    first_name: (user as any).first_name ?? '',
    middle_initial: (user as any).middle_initial ?? '',
    email: user.email ?? '',
    birthday: formatBirthday((user as any).birthday),
    address: (user as any).address ?? '',
    phone: (user as any).phone ?? '',
    // Student-specific fields
    student_id: (user as any).student_id ?? '',
    course: (user as any).course ?? '',
    year_level: (user as any).year_level ?? '',
    // Staff-specific fields
    faculty: (user as any).faculty ?? '',
    status: initialStatus,
});

// PROFILE PICTURE handling
const profilePicturePreview = ref<string | null>(user.profile_picture ? `/storage/${user.profile_picture}` : null);
const profilePictureError = ref<string | undefined>();

const profilePictureForm = useForm<{ profile_picture: File | null }>({
    profile_picture: null,
});

const profilePictureInput = ref<HTMLInputElement | null>(null);

const selectProfilePicture = () => {
    profilePictureInput.value?.click();
};

const updateProfilePicturePreview = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (!target.files || target.files.length === 0) return;

    const file = target.files[0];
    profilePictureForm.profile_picture = file;

    const reader = new FileReader();
    reader.onload = (e) => {
        profilePicturePreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);

    profilePictureForm.post(route('profile.update-picture'), {
        forceFormData: true,
        onError: (errors) => {
            profilePictureError.value = (errors as any).profile_picture ?? undefined;
        },
        onSuccess: () => {
            profilePictureError.value = undefined;
        },
    });
};

const removeProfilePicture = () => {
    router.delete(route('profile.remove-picture'), {
        onSuccess: () => {
            profilePicturePreview.value = null;
        },
    });
};

const hasProfilePicture = computed(() => !!profilePicturePreview.value);

// Get display initial for profile picture
const profileInitial = computed(() => {
    if (form.first_name) return form.first_name.charAt(0).toUpperCase();
    if (user.name) return user.name.charAt(0).toUpperCase();
    return '?';
});

// Course options
const courseOptions = ['BS Computer Science', 'BS Information Technology', 'BS Accountancy', 'BS Business Administration'];

// Year level options
const yearLevelOptions = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <!-- Profile form -->
                <Form
                    :method="'patch'"
                    :action="route('profile.update')"
                    :model="form"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <!-- Profile Picture -->
                    <div class="mb-8 flex flex-col space-y-6">
                        <HeadingSmall title="Profile Picture" description="Update your profile picture" />
                        <div class="flex items-center space-x-6">
                            <div class="shrink-0">
                                <img
                                    v-if="profilePicturePreview"
                                    :src="profilePicturePreview"
                                    class="h-20 w-20 rounded-full border object-cover"
                                    alt="Profile preview"
                                />
                                <div v-else class="flex h-20 w-20 items-center justify-center rounded-full border bg-muted">
                                    <span class="text-lg font-medium text-muted-foreground">
                                        {{ profileInitial }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <form @submit.prevent>
                                    <input
                                        ref="profilePictureInput"
                                        type="file"
                                        class="hidden"
                                        accept="image/*"
                                        @change="updateProfilePicturePreview"
                                        autocomplete="off"
                                    />
                                    <Button type="button" variant="outline" @click="selectProfilePicture" :disabled="profilePictureForm.processing">
                                        <span v-if="profilePictureForm.processing">Uploading...</span>
                                        <span v-else>Select New Photo</span>
                                    </Button>
                                    <div v-if="hasProfilePicture" class="mt-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            @click="removeProfilePicture"
                                            :disabled="profilePictureForm.processing"
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </form>
                                <InputError class="mt-2" :message="profilePictureError" />
                            </div>
                        </div>
                    </div>

                    <HeadingSmall
                        title="Profile information"
                        :description="isStudent ? 'Update your student account information' : 'Update your account information'"
                    />

                    <!-- Last Name -->
                    <div class="grid gap-2">
                        <Label for="last_name">Last Name <span class="text-red-500">*</span></Label>
                        <Input id="last_name" name="last_name" v-model="form.last_name" autocomplete="family-name" required placeholder="Dela Cruz" />
                        <InputError class="mt-2" :message="errors.last_name" />
                    </div>

                    <!-- First Name -->
                    <div class="grid gap-2">
                        <Label for="first_name">First Name <span class="text-red-500">*</span></Label>
                        <Input id="first_name" name="first_name" v-model="form.first_name" autocomplete="given-name" required placeholder="Juan" />
                        <InputError class="mt-2" :message="errors.first_name" />
                    </div>

                    <!-- Middle Initial -->
                    <div class="grid gap-2">
                        <Label for="middle_initial">Middle Initial</Label>
                        <Input
                            id="middle_initial"
                            name="middle_initial"
                            v-model="form.middle_initial"
                            autocomplete="additional-name"
                            placeholder="P"
                            maxlength="10"
                        />
                        <InputError class="mt-2" :message="errors.middle_initial" />
                    </div>

                    <!-- Account ID (Students Only) - Read Only -->
                    <div v-if="isStudent" class="grid gap-2">
                        <Label for="student_id">Account ID</Label>
                        <div class="flex items-center rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-gray-600">
                            <span class="font-medium">{{ form.student_id }}</span>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="grid gap-2">
                        <Label for="email">Email address <span class="text-red-500">*</span></Label>
                        <Input
                            id="email"
                            name="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            required
                            placeholder="student@ccdi.edu.ph"
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <!-- Birthday -->
                    <div class="grid gap-2">
                        <Label for="birthday">Birthday</Label>
                        <Input
                            id="birthday"
                            name="birthday"
                            v-model="form.birthday"
                            type="date"
                            autocomplete="bday"
                            :max="new Date().toISOString().split('T')[0]"
                        />
                        <InputError class="mt-2" :message="errors.birthday" />
                    </div>

                    <!-- Phone -->
                    <div class="grid gap-2">
                        <Label for="phone">Phone</Label>
                        <Input id="phone" name="phone" v-model="form.phone" autocomplete="tel" placeholder="09171234567" />
                        <InputError class="mt-2" :message="errors.phone" />
                    </div>

                    <!-- Address -->
                    <div class="grid gap-2">
                        <Label for="address">Address</Label>
                        <Input id="address" name="address" v-model="form.address" autocomplete="street-address" placeholder="Sorsogon City" />
                        <InputError class="mt-2" :message="errors.address" />
                    </div>

                    <!-- Faculty (Accounting/Admin Only) -->
                    <div v-if="isAccountingOrAdmin" class="grid gap-2">
                        <Label for="faculty">Faculty/Department</Label>
                        <Input
                            id="faculty"
                            name="faculty"
                            v-model="form.faculty"
                            autocomplete="organization"
                            placeholder="e.g., Accounting Department"
                        />
                        <InputError class="mt-2" :message="errors.faculty" />
                    </div>

                    <!-- Course (Students Only) -->
                    <div v-if="isStudent" class="grid gap-2">
                        <Label for="course">Course <span class="text-red-500">*</span></Label>
                        <select
                            id="course"
                            name="course"
                            v-model="form.course"
                            required
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">Select Course</option>
                            <option v-for="course in courseOptions" :key="course" :value="course">
                                {{ course }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="errors.course" />
                    </div>

                    <!-- Year Level (Students Only) -->
                    <div v-if="isStudent" class="grid gap-2">
                        <Label for="year_level">Year Level <span class="text-red-500">*</span></Label>
                        <select
                            id="year_level"
                            name="year_level"
                            v-model="form.year_level"
                            required
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">Select Year Level</option>
                            <option v-for="year in yearLevelOptions" :key="year" :value="year">
                                {{ year }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="errors.year_level" />
                    </div>

                    <!-- Status (Students Only, Admin-editable) -->
                    <div v-if="isStudent" class="grid gap-2">
                        <Label for="status">Status</Label>
                        <div v-if="isAdmin">
                            <select
                                id="status"
                                name="status"
                                v-model="form.status"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                autocomplete="off"
                            >
                                <option value="active">Active</option>
                                <option value="graduated">Graduated</option>
                                <option value="dropped">Dropped</option>
                            </select>
                        </div>
                        <div v-else>
                            <div class="w-full rounded border bg-gray-50 px-3 py-2 text-gray-700 capitalize">
                                {{ form.status }}
                            </div>
                        </div>
                        <InputError class="mt-2" :message="errors.status" />
                    </div>

                    <!-- Email verification -->
                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Your email address is unverified.
                            <Link
                                :href="route('verification.send')"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Click here to resend the verification email.
                            </Link>
                        </p>
                        <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                            A new verification link has been sent to your email address.
                        </div>
                    </div>

                    <!-- Save -->
                    <div class="flex items-center gap-4">
                        <Button type="submit" :disabled="processing">Save</Button>
                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="recentlySuccessful" class="text-sm text-neutral-600">Saved.</p>
                        </Transition>
                    </div>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
