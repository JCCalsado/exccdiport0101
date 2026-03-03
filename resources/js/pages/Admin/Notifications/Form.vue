<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationPreview from '@/components/NotificationPreview.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ToggleLeft, ToggleRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
        user_id?: number | null;
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
const searchQuery = ref('');
const termSelectionMode = ref<'none' | 'by_name' | 'by_id'>(
    props.notification?.target_term_name ? 'by_name' : props.notification?.term_ids ? 'by_id' : 'none',
);

const formatDateForInput = (dateString: string | undefined): string => {
    if (!dateString) return '';
    return dateString.split('T')[0];
};

const form = useForm({
    title: props.notification?.title || '',
    message: props.notification?.message || '',
    type: props.notification?.type || 'general',
    target_role: props.notification?.target_role || 'student',
    start_date: formatDateForInput(props.notification?.start_date),
    end_date: formatDateForInput(props.notification?.end_date),
    user_id: props.notification?.user_id || null,
    is_active: props.notification?.is_active !== false,
    term_ids: props.notification?.term_ids || [],
    target_term_name: props.notification?.target_term_name || '',
    trigger_days_before_due: props.notification?.trigger_days_before_due || null,
});

const submit = () => {
    // Clear term fields if 'none' mode is selected
    if (termSelectionMode.value === 'none') {
        form.term_ids = [];
        form.target_term_name = '';
        form.trigger_days_before_due = null;
    } else if (termSelectionMode.value === 'by_name') {
        form.term_ids = [];
    } else if (termSelectionMode.value === 'by_id') {
        form.target_term_name = '';
    }

    if (isEditing.value && props.notification?.id) {
        form.put(route('notifications.update', props.notification.id));
    } else {
        form.post(route('notifications.store'));
    }
};

const roleOptions = [
    { value: 'student', label: 'All Students' },
    { value: 'accounting', label: 'Accounting Staff' },
    { value: 'admin', label: 'Admins' },
    { value: 'all', label: 'Everyone' },
];

const typeOptions = [
    { value: 'general', label: '📢 General Notification' },
    { value: 'payment_due', label: '💳 Payment Due Reminder' },
    { value: 'payment_approved', label: '✅ Payment Approved' },
    { value: 'payment_rejected', label: '❌ Payment Rejected' },
];

const messages = {
    student: 'This notification will be sent to all students. Use this to remind them about payment dues.',
    accounting: 'This notification will be sent to accounting staff. Use this for accounting-related announcements.',
    admin: 'This notification will be sent to admin users. Use this for administrative announcements.',
    all: 'This notification will be sent to all users in the system.',
};

const filteredStudents = computed(() => {
    if (!searchQuery.value.trim()) return props.students;
    const query = searchQuery.value.toLowerCase();
    return props.students.filter((s) => s.name.toLowerCase().includes(query) || s.email.toLowerCase().includes(query));
});

const selectedStudent = computed(() => {
    return props.students.find((s) => s.id === form.user_id);
});

const breadcrumbs = [
    { title: 'Admin', href: route('admin.dashboard') },
    { title: 'Notifications', href: route('notifications.index') },
    {
        title: isEditing.value ? `Edit: ${props.notification?.title ?? 'Notification'}` : 'Create Notification',
        href: isEditing.value ? route('notifications.edit', props.notification?.id) : route('notifications.create'),
    },
];
</script>

