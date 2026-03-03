<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { AlertCircle, Bell, Calendar } from 'lucide-vue-next';

type Notification = {
    id: number;
    title: string;
    message: string;
    start_date: string | null;
    end_date: string | null;
    target_role: string;
    created_at: string;
};

defineProps<{
    notifications: Notification[];
    role: string;
}>();

const breadcrumbs = [{ title: 'Dashboard', href: route('dashboard') }, { title: 'Notifications' }];

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
};

const isActive = (notification: Notification) => {
    const now = new Date();
    if (!notification.start_date) return true;

    const startDate = new Date(notification.start_date);
    const endDate = notification.end_date ? new Date(notification.end_date) : null;

    return startDate <= now && (!endDate || endDate >= now);
};
</script>

<template>
    <AppLayout>
        <Head title="Notifications" />

        <div class="w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="mb-6 flex items-center gap-3">
                <div class="rounded-lg bg-blue-100 p-3">
                    <Bell :size="28" class="text-blue-600" />
                </div>
                <div>
                    <h1 class="text-3xl font-bold">Notifications</h1>
                    <p class="text-gray-600">Stay updated with important announcements</p>
                </div>
            </div>

            <!-- Notifications Grid -->
            <div v-if="notifications.length" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="notification in notifications"
                    :key="notification.id"
                    :class="[
                        'rounded-lg border p-6 shadow-sm transition-all hover:shadow-md',
                        isActive(notification) ? 'border-blue-200 bg-blue-50' : 'border-gray-200 bg-white',
                    ]"
                >
                    <!-- Active Badge -->
                    <div v-if="isActive(notification)" class="mb-3 flex items-center gap-2">
                        <span class="rounded bg-blue-500 px-2 py-1 text-xs font-medium text-white"> ACTIVE </span>
                    </div>

                    <!-- Title -->
                    <h2 class="mb-3 text-lg font-bold" :class="isActive(notification) ? 'text-blue-900' : 'text-gray-900'">
                        {{ notification.title }}
                    </h2>

                    <!-- Message -->
                    <p class="mb-4 text-sm leading-relaxed whitespace-pre-line text-gray-700">
                        {{ notification.message }}
                    </p>

                    <!-- Date Range -->
                    <div v-if="notification.start_date" class="flex items-center gap-2 border-t pt-3 text-sm text-gray-600">
                        <Calendar :size="16" />
                        <span>
                            {{ formatDate(notification.start_date) }}
                            <span v-if="notification.end_date"> - {{ formatDate(notification.end_date) }} </span>
                        </span>
                    </div>

                    <!-- Target Role Badge -->
                    <div class="mt-3">
                        <span class="rounded bg-gray-200 px-2 py-1 text-xs text-gray-700"> For: {{ notification.target_role }} </span>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="py-12 text-center">
                <AlertCircle :size="64" class="mx-auto mb-4 text-gray-400" />
                <p class="mb-2 text-lg text-gray-500">No notifications found</p>
                <p class="text-sm text-gray-400">Check back later for important announcements</p>
            </div>
        </div>
    </AppLayout>
</template>
