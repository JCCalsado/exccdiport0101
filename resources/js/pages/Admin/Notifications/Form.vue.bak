<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationPreview from '@/components/NotificationPreview.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckSquare, Square, ToggleLeft, ToggleRight, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Student {
    id: number;
    name: string;
    email: string;
}

interface PaymentTerm {
    id: number;
    term_name: string;
    term_order: number;
}

interface Props {
    notification?: {
        id: number;
        title: string;
        message: string;
        type?: string;
        target_role: string;
        start_date: string;
        end_date: string;
        due_date?: string | null;
        payment_term_id?: number | null;
        user_id?: number | null;
        user_ids?: number[] | null;
        is_active: boolean;
        term_ids?: number[] | null;
        target_term_name?: string | null;
        trigger_days_before_due?: number | null;
    };
    students?: Student[];
    paymentTerms?: PaymentTerm[];
}

const props = withDefaults(defineProps<Props>(), {
    notification: undefined,
    students: () => [],
    paymentTerms: () => [],
});

const isEditing = computed(() => !!props.notification?.id);

type StudentMode = 'all' | 'single' | 'multi';

const detectInitialStudentMode = (): StudentMode => {
    if (props.notification?.user_ids?.length) return 'multi';
    if (props.notification?.user_id) return 'single';
    return 'all';
};

const studentMode = ref<StudentMode>(detectInitialStudentMode());
const searchQuery  = ref('');

const termSelectionMode = ref<'none' | 'by_name' | 'by_id'>(
    props.notification?.target_term_name
        ? 'by_name'
        : props.notification?.term_ids?.length
          ? 'by_id'
          : 'none',
);

const formatDateForInput = (dateString: string | undefined | null): string => {
    if (!dateString) return '';
    return dateString.split('T')[0];
};

const form = useForm({
    title:                   props.notification?.title || '',
    message:                 props.notification?.message || '',
    type:                    props.notification?.type || 'general',
    target_role:             props.notification?.target_role || 'student',
    start_date:              formatDateForInput(props.notification?.start_date),
    end_date:                formatDateForInput(props.notification?.end_date),
    due_date:                formatDateForInput(props.notification?.due_date),
    payment_term_id:         props.notification?.payment_term_id || null,
    user_id:                 props.notification?.user_id || null,
    user_ids:                (props.notification?.user_ids ?? []) as number[],
    is_active:               props.notification?.is_active !== false,
    term_ids:                props.notification?.term_ids || [],
    target_term_name:        props.notification?.target_term_name || '',
    trigger_days_before_due: props.notification?.trigger_days_before_due || null,
});

// Reset student selection when target_role changes away from 'student'
watch(() => form.target_role, (newRole) => {
    if (newRole !== 'student') {
        studentMode.value = 'all';
        form.user_id  = null;
        form.user_ids = [];
    }
});

// Clear due_date when type changes away from payment_due
watch(() => form.type, (newType) => {
    if (newType !== 'payment_due') {
        form.due_date        = '';
        form.payment_term_id = null;
    }
});

// When studentMode changes, reset the irrelevant fields
watch(studentMode, (mode) => {
    if (mode === 'all') {
        form.user_id  = null;
        form.user_ids = [];
    } else if (mode === 'single') {
        form.user_ids = [];
    } else {
        form.user_id = null;
    }
    searchQuery.value = '';
});

const submit = () => {
    if (termSelectionMode.value === 'none') {
        form.term_ids                = [];
        form.target_term_name        = '';
        form.trigger_days_before_due = null;
    } else if (termSelectionMode.value === 'by_name') {
        form.term_ids = [];
    } else {
        form.target_term_name = '';
    }

    if (studentMode.value !== 'single') form.user_id  = null;
    if (studentMode.value !== 'multi')  form.user_ids = [];

    if (isEditing.value && props.notification?.id) {
        form.put(route('admin.notifications.update', props.notification.id));
    } else {
        form.post(route('admin.notifications.store'));
    }
};

// ── Computed helpers ──────────────────────────────────────────────────────

const filteredStudents = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.students;
    return props.students.filter(
        (s) => s.name.toLowerCase().includes(q) || s.email.toLowerCase().includes(q),
    );
});

