<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Bell, Calendar, CalendarClock, Edit2, Plus, Trash2, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Notification {
    id: number;
    title: string;
    message: string;
    type?: string;
    target_role: string;
    start_date: string;
    end_date?: string | null;
    due_date?: string | null;
    payment_term_id?: number | null;
    is_active: boolean;
    is_complete: boolean;
    target_term_name?: string | null;
    term_ids?: number[] | null;
    trigger_days_before_due?: number | null;
    user_id?: number | null;
    user_ids?: number[] | null;
    dismissed_at?: string | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    notifications: Notification[];
}

const props = withDefaults(defineProps<Props>(), {
    notifications: () => [],
});

// ── Role detection ─────────────────────────────────────────────────────────
const page = usePage();
const isAccounting = computed(() => (page.props.auth as any)?.user?.role === 'accounting');

// ── Breadcrumbs adapt to role so back-link always works ────────────────────
const breadcrumbs = computed(() => {
    if (isAccounting.value) {
        return [
            { title: 'Accounting', href: route('accounting.dashboard') },
            { title: 'Notifications', href: route('accounting.notifications.index') },
        ];
    }
    return [
        { title: 'Admin', href: route('admin.dashboard') },
        { title: 'Notifications', href: route('admin.notifications.index') },
    ];
});

// ── Filters ────────────────────────────────────────────────────────────────
const searchQuery = ref('');
type FilterTab = 'all' | 'active' | 'inactive' | 'payment_due';
const activeTab   = ref<FilterTab>('all');

// ── Delete (Accounting only) ───────────────────────────────────────────────
const pendingDeleteId = ref<number | null>(null);

const requestDelete = (id: number) => { pendingDeleteId.value = id; };
const cancelDelete  = ()          => { pendingDeleteId.value = null; };

const confirmDelete = (id: number) => {
    pendingDeleteId.value = null;
    router.delete(route('accounting.notifications.destroy', id));
};

// ── System-generated heuristic ────────────────────────────────────────────
const SYSTEM_TYPES = new Set(['payment_approved', 'payment_rejected']);

const isSystemGenerated = (n: Notification): boolean =>
    SYSTEM_TYPES.has(n.type ?? '') &&
    (n.message?.startsWith('Your payment') || n.message?.startsWith('Payment of') || !n.message);

// ── Date helpers ───────────────────────────────────────────────────────────
const todayStr = new Date().toLocaleDateString('en-CA');

const isActive = (n: Notification) => {
    if (!n.is_active || n.is_complete) return false;
    const start = n.start_date?.split('T')[0] ?? '';
    const end   = n.end_date?.split('T')[0]   ?? null;
    return start <= todayStr && (end === null || end >= todayStr);
};

// ── Filtered list ──────────────────────────────────────────────────────────
const filtered = computed(() => {
    let list = props.notifications;
    if (activeTab.value === 'active')       list = list.filter((n) => isActive(n) && !isSystemGenerated(n));
    else if (activeTab.value === 'inactive') list = list.filter((n) => !isActive(n) && !isSystemGenerated(n));
    else if (activeTab.value === 'payment_due') list = list.filter((n) => n.type === 'payment_due');
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(
            (n) =>
                n.title.toLowerCase().includes(q) ||
                n.message?.toLowerCase().includes(q) ||
                (n.target_term_name ?? '').toLowerCase().includes(q),
        );
    }
    return list;
});

const adminCreated    = computed(() => filtered.value.filter((n) => !isSystemGenerated(n)));
const systemGenerated = computed(() => filtered.value.filter((n) => isSystemGenerated(n)));

const tabCounts = computed(() => ({
    all:         props.notifications.filter((n) => !isSystemGenerated(n)).length,
    active:      props.notifications.filter((n) => isActive(n) && !isSystemGenerated(n)).length,
    inactive:    props.notifications.filter((n) => !isActive(n) && !isSystemGenerated(n)).length,
    payment_due: props.notifications.filter((n) => n.type === 'payment_due').length,
}));

// ── Display helpers ────────────────────────────────────────────────────────
const getRoleColor = (role: string) => {
    const colors: Record<string, string> = {
        student:    'bg-blue-100 text-blue-800',
        accounting: 'bg-purple-100 text-purple-800',
        admin:      'bg-indigo-100 text-indigo-800',
        all:        'bg-gray-100 text-gray-800',
    };
    return colors[role] || 'bg-gray-100 text-gray-800';
};

