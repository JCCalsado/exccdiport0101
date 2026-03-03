<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Form from './Form.vue';

interface Student {
    id: number;
    name: string;
    email: string;
}

interface Props {
    students: Student[];
}

defineProps<Props>();
</script>

<template>
    <Head title="Create Notification" />
    <Form :students="students" />
</template>
