<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const form = useForm({
    last_name: '',
    first_name: '',
    middle_initial: '',
    birthday: '',
    email: '',
    year_level: '',
    course: '',
    address: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase title="Create an account" description="Enter your details below to create your account">
        <Head title="Register" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <!-- Last Name -->
                <div class="grid gap-2">
                    <Label for="last_name">Last Name</Label>
                    <Input id="last_name" type="text" required v-model="form.last_name" placeholder="Dela Cruz" autocomplete="family-name" />
                    <InputError :message="form.errors.last_name" />
                </div>

                <!-- First Name -->
                <div class="grid gap-2">
                    <Label for="first_name">First Name</Label>
                    <Input id="first_name" type="text" required v-model="form.first_name" placeholder="Juan" autocomplete="given-name" />
                    <InputError :message="form.errors.first_name" />
                </div>

                <!-- Middle Initial / Suffix -->
                <div class="grid gap-2">
                    <Label for="middle_initial">Middle Initial</Label>
                    <Input id="middle_initial" type="text" v-model="form.middle_initial" placeholder="M." autocomplete="additional-name" />
                    <InputError :message="form.errors.middle_initial" />
                </div>

                <!-- Birthday -->
                <div class="grid gap-2">
                    <Label for="birthday">Birthday</Label>
                    <Input id="birthday" type="date" required v-model="form.birthday" />
                    <InputError :message="form.errors.birthday" />
                </div>

                <!-- Email -->
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input id="email" type="email" required autocomplete="email" v-model="form.email" placeholder="email@example.com" />
                    <InputError :message="form.errors.email" />
                </div>

                <!-- Year Level -->
                <div class="grid gap-2">
                    <Label for="year_level">Year Level</Label>
                    <select id="year_level" v-model="form.year_level" required class="rounded border px-3 py-2">
                        <option value="">Select Year Level</option>
                        <option value="1st Year">1st Year</option>
                        <option value="2nd Year">2nd Year</option>
                        <option value="3rd Year">3rd Year</option>
                        <option value="4th Year">4th Year</option>
                    </select>
                    <InputError :message="form.errors.year_level" />
                </div>

                <!-- Course -->
                <div class="grid gap-2">
                    <Label for="course">Course</Label>
                    <Input id="course" type="text" required v-model="form.course" placeholder="BS Computer Science" />
                    <InputError :message="form.errors.course" />
                </div>

                <!-- Address -->
                <div class="grid gap-2">
                    <Label for="address">Address</Label>
                    <Input id="address" type="text" required v-model="form.address" placeholder="Sorsogon City" />
                    <InputError :message="form.errors.address" />
                </div>

                <!-- Phone -->
                <div class="grid gap-2">
                    <Label for="phone">Phone Number</Label>
                    <Input id="phone" type="text" required v-model="form.phone" placeholder="09171234567" />
                    <InputError :message="form.errors.phone" />
                </div>

                <!-- Password -->
                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input id="password" type="password" required autocomplete="new-password" v-model="form.password" placeholder="Password" />
                    <InputError :message="form.errors.password" />
                </div>

                <!-- Confirm Password -->
                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm Password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        placeholder="Confirm password"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <!-- Submit -->
                <Button type="submit" class="mt-2 w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Create account
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink :href="route('login')" class="underline underline-offset-4">Log in</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