const getTypeLabel = (type?: string) => {
    const labels: Record<string, string> = {
        general:          '📢 General',
        payment_due:      '💳 Payment Due',
        payment_approved: '✅ Approved',
        payment_rejected: '❌ Rejected',
    };
    return labels[type || 'general'] || 'General';
};

const getTypeColor = (type?: string) => {
    const colors: Record<string, string> = {
        general:          'bg-blue-100 text-blue-800',
        payment_due:      'bg-amber-100 text-amber-800',
        payment_approved: 'bg-emerald-100 text-emerald-800',
        payment_rejected: 'bg-red-100 text-red-800',
    };
    return colors[type || 'general'] || 'bg-gray-100 text-gray-800';
};

const getDueDateChipClass = (dueDateStr: string | null | undefined): string => {
    if (!dueDateStr) return 'bg-gray-100 text-gray-700';
    const diffDays = Math.ceil((new Date(dueDateStr).getTime() - Date.now()) / 86_400_000);
    if (diffDays <= 7)  return 'bg-red-100 text-red-700 ring-1 ring-red-200';
    if (diffDays <= 14) return 'bg-amber-100 text-amber-700 ring-1 ring-amber-200';
    return 'bg-green-100 text-green-700 ring-1 ring-green-200';
};

const formatDueDate = (dueDateStr: string | null | undefined): string => {
    if (!dueDateStr) return '';
    return new Date(dueDateStr).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
    });
};

const formatAdminDate = (dateStr: string | null | undefined): string => {
    if (!dateStr) return '';
    const d = dateStr.split('T')[0];
    return new Date(d + 'T12:00:00').toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
};

const recipientLabel = (n: Notification): string => {
    if (n.user_ids?.length) return `👥 ${n.user_ids.length} specific student${n.user_ids.length !== 1 ? 's' : ''}`;
    if (n.user_id)          return '👤 Personal';
    return '';
};
</script>

