<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { StudentUser, User } from '@/types';
import { computed } from 'vue';

interface Props {
    user: User | StudentUser;
    showEmail?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const { getInitials } = useInitials();

/**
 * Avatar URL comes from the unified `avatar` field set in HandleInertiaRequests.
 * It is already a full /storage/... URL, not a raw path.
 */
const avatarUrl = computed(() => props.user.avatar ?? null);
const initials = computed(() => getInitials(props.user.name));
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage v-if="avatarUrl" :src="avatarUrl" :alt="user.name" />
        <AvatarFallback class="rounded-lg bg-muted text-black dark:text-white">
            {{ initials }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{ user.email }}</span>
    </div>
</template>
