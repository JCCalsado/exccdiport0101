<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, Edit2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface Notification {
    id: number;
    title: string;
    message: string;
    target_role: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

interface Props {
    notification: Notification;
}

const props = withDefaults(defineProps<Props>(), {
    notification: () => ({
        id: 0,
        title: '',
        message: '',
        target_role: 'student',
        start_date: '',
        end_date: '',
        is_active: false,
        created_at: '',
        updated_at: '',
    }),
});

const page = usePage();
const isAccounting = computed(() => (page.props.auth as any)?.user?.role === 'accounting');

const breadcrumbs = computed(() => {
    if (isAccounting.value) {
        return [
            { title: 'Accounting', href: route('accounting.dashboard') },
            { title: 'Notifications', href: route('accounting.notifications.index') },
            { title: props.notification.title, href: route('accounting.notifications.show', props.notification.id) },
        ];
    }
    return [
        { title: 'Admin', href: route('admin.dashboard') },
        { title: 'Notifications', href: route('admin.notifications.index') },
        { title: props.notification.title, href: route('admin.notifications.show', props.notification.id) },
    ];
});

const backHref = computed(() =>
    isAccounting.value
        ? route('accounting.notifications.index')
        : route('admin.notifications.index'),
);

const formatDate = (dateStr: string | null | undefined): string => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric', month: 'long', day: 'numeric',
    });
};
</script>

<template>
    <Head :title="`Notification: ${notification.title}`" />
    <AppLayout>
        <div class="w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ notification.title }}</h1>
                    <p class="mt-1 text-sm text-gray-500">Notification detail</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="backHref">
                        <Button variant="outline">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back
                        </Button>
                    </Link>
                    <!-- Edit: Accounting only -->
                    <Link
                        v-if="isAccounting"
                        :href="route('accounting.notifications.edit', notification.id)"
                    >
                        <Button>
                            <Edit2 class="mr-2 h-4 w-4" />
                            Edit
                        </Button>
                    </Link>
                    <!-- Admin: read-only badge -->
                    <span
                        v-else
                        class="inline-flex items-center rounded-full bg-purple-100 px-3 py-1 text-xs font-medium text-purple-700"
                    >
                        Read-only
                    </span>
                </div>
            </div>

            <div class="max-w-2xl space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Notification Details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <div class="flex justify-between border-b pb-3">
                            <span class="font-medium text-gray-600">Status</span>
                            <span
                                :class="[
                                    'rounded-full px-2.5 py-1 text-xs font-medium',
                                    notification.is_active
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-gray-100 text-gray-600',
                                ]"
                            >
                                {{ notification.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="flex justify-between border-b pb-3">
                            <span class="font-medium text-gray-600">Target Role</span>
                            <span class="capitalize text-gray-900">{{ notification.target_role }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-3">
                            <span class="font-medium text-gray-600">Start Date</span>
                            <span class="text-gray-900">
                                <Calendar class="mr-1 inline h-3.5 w-3.5" />
                                {{ formatDate(notification.start_date) }}
                            </span>
                        </div>
                        <div class="flex justify-between border-b pb-3">
                            <span class="font-medium text-gray-600">End Date</span>
                            <span class="text-gray-900">{{ formatDate(notification.end_date) }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-600">Message</span>
                            <p class="mt-2 rounded-lg bg-gray-50 p-3 text-gray-800 leading-relaxed">
                                {{ notification.message }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <div class="flex gap-2">
                    <Link :href="backHref">
                        <Button variant="outline">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back to Notifications
                        </Button>
                    </Link>
                    <Link
                        v-if="isAccounting"
                        :href="route('accounting.notifications.edit', notification.id)"
                    >
                        <Button>
                            <Edit2 class="mr-2 h-4 w-4" />
                            Edit Notification
                        </Button>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>