<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, FileText, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    stats?: {
        totalAdmins: number;
        activeAdmins: number;
        inactiveAdmins: number;
        pendingApprovals: number;
        totalUsers: number;
        totalStudents: number;
        recentNotifications: Array<{
            id: number;
            title: string;
            targetRole: string;
            startDate: string;
            endDate: string;
            createdAt: string;
        }>;
        systemHealth: {
            status: string;
        };
    };
}

const props = withDefaults(defineProps<Props>(), {
    stats: () => ({
        totalAdmins: 0,
        activeAdmins: 0,
        inactiveAdmins: 0,
        pendingApprovals: 0,
        totalUsers: 0,
        totalStudents: 0,
        recentNotifications: [],
        systemHealth: {
            status: 'operational',
        },
    }),
});

const breadcrumbs = [
    { title: 'Admin', href: route('admin.dashboard') },
    { title: 'Dashboard', href: route('admin.dashboard') },
];

const adminStats = computed(() => [
    {
        title: 'Total Admins',
        value: props.stats?.totalAdmins || 0,
        description: `${props.stats?.activeAdmins || 0} active`,
        icon: Users,
        color: 'blue',
    },
    {
        title: 'Total Users',
        value: props.stats?.totalUsers || 0,
        description: `${props.stats?.totalStudents || 0} students`,
        icon: Users,
        color: 'purple',
    },
    {
        title: 'Pending Approvals',
        value: props.stats?.pendingApprovals || 0,
        description: 'Awaiting action',
        icon: AlertCircle,
        color: 'orange',
    },
    {
        title: 'System Status',
        value: 'Operational',
        description: 'All systems healthy',
        icon: CheckCircle2,
        color: 'green',
    },
]);

const getColorClass = (color: string) => {
    const colors: Record<string, string> = {
        blue: 'text-blue-500',
        purple: 'text-purple-500',
        orange: 'text-orange-500',
        green: 'text-green-500',
    };
    return colors[color] || 'text-gray-500';
};
</script>

<template>
    <Head title="Admin Dashboard" />

    <AppLayout>
        <div class="w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Welcome Header -->
            <div class="mb-8">
                <h1 class="mb-2 text-4xl font-bold text-gray-900">Admin Dashboard</h1>
                <p class="text-gray-600">Welcome to your administration center</p>
            </div>

            <!-- Quick Stats Grid -->
            <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div v-for="(stat, index) in adminStats" :key="index">
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-sm font-medium text-gray-700">{{ stat.title }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center justify-between">
                                <div class="text-3xl font-bold text-gray-900">{{ stat.value }}</div>
                                <component :is="stat.icon" :class="['h-8 w-8', getColorClass(stat.color)]" />
                            </div>
                            <p class="mt-2 text-xs text-gray-500">{{ stat.description }}</p>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Quick Actions -->
                <Card class="lg:col-span-1">
                    <CardHeader>
                        <CardTitle>Quick Actions</CardTitle>
                        <CardDescription>Common administrative tasks</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <Link :href="route('users.create')" as="button" class="w-full">
                            <Button variant="outline" class="w-full justify-start">
                                <Users class="mr-2 h-4 w-4" />
                                Add Admin User
                            </Button>
                        </Link>
                        <Link :href="route('notifications.index')" as="button" class="w-full">
                            <Button variant="outline" class="w-full justify-start">
                                <FileText class="mr-2 h-4 w-4" />
                                Manage Notifications
                            </Button>
                        </Link>
                        <Link :href="route('users.index')" as="button" class="w-full">
                            <Button variant="outline" class="w-full justify-start">
                                <Users class="mr-2 h-4 w-4" />
                                View All Admins
                            </Button>
                        </Link>
                        <Link :href="route('student-fees.index')" as="button" class="w-full">
                            <Button variant="outline" class="w-full justify-start">
                                <Users class="mr-2 h-4 w-4" />
                                View Students
                            </Button>
                        </Link>
                        <!-- Fee Management link removed (Fee Management disabled) -->
                    </CardContent>
                </Card>

                <!-- System Status & Admin Information -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- System Status -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <CheckCircle2 class="h-5 w-5 text-green-500" />
                                System Status
                            </CardTitle>
                            <CardDescription>Real-time system health</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-semibold text-green-900">All Systems Operational</h4>
                                        <p class="mt-1 text-sm text-green-700">All services are running normally</p>
                                    </div>
                                    <CheckCircle2 class="h-8 w-8 text-green-500" />
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                <div class="rounded border border-gray-200 bg-gray-50 p-3">
                                    <p class="text-xs font-medium text-gray-600">Database</p>
                                    <p class="mt-1 text-sm font-semibold text-green-600">✓ Online</p>
                                </div>
                                <div class="rounded border border-gray-200 bg-gray-50 p-3">
                                    <p class="text-xs font-medium text-gray-600">API</p>
                                    <p class="mt-1 text-sm font-semibold text-green-600">✓ Online</p>
                                </div>
                                <div class="rounded border border-gray-200 bg-gray-50 p-3">
                                    <p class="text-xs font-medium text-gray-600">Auth</p>
                                    <p class="mt-1 text-sm font-semibold text-green-600">✓ Online</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Admin Roles Distribution -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Admin Roles</CardTitle>
                            <CardDescription>Current admin distribution</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700">Active Admins</span>
                                    <span class="font-semibold text-gray-900">{{ props.stats?.activeAdmins || 0 }}</span>
                                </div>
                                <div class="h-2 w-full rounded-full bg-gray-200">
                                    <div
                                        class="h-2 rounded-full bg-green-500"
                                        :style="{
                                            width: props.stats?.activeAdmins
                                                ? (props.stats.activeAdmins / Math.max(props.stats.totalAdmins, 1)) * 100 + '%'
                                                : '0%',
                                        }"
                                    ></div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700">Inactive Admins</span>
                                    <span class="font-semibold text-gray-900">{{ props.stats?.inactiveAdmins || 0 }}</span>
                                </div>
                                <div class="h-2 w-full rounded-full bg-gray-200">
                                    <div
                                        class="h-2 rounded-full bg-red-500"
                                        :style="{
                                            width: props.stats?.inactiveAdmins
                                                ? (props.stats.inactiveAdmins / Math.max(props.stats.totalAdmins, 1)) * 100 + '%'
                                                : '0%',
                                        }"
                                    ></div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Recent Notifications -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0">
                    <div>
                        <CardTitle>Recent Notifications</CardTitle>
                        <CardDescription>Latest notifications sent to users</CardDescription>
                    </div>
                    <Link :href="route('notifications.index')">
                        <Button variant="outline" size="sm">View All</Button>
                    </Link>
                </CardHeader>
                <CardContent>
                    <div v-if="!props.stats?.recentNotifications?.length" class="py-8 text-center">
                        <FileText class="mx-auto mb-4 h-12 w-12 text-gray-300" />
                        <p class="text-gray-500">No notifications yet</p>
                        <p class="mt-1 text-sm text-gray-400">Create one to get started</p>
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="notification in props.stats?.recentNotifications?.slice(0, 5)"
                            :key="notification.id"
                            class="rounded-lg border p-4 transition hover:bg-gray-50"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-900">{{ notification.title }}</h4>
                                    <p class="mt-1 text-sm text-gray-600">
                                        To: <span class="font-medium capitalize">{{ notification.targetRole }}</span>
                                    </p>
                                </div>
                                <span class="rounded bg-blue-100 px-2 py-1 text-xs text-blue-700">{{
                                    new Date(notification.createdAt).toLocaleDateString()
                                }}</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
