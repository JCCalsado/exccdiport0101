<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex h-full items-center justify-center">
        <div class="flex items-center space-x-2">
            <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
            </div>
            <div class="grid text-left text-sm">
                <span class="mb-0.5 truncate leading-tight font-semibold">CCDI Account Portal</span>
            </div>
        </div>
    </div>
</template>
