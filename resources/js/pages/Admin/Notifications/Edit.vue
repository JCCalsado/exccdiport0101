<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Form from './Form.vue';

interface Student {
    id: number;
    name: string;
    email: string;
}

interface PaymentTerm {
    id: number;
    term_name: string;
    term_order: number;
}

interface Notification {
    id: number;
    title: string;
    message: string;
    type?: string;
    target_role: string;
    start_date: string;
    end_date: string;
    due_date?: string | null;
    payment_term_id?: number | null;
    user_id?: number | null;
    is_active: boolean;
    term_ids?: number[] | null;
    target_term_name?: string | null;
    trigger_days_before_due?: number | null;
}

interface Props {
    notification: Notification;
    students: Student[];
    paymentTerms: PaymentTerm[];
}

defineProps<Props>();
</script>

<template>
    <Head title="Edit Notification" />
    <Form :notification="notification" :students="students" :payment-terms="paymentTerms" />
</template>
