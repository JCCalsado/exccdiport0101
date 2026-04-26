<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
    width?: number;
    height?: number;
}

defineProps<Props>();
</script>

<template>
    <img src="/images/logo.png" :class="className" :width="width || 40" :height="height || 42" alt="Logo" v-bind="$attrs" />
</template>
