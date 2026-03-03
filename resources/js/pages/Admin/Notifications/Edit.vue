<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Form from './Form.vue';

interface Student {
    id: number;
    name: string;
    email: string;
}

interface Notification {
    id: number;
    title: string;
    message: string;
    target_role: string;
    start_date: string;
    end_date: string;
    user_id?: number | null;
    is_active: boolean;
}

interface Props {
    notification: Notification;
    students: Student[];
}

defineProps<Props>();
</script>

<template>
    <Head title="Edit Notification" />
    <Form :notification="notification" :students="students" />
</template>
