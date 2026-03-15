<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TermsAcceptance from '@/components/TermsAcceptance.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';

interface Props {
    admin?: any;
    isEditing?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    isEditing: false,
});

const form = useForm({
    last_name: props.admin?.last_name ?? '',
    first_name: props.admin?.first_name ?? '',
    middle_initial: props.admin?.middle_initial ?? '',
    email: props.admin?.email ?? '',
    password: '',
    password_confirmation: '',
    admin_type: props.admin?.admin_type ?? 'manager',
    department: props.admin?.department ?? '',
    is_active: props.admin?.is_active ?? true,
    terms_accepted: props.admin?.terms_accepted_at ? true : false,
});

const submit = () => {
    if (props.isEditing) {
        form.put(route('users.update', props.admin.id));
    } else {
        form.post(route('users.store'));
    }
};

const goBack = () => {
    history.back();
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <Label for="last_name">Last Name *</Label>
                <Input id="last_name" v-model="form.last_name" type="text" required />
                <InputError :message="form.errors.last_name" />
            </div>

            <div>
                <Label for="first_name">First Name *</Label>
                <Input id="first_name" v-model="form.first_name" type="text" required />
                <InputError :message="form.errors.first_name" />
            </div>

            <div>
                <Label for="middle_initial">Middle Initial</Label>
                <Input id="middle_initial" v-model="form.middle_initial" type="text" maxlength="1" class="uppercase" />
                <InputError :message="form.errors.middle_initial" />
            </div>
        </div>

        <div>
            <Label for="email">Email Address *</Label>
            <Input id="email" v-model="form.email" type="email" required />
            <InputError :message="form.errors.email" />
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <Label for="password">{{ isEditing ? 'Password (leave blank to keep current)' : 'Password *' }}</Label>
                <Input id="password" v-model="form.password" type="password" :required="!isEditing" />
                <InputError :message="form.errors.password" />
            </div>

            <div>
                <Label for="password_confirmation">Confirm Password{{ isEditing ? '' : ' *' }}</Label>
                <Input id="password_confirmation" v-model="form.password_confirmation" type="password" :required="!isEditing" />
                <InputError :message="form.errors.password_confirmation" />
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <Label for="admin_type">Admin Type *</Label>
                <select id="admin_type" v-model="form.admin_type" class="w-full rounded-lg border px-3 py-2" required>
                    <option value="super">Super Admin</option>
                    <option value="manager">Manager</option>
                    <option value="operator">Operator</option>
                </select>
                <InputError :message="form.errors.admin_type" />
            </div>

            <div>
                <Label for="department">Department</Label>
                <Input id="department" v-model="form.department" type="text" placeholder="e.g., Finance, Operations" />
                <InputError :message="form.errors.department" />
            </div>
        </div>

        <div v-if="!isEditing" class="mt-6">
            <TermsAcceptance />
        </div>

        <div class="flex space-x-4 pt-4">
            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? 'Saving...' : isEditing ? 'Update Admin' : 'Create Admin' }}
            </Button>
            <Button type="button" variant="outline" @click="goBack">Cancel</Button>
        </div>
    </form>
</template>