const selectedStudent = computed(() =>
    form.user_id ? props.students.find((s) => s.id === form.user_id) : undefined,
);

const selectedStudents = computed(() =>
    props.students.filter((s) => form.user_ids.includes(s.id)),
);

const isStudentSelected = (id: number) => form.user_ids.includes(id);

const toggleStudent = (id: number) => {
    const idx = form.user_ids.indexOf(id);
    if (idx === -1) {
        form.user_ids = [...form.user_ids, id];
    } else {
        form.user_ids = form.user_ids.filter((v) => v !== id);
    }
};

const removeStudentFromMulti = (id: number) => {
    form.user_ids = form.user_ids.filter((v) => v !== id);
};

const selectAllFiltered = () => {
    const ids    = filteredStudents.value.map((s) => s.id);
    const merged = Array.from(new Set([...form.user_ids, ...ids]));
    form.user_ids = merged;
};

const clearAllStudents = () => {
    form.user_ids = [];
};

// ── Character counter ─────────────────────────────────────────────────────
const MESSAGE_MAX   = 2000;
const charsLeft     = computed(() => MESSAGE_MAX - (form.message?.length ?? 0));
const charsLeftClass = computed(() => {
    if (charsLeft.value < 0)   return 'text-red-600 font-semibold';
    if (charsLeft.value < 200) return 'text-amber-600';
    return 'text-gray-400';
});

// ── Misc ──────────────────────────────────────────────────────────────────

const roleOptions = [
    { value: 'student',    label: 'All Students' },
    { value: 'accounting', label: 'Accounting Staff' },
    { value: 'admin',      label: 'Admins' },
    { value: 'all',        label: 'Everyone' },
];

const typeOptions = [
    { value: 'general',          label: '📢 General Notification' },
    { value: 'payment_due',      label: '💳 Payment Due Reminder' },
    { value: 'payment_approved', label: '✅ Payment Approved' },
    { value: 'payment_rejected', label: '❌ Payment Rejected' },
];

const messages: Record<string, string> = {
    student:    'This notification will be sent to all students.',
    accounting: 'This notification will be sent to accounting staff.',
    admin:      'This notification will be sent to admin users.',
    all:        'This notification will be sent to all users in the system.',
};

const breadcrumbs = [
    { title: 'Admin', href: route('admin.dashboard') },
    { title: 'Notifications', href: route('admin.notifications.index') },
    {
        title: isEditing.value
            ? `Edit: ${props.notification?.title ?? 'Notification'}`
            : 'Create Notification',
        href: isEditing.value
            ? route('admin.notifications.edit', props.notification?.id)
            : route('admin.notifications.create'),
    },
];
</script>

