<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

export interface BreadcrumbItemType {
    title: string;
    href?: string;
}

const props = defineProps<{
    items: BreadcrumbItemType[];
}>();
</script>

<template>
    <nav class="flex w-full" aria-label="Breadcrumb">
        <ol class="flex w-full items-center space-x-1 md:space-x-3">
            <li v-for="(item, index) in items" :key="index" class="inline-flex items-center">
                <svg
                    v-if="index > 0"
                    class="mx-1 h-3 w-3 text-gray-400"
                    aria-hidden="true"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 6 10"
                >
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4" />
                </svg>
                <!-- Last item = current page, always render as plain text (non-clickable) -->
                <span v-if="index === items.length - 1" class="text-sm font-medium text-gray-500" aria-current="page">
                    {{ item.title }}
                </span>
                <Link v-else-if="item.href" :href="item.href" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                    {{ item.title }}
                </Link>
                <span v-else class="text-sm font-medium text-gray-500">
                    {{ item.title }}
                </span>
            </li>
        </ol>
    </nav>
</template>
