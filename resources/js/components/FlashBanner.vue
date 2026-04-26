<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type FlashType = 'error' | 'warning' | 'success' | 'info';

interface FlashProps {
    error?: string | null;
    warning?: string | null;
    success?: string | null;
    info?: string | null;
}

const page = usePage();

const flash = computed<FlashProps>(() => (page.props.flash as FlashProps) ?? {});

// Active message — picked in priority order: error > warning > success > info
const activeType = computed<FlashType | null>(() => {
    if (flash.value.error) return 'error';
    if (flash.value.warning) return 'warning';
    if (flash.value.success) return 'success';
    if (flash.value.info) return 'info';
    return null;
});

const activeMessage = computed<string | null>(() => {
    if (!activeType.value) return null;
    return flash.value[activeType.value] ?? null;
});

// Dismissal state — resets whenever a new flash arrives
const dismissed = ref(false);

watch(activeMessage, (msg) => {
    if (msg) dismissed.value = false;
});

const isVisible = computed(() => !!activeMessage.value && !dismissed.value);

// Style map — one entry per flash type
const styles: Record<FlashType, { container: string; icon: string; text: string; close: string }> = {
    error: {
        container: 'bg-red-50 border-red-200 dark:bg-red-950 dark:border-red-800',
        icon: 'text-red-500 dark:text-red-400',
        text: 'text-red-800 dark:text-red-200',
        close: 'text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200',
    },
    warning: {
        container: 'bg-amber-50 border-amber-200 dark:bg-amber-950 dark:border-amber-800',
        icon: 'text-amber-500 dark:text-amber-400',
        text: 'text-amber-800 dark:text-amber-200',
        close: 'text-amber-500 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-200',
    },
    success: {
        container: 'bg-green-50 border-green-200 dark:bg-green-950 dark:border-green-800',
        icon: 'text-green-500 dark:text-green-400',
        text: 'text-green-800 dark:text-green-200',
        close: 'text-green-500 hover:text-green-700 dark:text-green-400 dark:hover:text-green-200',
    },
    info: {
        container: 'bg-blue-50 border-blue-200 dark:bg-blue-950 dark:border-blue-800',
        icon: 'text-blue-500 dark:text-blue-400',
        text: 'text-blue-800 dark:text-blue-200',
        close: 'text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-200',
    },
};

const currentStyle = computed(() => (activeType.value ? styles[activeType.value] : null));

// SVG icon paths — avoids adding lucide-vue-next as a dep just for this
const iconPaths: Record<FlashType, string> = {
    error: 'M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
    warning: 'M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
    success: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    info: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
};
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
    >
        <div
            v-if="isVisible && activeType && currentStyle"
            :class="['mx-4 mt-4 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm', currentStyle.container]"
            role="alert"
        >
            <!-- Icon -->
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="mt-0.5 h-4 w-4 shrink-0"
                :class="currentStyle.icon"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconPaths[activeType]" />
            </svg>

            <!-- Message -->
            <p :class="['flex-1 leading-relaxed', currentStyle.text]">
                {{ activeMessage }}
            </p>

            <!-- Dismiss button -->
            <button type="button" :class="['shrink-0 transition-colors', currentStyle.close]" aria-label="Dismiss" @click="dismissed = true">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </Transition>
</template>