<template>
    <Head :title="isEditing ? 'Edit Notification' : 'Create Notification'" />

    <AppLayout>
        <div class="w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <div class="max-w-7xl space-y-6">
                <!-- Header -->
                <div class="mb-8 flex items-start justify-between">
                    <div class="flex items-center gap-4">
                        <Link :href="route('admin.notifications.index')">
                            <Button variant="ghost" size="icon" class="h-10 w-10">
                                <ArrowLeft class="h-5 w-5" />
                            </Button>
                        </Link>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">
                                {{ isEditing ? 'Edit Notification' : 'Create Notification' }}
                            </h1>
                            <p class="mt-1 text-sm text-gray-600">
                                {{
                                    isEditing
                                        ? 'Update notification details'
                                        : 'Set up a new notification for students to see on their dashboard'
                                }}
                            </p>
                        </div>
                    </div>
                    <div v-if="isEditing" class="text-right">
                        <div
                            class="inline-flex items-center gap-2 rounded-lg px-4 py-2"
                            :class="form.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                        >
                            <span class="text-sm font-medium">{{
                                form.is_active ? '✓ Active' : '○ Inactive'
                            }}</span>
                        </div>
                    </div>
                </div>

                <!-- Main Form Grid -->
                <div class="grid grid-cols-3 gap-8">
                    <!-- Left Column: Form (2/3 width) -->
                    <div class="col-span-2 space-y-6">
                        <!-- Notification Content -->
                        <Card>
                            <CardHeader>
                                <CardTitle>📝 Notification Content</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form class="space-y-6">
                                    <!-- Title -->
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-gray-900"
                                            >Notification Title *</label
                                        >
                                        <input
                                            v-model="form.title"
                                            type="text"
                                            placeholder="e.g., Midterm Payment Due Reminder"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                            required
                                        />
                                        <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">
                                            {{ form.errors.title }}
                                        </p>
                                    </div>

                                    <!-- Message -->
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-gray-900"
                                            >Message Content</label
                                        >
                                        <textarea
                                            v-model="form.message"
                                            placeholder="Enter your notification message."
                                            class="h-40 w-full resize-none rounded-lg border border-gray-300 px-4 py-3 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                            :class="{ 'border-red-400': charsLeft < 0 }"
                                        />
                                        <!-- Character counter -->
                                        <div class="mt-1 flex items-center justify-between">
                                            <p v-if="form.errors.message" class="text-sm text-red-600">
                                                {{ form.errors.message }}
                                            </p>
                                            <p v-else class="text-xs text-gray-400">
                                                {{ form.message?.length ?? 0 }} characters
                                            </p>
                                            <p :class="['text-xs', charsLeftClass]">
                                                {{ charsLeft >= 0 ? `${charsLeft} remaining` : `${Math.abs(charsLeft)} over limit` }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Notification Type -->
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-gray-900"
                                            >Notification Type</label
                                        >
                                        <select
                                            v-model="form.type"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                        >
                                            <option
                                                v-for="option in typeOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.type" class="mt-1 text-sm text-red-600">
                                            {{ form.errors.type }}
                                        </p>
                                    </div>

                                    <!-- Payment Due Date -->
                                    <div
                                        v-if="form.type === 'payment_due'"
                                        class="rounded-lg border border-amber-200 bg-amber-50 p-4"
                                    >
                                        <h4 class="mb-3 text-sm font-semibold text-amber-900">
                                            💳 Payment Due Date
                                        </h4>
                                        <p class="mb-3 text-xs text-amber-700">
                                            Set the actual payment deadline. Displayed as a colour-coded chip
                                            on the student's notification. Also synced to the matching payment
                                            term record.
                                        </p>
                                        <input
                                            v-model="form.due_date"
                                            type="date"
                                            class="w-full rounded-lg border border-amber-300 bg-white px-4 py-3 focus:border-transparent focus:ring-2 focus:ring-amber-500"
                                        />
                                        <p v-if="form.errors.due_date" class="mt-1 text-sm text-red-600">
                                            {{ form.errors.due_date }}
                                        </p>
                                    </div>

                                    <!-- Date Range -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-gray-900"
                                                >Start Date *</label
                                            >
                                            <input
                                                v-model="form.start_date"
                                                type="date"
                                                class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                                required
                                            />
                                            <p class="mt-1 text-xs text-gray-500">
                                                When this notification becomes visible
                                            </p>
                                            <p
                                                v-if="form.errors.start_date"
                                                class="mt-1 text-sm text-red-600"
                                            >
                                                {{ form.errors.start_date }}
                                            </p>
                                        </div>
                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-gray-900"
                                                >End Date (Optional)</label
                                            >
                                            <input
                                                v-model="form.end_date"
                                                type="date"
                                                class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                            />
                                            <p class="mt-1 text-xs text-gray-500">
                                                Leave empty for ongoing notifications
                                            </p>
                                            <p
                                                v-if="form.errors.end_date"
                                                class="mt-1 text-sm text-red-600"
                                            >
                                                {{ form.errors.end_date }}
                                            </p>
                                        </div>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        <!-- Target Audience -->
                        <Card>
                            <CardHeader>
                                <CardTitle>👥 Target Audience</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-6">
                                    <!-- Role -->
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-gray-900"
                                            >Who should see this? *</label
                                        >
                                        <select
                                            v-model="form.target_role"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                            required
                                        >
                                            <option value="">-- Select Audience --</option>
                                            <option
                                                v-for="option in roleOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <p
                                            class="mt-2 rounded border border-blue-200 bg-blue-50 p-3 text-xs text-gray-500"
                                        >
                                            {{ messages[form.target_role] || 'Select an audience' }}
                                        </p>
                                        <p
                                            v-if="form.errors.target_role"
                                            class="mt-1 text-sm text-red-600"
                                        >
                                            {{ form.errors.target_role }}
                                        </p>
                                    </div>

                                    <!-- Student Targeting Mode -->
                                    <div v-if="form.target_role === 'student'" class="space-y-4">
                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-gray-900"
                                                >Student targeting</label
                                            >
                                            <div class="flex gap-3">
                                                <button
                                                    type="button"
                                                    @click="studentMode = 'all'"
                                                    class="flex-1 rounded-lg border px-4 py-2.5 text-sm font-medium transition"
                                                    :class="
                                                        studentMode === 'all'
                                                            ? 'border-blue-500 bg-blue-50 text-blue-700'
                                                            : 'border-gray-300 text-gray-600 hover:bg-gray-50'
                                                    "
                                                >
                                                    🌐 All Students
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="studentMode = 'multi'"
                                                    class="flex-1 rounded-lg border px-4 py-2.5 text-sm font-medium transition"
                                                    :class="
                                                        studentMode === 'multi'
                                                            ? 'border-blue-500 bg-blue-50 text-blue-700'
                                                            : 'border-gray-300 text-gray-600 hover:bg-gray-50'
                                                    "
                                                >
                                                    👥 Specific Students
                                                    <span v-if="selectedStudents.length" class="ml-1 inline-flex items-center justify-center rounded-full bg-blue-600 px-1.5 text-xs text-white">
                                                        {{ selectedStudents.length }}
                                                    </span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Multi-student picker -->
                                        <div v-if="studentMode === 'multi'" class="space-y-3">
                                            <!-- Selected chips -->
                                            <div
                                                v-if="selectedStudents.length"
                                                class="flex flex-wrap gap-2 rounded-lg border border-blue-200 bg-blue-50 p-3"
                                            >
                                                <span
                                                    v-for="s in selectedStudents"
                                                    :key="s.id"
                                                    class="inline-flex items-center gap-1.5 rounded-full bg-blue-600 px-3 py-1 text-xs font-medium text-white"
                                                >
                                                    {{ s.name }}
                                                    <button
                                                        type="button"
                                                        @click="removeStudentFromMulti(s.id)"
                                                        class="rounded-full hover:bg-blue-700"
                                                    >
                                                        <X class="h-3 w-3" />
                                                    </button>
                                                </span>
                                            </div>
                                            <p v-else class="text-xs text-gray-500">
                                                No students selected yet. Use the list below to select.
                                            </p>

                                            <!-- Search -->
                                            <input
                                                v-model="searchQuery"
                                                type="text"
                                                placeholder="Search by name or email…"
                                                class="w-full rounded-lg border border-gray-300 px-4 py-2.5 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                            />

                                            <!-- Bulk actions -->
                                            <div class="flex gap-2">
                                                <button
                                                    type="button"
                                                    @click="selectAllFiltered"
                                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                                >
                                                    Select all {{ filteredStudents.length > 0 ? `(${filteredStudents.length})` : '' }}
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="clearAllStudents"
                                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                                                >
                                                    Clear all
                                                </button>
                                            </div>

                                            <!-- Student list -->
                                            <div class="max-h-72 overflow-y-auto rounded-lg border border-gray-300">
                                                <div
                                                    v-if="filteredStudents.length === 0"
                                                    class="p-4 text-center text-sm text-gray-500"
                                                >
                                                    No students found.
                                                </div>
                                                <div
                                                    v-for="student in filteredStudents"
                                                    :key="student.id"
                                                    @click="toggleStudent(student.id)"
                                                    class="flex cursor-pointer items-center gap-3 border-b border-gray-100 p-3 last:border-b-0 hover:bg-gray-50 transition-colors"
                                                    :class="isStudentSelected(student.id) ? 'bg-blue-50' : ''"
                                                >
                                                    <component
                                                        :is="isStudentSelected(student.id) ? CheckSquare : Square"
                                                        class="h-4 w-4 shrink-0"
                                                        :class="isStudentSelected(student.id) ? 'text-blue-600' : 'text-gray-400'"
                                                    />
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-medium text-gray-900">
                                                            {{ student.name }}
                                                        </p>
                                                        <p class="truncate text-xs text-gray-500">
                                                            {{ student.email }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <p
                                                v-if="form.errors['user_ids']"
                                                class="mt-1 text-sm text-red-600"
                                            >
                                                {{ form.errors['user_ids'] }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Term-Based Filtering -->
                        <Card v-if="form.target_role === 'student' && studentMode === 'all'">
                            <CardHeader>
                                <CardTitle>📅 Term-Based Filtering (Optional)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-5">
                                    <p class="text-xs text-gray-600">
                                        Limit this notification to students who have a specific payment term.
                                        <strong>Example:</strong> Setting "Midterm" means only students with a
                                        Midterm payment term on their assessment will see this notification.
                                    </p>

                                    <!-- Selection Mode -->
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-gray-900"
                                            >Term filter type</label
                                        >
                                        <div class="space-y-2">
                                            <label class="flex cursor-pointer items-center gap-3">
                                                <input
                                                    v-model="termSelectionMode"
                                                    type="radio"
                                                    value="none"
                                                    class="h-4 w-4"
                                                />
                                                <span class="text-sm text-gray-700"
                                                    >No filter — show to all matching students</span
                                                >
                                            </label>
                                            <label class="flex cursor-pointer items-center gap-3">
                                                <input
                                                    v-model="termSelectionMode"
                                                    type="radio"
                                                    value="by_name"
                                                    class="h-4 w-4"
                                                />
                                                <span class="text-sm text-gray-700"
                                                    >By term name (e.g., "Midterm", "Prelim")</span
                                                >
                                            </label>
                                            <label class="flex cursor-pointer items-center gap-3">
                                                <input
                                                    v-model="termSelectionMode"
                                                    type="radio"
                                                    value="by_id"
                                                    class="h-4 w-4"
                                                />
                                                <span class="text-sm text-gray-700"
                                                    >By specific payment term IDs</span
                                                >
                                            </label>
                                        </div>
                                    </div>

                                    <!-- By Term Name -->
                                    <div v-if="termSelectionMode === 'by_name'">
                                        <label class="mb-2 block text-sm font-semibold text-gray-900"
                                            >Which term? *</label
                                        >
                                        <select
                                            v-model="form.target_term_name"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                        >
                                            <option value="">-- Select a Term --</option>
                                            <option value="Upon Registration">Upon Registration</option>
                                            <option value="Prelim">Prelim</option>
                                            <option value="Midterm">Midterm</option>
                                            <option value="Semi-Final">Semi-Final</option>
                                            <option value="Final">Final</option>
                                        </select>
                                        <p
                                            v-if="form.errors.target_term_name"
                                            class="mt-1 text-sm text-red-600"
                                        >
                                            {{ form.errors.target_term_name }}
                                        </p>
                                    </div>

                                    <!-- By Specific IDs -->
                                    <div v-if="termSelectionMode === 'by_id'">
                                        <label class="mb-2 block text-sm font-semibold text-gray-900"
                                            >Select Payment Terms *</label
                                        >
                                        <div
                                            class="max-h-48 space-y-2 overflow-y-auto rounded-lg border border-gray-300 p-4"
                                        >
                                            <div
                                                v-if="paymentTerms.length === 0"
                                                class="text-sm text-gray-500"
                                            >
                                                No payment terms available
                                            </div>
                                            <label
                                                v-for="term in paymentTerms"
                                                :key="term.id"
                                                class="flex cursor-pointer items-center gap-3"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :value="term.id"
                                                    v-model="form.term_ids"
                                                    class="h-4 w-4 rounded border-gray-300 text-blue-600"
                                                />
                                                <span class="text-sm text-gray-700">{{
                                                    term.term_name
                                                }}</span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Trigger Days -->
                                    <div v-if="termSelectionMode !== 'none'">
                                        <label class="mb-2 block text-sm font-semibold text-gray-900">
                                            Show only N days before due date (Optional)
                                        </label>
                                        <input
                                            v-model.number="form.trigger_days_before_due"
                                            type="number"
                                            placeholder="e.g., 7 — show 7 days before due date"
                                            min="0"
                                            max="90"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Right Column: Sidebar -->
                    <div class="col-span-1 space-y-6">
                        <!-- Activation Toggle -->
                        <Card
                            class="border-2"
                            :class="form.is_active ? 'border-green-200 bg-green-50' : 'border-gray-200'"
                        >
                            <CardHeader>
                                <CardTitle class="text-sm">Activation Status</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-4">
                                    <button
                                        type="button"
                                        @click="form.is_active = !form.is_active"
                                        class="flex w-full items-center justify-center gap-3 rounded-lg px-4 py-4 transition"
                                        :class="
                                            form.is_active
                                                ? 'bg-green-500 text-white hover:bg-green-600'
                                                : 'bg-gray-300 text-white hover:bg-gray-400'
                                        "
                                    >
                                        <component
                                            :is="form.is_active ? ToggleRight : ToggleLeft"
                                            class="h-6 w-6"
                                        />
                                        <span class="font-semibold">
                                            {{
                                                form.is_active
                                                    ? 'Notification Active'
                                                    : 'Notification Inactive'
                                            }}
                                        </span>
                                    </button>
                                    <div
                                        class="rounded-lg p-3"
                                        :class="
                                            form.is_active
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-gray-100 text-gray-800'
                                        "
                                    >
                                        <p class="text-xs font-medium">
                                            <span v-if="form.is_active"
                                                >✓ Students will see this notification</span
                                            >
                                            <span v-else>○ Students will NOT see this notification</span>
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Recipient Summary -->
                        <Card class="border border-gray-200">
                            <CardHeader>
                                <CardTitle class="text-sm">📬 Email Recipients</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="text-xs text-gray-700 space-y-1">
                                    <template v-if="studentMode === 'multi' && selectedStudents.length">
                                        <p class="font-semibold text-blue-700">
                                            {{ selectedStudents.length }} specific student{{
                                                selectedStudents.length !== 1 ? 's' : ''
                                            }}
                                        </p>
                                        <p
                                            v-for="s in selectedStudents.slice(0, 5)"
                                            :key="s.id"
                                            class="truncate text-gray-500"
                                        >
                                            {{ s.email }}
                                        </p>
                                        <p
                                            v-if="selectedStudents.length > 5"
                                            class="text-gray-400"
                                        >
                                            +{{ selectedStudents.length - 5 }} more…
                                        </p>
                                    </template>
                                    <template v-else-if="form.target_role === 'all'">
                                        <p>All active users will receive an email.</p>
                                    </template>
                                    <template v-else>
                                        <p>
                                            All active
                                            <strong>{{ form.target_role }}</strong> users will receive
                                            an email.
                                        </p>
                                    </template>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- ✅ ENHANCED: Live preview now receives type + due_date -->
                        <NotificationPreview
                            :title="form.title"
                            :message="form.message"
                            :type="form.type"
                            :start-date="form.start_date"
                            :end-date="form.end_date"
                            :due-date="form.due_date"
                            :target-role="form.target_role"
                            :selected-student-email="selectedStudents[0]?.email ?? selectedStudent?.email"
                        />

                        <!-- Tips -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-sm">💡 Tips</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul class="space-y-2 text-xs text-gray-700">
                                    <li>
                                        ✓ Use <strong>Specific Students</strong> to notify a handful of
                                        students individually
                                    </li>
                                    <li>
                                        ✓ For "Midterm payment due" — set type to
                                        <strong>Payment Due</strong> and add a due date
                                    </li>
                                    <li>
                                        ✓ Remember to <strong>Activate</strong> before saving
                                    </li>
                                    <li>✓ Emails are queued and sent in the background</li>
                                </ul>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-8 flex items-center justify-end gap-3 border-t border-gray-300 pt-6">
                    <!-- Save status indicator -->
                    <span v-if="form.processing" class="text-sm text-gray-500 animate-pulse">
                        Saving…
                    </span>
                    <span v-else-if="form.recentlySuccessful" class="text-sm text-green-600 font-medium">
                        ✓ Saved!
                    </span>
                    <Link :href="route('admin.notifications.index')">
                        <Button type="button" variant="outline" class="px-6">Cancel</Button>
                    </Link>
                    <Button
                        type="submit"
                        :disabled="form.processing || charsLeft < 0"
                        @click="submit"
                        class="bg-blue-600 px-8 text-white hover:bg-blue-700 disabled:opacity-60"
                    >
                        <span v-if="form.processing">Saving…</span>
                        <span v-else>{{
                            isEditing ? 'Update Notification' : 'Create Notification'
                        }}</span>
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>