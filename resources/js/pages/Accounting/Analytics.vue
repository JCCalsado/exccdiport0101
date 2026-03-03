<template>
    <div class="p-6">
        <h1 class="mb-4 text-2xl font-bold">Payment Analytics</h1>
        <p class="text-gray-600">{{ message }}</p>
    </div>
</template>

<script setup lang="ts">
defineProps<{
    paymentsByMethod: any[];
    message: string;
}>();
</script>