<template>
    <Head :title="isEditing ? 'Edit Notification' : 'Create Notification'" />

    <AppLayout>
        <div class="w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <div class="max-w-7xl space-y-6">
                <!-- Header Section -->
                <div class="mb-8 flex items-start justify-between">
                    <div class="flex items-center gap-4">
                        <Link :href="route('notifications.index')">
                            <Button variant="ghost" size="icon" class="h-10 w-10">
                                <ArrowLeft class="h-5 w-5" />
                            </Button>
                        </Link>
                        <div>
                            <div class="mb-2 flex items-center gap-3">
                                <div>
                                    <h1 class="text-3xl font-bold text-gray-900">
                                        {{ isEditing ? 'Edit Notification' : 'Create Payment Notification' }}
                                    </h1>
                                    <p class="mt-1 text-sm text-gray-600">
                                        {{
                                            isEditing
                                                ? 'Update notification details and re-activate if needed'
                                                : 'Set up a new notification for students to see on their dashboard'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Badge -->
                    <div v-if="isEditing" class="text-right">
                        <div
                            class="inline-flex items-center gap-2 rounded-lg px-4 py-2"
                            :class="form.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                        >
                            <span class="text-sm font-medium">
                                {{ form.is_active ? '✓ Active' : '○ Inactive' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Main Form Grid -->
                <div class="grid grid-cols-3 gap-8">
                    <!-- Left Column: Form (2/3 width) -->
                    <div class="col-span-2 space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <span>📝 Notification Content</span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form class="space-y-6">
                                    <!-- Title Field -->
                                    <div>
                                        <label class="mb-3 block text-sm font-semibold text-gray-900"> Notification Title * </label>
                                        <input
                                            v-model="form.title"
                                            type="text"
                                            placeholder="e.g., Second Semester Tuition Payment Required"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-3 transition focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                            required
                                        />
                                        <p v-if="form.errors.title" class="mt-2 text-sm text-red-600">{{ form.errors.title }}</p>
                                    </div>

                                    <!-- Message Field -->
                                    <div>
                                        <label class="mb-3 block text-sm font-semibold text-gray-900"> Message Content * </label>
                                        <textarea
                                            v-model="form.message"
                                            placeholder="Enter your notification message. Include payment amount, deadline, and payment instructions. This message will be clearly visible to students."
                                            class="h-40 w-full resize-none rounded-lg border border-gray-300 px-4 py-3 transition focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                        ></textarea>
                                        <p v-if="form.errors.message" class="mt-2 text-sm text-red-600">{{ form.errors.message }}</p>
                                        <p class="mt-2 text-xs text-gray-500">{{ form.message.length }} characters</p>
                                    </div>

                                    <!-- Notification Type -->
                                    <div>
                                        <label class="mb-3 block text-sm font-semibold text-gray-900"> Notification Type </label>
                                        <select
                                            v-model="form.type"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-3 transition focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                        >
                                            <option v-for="option in typeOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <p class="mt-2 text-xs text-gray-500">Classify the notification type for better organization</p>
                                        <p v-if="form.errors.type" class="mt-2 text-sm text-red-600">{{ form.errors.type }}</p>
                                    </div>

                                    <!-- Date Range -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="mb-3 block text-sm font-semibold text-gray-900"> Start Date * </label>
                                            <input
                                                v-model="form.start_date"
                                                type="date"
                                                class="w-full rounded-lg border border-gray-300 px-4 py-3 transition focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                                required
                                            />
                                            <p class="mt-2 text-xs text-gray-500">When this notification becomes active</p>
                                            <p v-if="form.errors.start_date" class="mt-2 text-sm text-red-600">{{ form.errors.start_date }}</p>
                                        </div>

                                        <div>
                                            <label class="mb-3 block text-sm font-semibold text-gray-900"> End Date (Optional) </label>
                                            <input
                                                v-model="form.end_date"
                                                type="date"
                                                class="w-full rounded-lg border border-gray-300 px-4 py-3 transition focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                            />
                                            <p class="mt-2 text-xs text-gray-500">Leave empty for ongoing notifications</p>
                                            <p v-if="form.errors.end_date" class="mt-2 text-sm text-red-600">{{ form.errors.end_date }}</p>
                                        </div>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        <!-- Target & Audience -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <span>👥 Target Audience</span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-6">
                                    <!-- Target Role -->
                                    <div>
                                        <label class="mb-3 block text-sm font-semibold text-gray-900"> Who should see this? * </label>
                                        <select
                                            v-model="form.target_role"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-3 transition focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                            required
                                        >
                                            <option value="">-- Select Audience --</option>
                                            <option v-for="option in roleOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <p class="mt-3 rounded border border-blue-200 bg-blue-50 p-3 text-xs text-gray-500">
                                            {{ messages[form.target_role as keyof typeof messages] || 'Select an audience' }}
                                        </p>
                                        <p v-if="form.errors.target_role" class="mt-2 text-sm text-red-600">{{ form.errors.target_role }}</p>
                                    </div>

                                    <!-- Specific Student Selector -->
                                    <div v-if="form.target_role === 'student'">
                                        <label class="mb-3 block text-sm font-semibold text-gray-900"> Send to Specific Student (Optional) </label>
                                        <p class="mb-3 text-xs text-gray-600">
                                            <strong>Leave empty</strong> to send to <strong>all students</strong>. Or select a specific student below
                                            to send a <strong>personal notification</strong> (e.g., payment confirmation) that only that student will
                                            see.
                                        </p>
                                        <p class="mb-3 rounded border border-green-200 bg-green-50 p-2 text-xs text-green-700">
                                            💡 <strong>Personal notifications (payment alerts, approvals):</strong> When you select a specific
                                            student, only that student sees the notification. This is perfect for payment-related messages like "Your
                                            payment has been approved" or "Payment due reminder for [StudentName]".
                                        </p>

                                        <!-- Search Input -->
                                        <div class="mb-4">
                                            <input
                                                v-model="searchQuery"
                                                type="text"
                                                placeholder="Search by name or email (e.g., jcdc742713@gmail.com)"
                                                class="w-full rounded-lg border border-gray-300 px-4 py-3 transition focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>

                                        <!-- Selected Student Display -->
                                        <div v-if="selectedStudent" class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="font-medium text-gray-900">{{ selectedStudent.name }}</p>
                                                    <p class="text-sm text-gray-600">{{ selectedStudent.email }}</p>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    @click="form.user_id = null"
                                                    class="text-red-600 hover:text-red-700"
                                                >
                                                    Clear
                                                </Button>
                                            </div>
                                        </div>

                                        <!-- Student List -->
                                        <div
                                            v-if="!selectedStudent && filteredStudents.length > 0"
                                            class="max-h-64 overflow-y-auto rounded-lg border border-gray-300"
                                        >
                                            <div
                                                v-for="student in filteredStudents"
                                                :key="student.id"
                                                @click="
                                                    form.user_id = student.id;
                                                    searchQuery = '';
                                                "
                                                class="cursor-pointer border-b border-gray-200 p-4 transition last:border-b-0 hover:bg-blue-50"
                                            >
                                                <p class="font-medium text-gray-900">{{ student.name }}</p>
                                                <p class="text-sm text-gray-600">{{ student.email }}</p>
                                            </div>
                                        </div>

                                        <div
                                            v-if="!selectedStudent && searchQuery && filteredStudents.length === 0"
                                            class="p-4 text-center text-gray-500"
                                        >
                                            No students found matching "{{ searchQuery }}"
                                        </div>

                                        <p v-if="form.errors.user_id" class="mt-2 text-sm text-red-600">{{ form.errors.user_id }}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Term-Based Scheduling (Optional) -->
                        <Card v-if="form.target_role === 'student'">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <span>📅 Term-Based Scheduling (Optional)</span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-6">
                                    <p class="text-xs text-gray-600">
                                        Limit this notification to specific payment terms. Leave empty to send to all students with any payment term.
                                    </p>

                                    <!-- Selection Mode Toggle -->
                                    <div>
                                        <label class="mb-3 block text-sm font-semibold text-gray-900"> How do you want to select terms? </label>
                                        <div class="space-y-2">
                                            <div class="flex items-center">
                                                <input
                                                    id="mode_none"
                                                    v-model="termSelectionMode"
                                                    type="radio"
                                                    value="none"
                                                    class="h-4 w-4 rounded border-gray-300 focus:ring-2 focus:ring-blue-500"
                                                />
                                                <label for="mode_none" class="ml-3 cursor-pointer text-sm text-gray-700">
                                                    No specific terms (send to all students)
                                                </label>
                                            </div>
                                            <div class="flex items-center">
                                                <input
                                                    id="mode_by_name"
                                                    v-model="termSelectionMode"
                                                    type="radio"
                                                    value="by_name"
                                                    class="h-4 w-4 rounded border-gray-300 focus:ring-2 focus:ring-blue-500"
                                                />
                                                <label for="mode_by_name" class="ml-3 cursor-pointer text-sm text-gray-700">
                                                    By term name (e.g., "Prelim", "Midterm")
                                                </label>
                                            </div>
                                            <div class="flex items-center">
                                                <input
                                                    id="mode_by_id"
                                                    v-model="termSelectionMode"
                                                    type="radio"
                                                    value="by_id"
                                                    class="h-4 w-4 rounded border-gray-300 focus:ring-2 focus:ring-blue-500"
                                                />
                                                <label for="mode_by_id" class="ml-3 cursor-pointer text-sm text-gray-700">
                                                    By specific payment terms
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Select by Term Name -->
                                    <div v-if="termSelectionMode === 'by_name'">
                                        <label class="mb-3 block text-sm font-semibold text-gray-900"> Which term? * </label>
                                        <select
                                            v-model="form.target_term_name"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-3 transition focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                            required
                                        >
                                            <option value="">-- Select a Term --</option>
                                            <option value="Upon Registration">Upon Registration</option>
                                            <option value="Prelim">Prelim</option>
                                            <option value="Midterm">Midterm</option>
                                            <option value="Semi-Final">Semi-Final</option>
                                            <option value="Final">Final</option>
                                        </select>
                                        <p class="mt-2 text-xs text-gray-500">Only students with this term will see the notification</p>
                                        <p v-if="form.errors.target_term_name" class="mt-2 text-sm text-red-600">
                                            {{ form.errors.target_term_name }}
                                        </p>
                                    </div>

                                    <!-- Select by Specific Payment Terms -->
                                    <div v-if="termSelectionMode === 'by_id'">
                                        <label class="mb-3 block text-sm font-semibold text-gray-900"> Select Payment Terms * </label>
                                        <div class="max-h-48 space-y-2 overflow-y-auto rounded-lg border border-gray-300 p-4">
                                            <div v-if="paymentTerms.length === 0" class="text-sm text-gray-500">No payment terms available</div>
                                            <div v-for="term in paymentTerms" :key="term.id" class="flex items-center">
                                                <input
                                                    :id="`term_${term.id}`"
                                                    type="checkbox"
                                                    :value="term.id"
                                                    v-model="form.term_ids"
                                                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500"
                                                />
                                                <label :for="`term_${term.id}`" class="ml-3 cursor-pointer text-sm text-gray-700">
                                                    {{ term.term_name }}
                                                </label>
                                            </div>
                                        </div>
                                        <p class="mt-2 text-xs text-gray-500">Only students with these terms will see the notification</p>
                                        <p v-if="form.errors['term_ids.*']" class="mt-2 text-sm text-red-600">{{ form.errors['term_ids.*'] }}</p>
                                    </div>

                                    <!-- Trigger Days Before Due Date -->
                                    <div v-if="termSelectionMode !== 'none'">
                                        <label class="mb-3 block text-sm font-semibold text-gray-900">
                                            Show this notification N days before term due date (Optional)
                                        </label>
                                        <input
                                            v-model.number="form.trigger_days_before_due"
                                            type="number"
                                            placeholder="e.g., 3 days before due date"
                                            min="0"
                                            max="90"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-3 transition focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                        />
                                        <p class="mt-2 text-xs text-gray-500">
                                            If specified, notification will only show to students when their payment term is due within this many days
                                        </p>
                                        <p v-if="form.errors.trigger_days_before_due" class="mt-2 text-sm text-red-600">
                                            {{ form.errors.trigger_days_before_due }}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Right Column: Sidebar (1/3 width) -->
                    <div class="col-span-1 space-y-6">
                        <!-- Activation Toggle Card -->
                        <Card class="border-2" :class="form.is_active ? 'border-green-200 bg-green-50' : 'border-gray-200'">
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
                                            form.is_active ? 'bg-green-500 text-white hover:bg-green-600' : 'bg-gray-300 text-white hover:bg-gray-400'
                                        "
                                    >
                                        <component :is="form.is_active ? ToggleRight : ToggleLeft" class="h-6 w-6" />
                                        <span class="font-semibold">
                                            {{ form.is_active ? 'Notification Active' : 'Notification Inactive' }}
                                        </span>
                                    </button>

                                    <div class="rounded-lg p-3" :class="form.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                                        <p class="text-xs font-medium">
                                            <span v-if="form.is_active">✓ Students will see this notification</span>
                                            <span v-else>○ Students will NOT see this notification</span>
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Preview Card -->
                        <NotificationPreview
                            :title="form.title"
                            :message="form.message"
                            :start-date="form.start_date"
                            :end-date="form.end_date"
                            :target-role="form.target_role"
                            :selected-student-email="selectedStudent?.email"
                        />

                        <!-- Tips Card -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-sm">💡 Tips</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul class="space-y-2 text-xs text-gray-700">
                                    <li>✓ Include payment amount and deadline</li>
                                    <li>✓ Be clear and professional</li>
                                    <li>✓ Provide payment instructions</li>
                                    <li>✓ Set realistic date ranges</li>
                                    <li>✓ Remember to ACTIVATE the notification</li>
                                </ul>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-8 flex justify-end gap-3 border-t border-gray-300 pt-6">
                    <Link :href="route('notifications.index')">
                        <Button type="button" variant="outline" class="px-6"> Cancel </Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing" @click="submit" class="bg-blue-600 px-8 text-white hover:bg-blue-700">
                        <span v-if="form.processing" class="inline-block">Saving...</span>
                        <span v-else>{{ isEditing ? 'Update Notification' : 'Create Notification' }}</span>
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Smooth transitions */
input,
textarea,
select,
button {
    transition: all 0.2s ease;
}
</style>