<template>
    <Head title="Payment Notifications" />

    <AppLayout>
        <div class="w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="mb-2 text-3xl font-bold text-gray-900">Payment Notifications</h1>
                    <p class="text-gray-600">
                        {{ isAccounting ? 'Create and manage notifications for students' : 'View all system notifications (read-only)' }}
                    </p>
                </div>

                <!--
                    Create button: Accounting only.
                    Admin sees a read-only badge instead.
                -->
                <template v-if="isAccounting">
                    <Link :href="route('accounting.notifications.create')">
                        <Button>
                            <Plus class="mr-2 h-4 w-4" />
                            Create Notification
                        </Button>
                    </Link>
                </template>
                <template v-else>
                    <span class="inline-flex items-center rounded-full bg-purple-100 px-3 py-1 text-xs font-medium text-purple-700">
                        Read-only
                    </span>
                </template>
            </div>

            <!-- Filter tabs + search -->
            <div class="mb-6 space-y-3">
                <div class="flex w-fit gap-1 rounded-xl bg-gray-100 p-1">
                    <button
                        v-for="tab in ([
                            { key: 'all',         label: 'All' },
                            { key: 'active',      label: '● Active' },
                            { key: 'inactive',    label: '○ Inactive' },
                            { key: 'payment_due', label: '💳 Payment Due' },
                        ] as { key: FilterTab; label: string }[])"
                        :key="tab.key"
                        type="button"
                        @click="activeTab = tab.key"
                        :class="[
                            'rounded-lg px-4 py-1.5 text-sm font-medium transition-all',
                            activeTab === tab.key
                                ? 'bg-white shadow text-gray-900'
                                : 'text-gray-500 hover:text-gray-700',
                        ]"
                    >
                        {{ tab.label }}
                        <span class="ml-1.5 rounded-full bg-gray-200 px-1.5 py-0.5 text-xs font-semibold text-gray-700">
                            {{ tabCounts[tab.key] }}
                        </span>
                    </button>
                </div>

                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search by title, message, or term name…"
                    class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                />
            </div>

            <!-- Empty state -->
            <div v-if="filtered.length === 0" class="py-16 text-center">
                <Bell class="mx-auto mb-4 h-12 w-12 text-gray-300" />
                <h3 class="mb-2 text-lg font-semibold text-gray-700">No notifications found</h3>
                <p class="mb-4 text-gray-600">
                    {{
                        searchQuery || activeTab !== 'all'
                            ? 'Try adjusting your search or filter'
                            : isAccounting
                              ? 'Create your first notification to get started'
                              : 'No notifications have been created yet'
                    }}
                </p>
                <Link v-if="isAccounting && !searchQuery && activeTab === 'all'" :href="route('accounting.notifications.create')">
                    <Button variant="outline">
                        <Plus class="mr-2 h-4 w-4" />
                        Create First Notification
                    </Button>
                </Link>
            </div>

            <div v-else class="space-y-10">

                <!-- ── Section 1: Admin-Created Notifications ──────────────── -->
                <section>
                    <div class="mb-4 flex items-center gap-3">
                        <div class="h-px flex-1 bg-gray-200" />
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">
                            📋 Admin-Created Notifications
                        </h2>
                        <div class="h-px flex-1 bg-gray-200" />
                    </div>

                    <div v-if="adminCreated.length === 0" class="py-8 text-center text-sm text-gray-400">
                        No notifications match the current filter.
                    </div>

                    <div v-else class="space-y-4">
                        <Card
                            v-for="notification in adminCreated"
                            :key="notification.id"
                            class="transition-all duration-200"
                        >
                            <CardContent class="pt-6">
                                <!-- Title row + badges -->
                                <div class="mb-3 flex items-start justify-between">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-lg font-semibold text-gray-900">
                                            {{ notification.title }}
                                        </h3>

                                        <span
                                            v-if="notification.is_complete"
                                            class="inline-flex items-center rounded-full bg-gray-200 px-3 py-1 text-xs font-medium text-gray-600"
                                        >✓ Completed</span>
                                        <span
                                            v-else-if="isActive(notification)"
                                            class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800"
                                        >● Active</span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600"
                                        >○ Inactive</span>

                                        <span
                                            v-if="recipientLabel(notification)"
                                            class="inline-flex items-center rounded-full bg-purple-100 px-3 py-1 text-xs font-medium text-purple-800"
                                        >
                                            {{ recipientLabel(notification) }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Message -->
                                <p
                                    v-if="notification.message"
                                    class="mb-4 text-sm leading-relaxed text-gray-700"
                                >
                                    {{ notification.message }}
                                </p>

                                <!-- Metadata chips -->
                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-full px-2.5 py-1 font-medium',
                                            getRoleColor(notification.target_role),
                                        ]"
                                    >
                                        <Users class="h-3 w-3" />
                                        {{
                                            notification.target_role.charAt(0).toUpperCase() +
                                            notification.target_role.slice(1)
                                        }}
                                    </span>

                                    <span
                                        v-if="notification.type"
                                        :class="['rounded-full px-2.5 py-1 font-medium', getTypeColor(notification.type)]"
                                    >
                                        {{ getTypeLabel(notification.type) }}
                                    </span>

                                    <span
                                        v-if="notification.target_term_name"
                                        class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-1 font-medium text-indigo-800"
                                    >
                                        🎓 {{ notification.target_term_name }} only
                                    </span>
                                    <span
                                        v-else-if="notification.term_ids && notification.term_ids.length > 0"
                                        class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-1 font-medium text-indigo-800"
                                    >
                                        🎓 {{ notification.term_ids.length }} specific term(s)
                                    </span>

                                    <span
                                        v-if="notification.due_date"
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-full px-2.5 py-1 font-medium',
                                            getDueDateChipClass(notification.due_date),
                                        ]"
                                    >
                                        <CalendarClock class="h-3 w-3" />
                                        Due: {{ formatDueDate(notification.due_date) }}
                                    </span>

                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-600">
                                        <Calendar class="h-3 w-3" />
                                        {{ formatAdminDate(notification.start_date) }}
                                        <span v-if="notification.end_date">
                                            → {{ formatAdminDate(notification.end_date) }}
                                        </span>
                                        <span v-else>→ ongoing</span>
                                    </span>

                                    <span
                                        v-if="notification.trigger_days_before_due"
                                        class="rounded-full bg-yellow-100 px-2.5 py-1 font-medium text-yellow-800"
                                    >
                                        ⏱ Shows {{ notification.trigger_days_before_due }}d before due
                                    </span>

                                    <span class="ml-auto text-gray-400">
                                        Created {{ formatAdminDate(notification.created_at) }}
                                    </span>
                                </div>

                                <!--
                                    Actions row:
                                    - Accounting: full Edit + Delete
                                    - Admin: no actions (view-only)
                                -->
                                <div v-if="isAccounting" class="mt-4 flex justify-end gap-2 border-t pt-4">
                                    <template v-if="pendingDeleteId === notification.id">
                                        <span class="mr-auto flex items-center text-xs text-gray-600">
                                            Delete this notification?
                                        </span>
                                        <Button variant="outline" size="sm" @click="cancelDelete">
                                            Cancel
                                        </Button>
                                        <Button
                                            size="sm"
                                            class="bg-red-600 text-white hover:bg-red-700"
                                            @click="confirmDelete(notification.id)"
                                        >
                                            <Trash2 class="mr-1 h-4 w-4" />
                                            Yes, Delete
                                        </Button>
                                    </template>

                                    <template v-else>
                                        <Link :href="route('accounting.notifications.edit', notification.id)" as="button">
                                            <Button variant="outline" size="sm">
                                                <Edit2 class="mr-2 h-4 w-4" />
                                                Edit
                                            </Button>
                                        </Link>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="text-red-600 hover:bg-red-50 hover:text-red-700"
                                            @click="requestDelete(notification.id)"
                                        >
                                            <Trash2 class="mr-2 h-4 w-4" />
                                            Delete
                                        </Button>
                                    </template>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <!-- ── Section 2: System-Generated ────────────────────────── -->
                <section v-if="systemGenerated.length > 0">
                    <div class="mb-4 flex items-center gap-3">
                        <div class="h-px flex-1 bg-gray-200" />
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">
                            ⚡ System-Generated Notifications
                        </h2>
                        <div class="h-px flex-1 bg-gray-200" />
                    </div>

                    <p class="mb-4 text-xs text-gray-500">
                        Automatically generated by payment events. They cannot be edited.
                    </p>

                    <div class="space-y-3">
                        <Card
                            v-for="notification in systemGenerated"
                            :key="notification.id"
                            class="border-dashed opacity-90"
                        >
                            <CardContent class="pt-5">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0 flex-1">
                                        <div class="mb-1 flex flex-wrap items-center gap-2">
                                            <span
                                                v-if="isActive(notification)"
                                                class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800"
                                            >● Active</span>
                                            <span
                                                v-else
                                                class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600"
                                            >○ Inactive</span>

                                            <span
                                                v-if="notification.user_id"
                                                class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800"
                                            >👤 Personal</span>

                                            <span
                                                v-if="notification.type"
                                                :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getTypeColor(notification.type)]"
                                            >{{ getTypeLabel(notification.type) }}</span>
                                        </div>

                                        <p class="text-sm font-semibold text-gray-900">
                                            {{ notification.title }}
                                        </p>
                                        <p
                                            v-if="notification.message"
                                            class="mt-1 line-clamp-2 text-xs leading-relaxed text-gray-600"
                                        >
                                            {{ notification.message }}
                                        </p>
                                    </div>

                                    <div class="flex shrink-0 flex-col items-end gap-2">
                                        <span class="text-xs text-gray-400">
                                            {{ formatAdminDate(notification.created_at) }}
                                        </span>

                                        <!-- Delete for system notifications: Accounting only -->
                                        <template v-if="isAccounting">
                                            <template v-if="pendingDeleteId === notification.id">
                                                <div class="flex gap-1">
                                                    <Button variant="outline" size="sm" class="h-7 text-xs" @click="cancelDelete">
                                                        Cancel
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        class="h-7 bg-red-600 text-xs text-white hover:bg-red-700"
                                                        @click="confirmDelete(notification.id)"
                                                    >
                                                        Confirm
                                                    </Button>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-7 text-red-500 hover:text-red-600"
                                                    @click="requestDelete(notification.id)"
                                                >
                                                    <Trash2 class="h-3.5 w-3.5" />
                                                </Button>
                                            </template>
                                        </template>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>