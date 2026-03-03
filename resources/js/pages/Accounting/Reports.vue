<template>
    <div class="p-6">
        <h1 class="mb-4 text-2xl font-bold">Financial Reports</h1>
        <p class="text-gray-600">{{ message }}</p>
        <p class="mt-2 text-sm text-gray-500">Report type: {{ reportType }}</p>
    </div>
</template>

<script setup lang="ts">
defineProps<{
    reportType: string;
    data: any;
    message: string;
}>();
</script>
