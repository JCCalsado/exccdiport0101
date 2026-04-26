<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertCircle, Bell, CalendarClock, CheckCircle2, Megaphone } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    title?: string;
    message?: string;
    startDate?: string;
    endDate?: string;
    dueDate?: string;
    targetRole?: string;
    type?: string;
    selectedStudentEmail?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Notification Title',
    message: 'Your message will appear here...',
    startDate: '',
    endDate: '',
    dueDate: '',
    targetRole: 'student',
    type: 'general',
    selectedStudentEmail: '',
});

const getRoleLabel = (role: string) => {
    const labels: Record<string, string> = {
        student:    'All Students',
        accounting: 'Accounting Staff',
        admin:      'Admins',
        all:        'Everyone',
    };
    return labels[role] || role;
};

const typeConfig = computed(() => {
    const configs: Record<string, {
        icon: any;
        cardClass: string;
        badgeClass: string;
        iconClass: string;
        label: string;
    }> = {
        payment_due: {
            label:     'Payment Due',
            icon:      CalendarClock,
            cardClass: 'border-amber-200 bg-amber-50',
            badgeClass:'bg-amber-100 text-amber-800',
            iconClass: 'text-amber-500',
        },
        payment_approved: {
            label:     'Payment Approved',
            icon:      CheckCircle2,
            cardClass: 'border-emerald-200 bg-emerald-50',
            badgeClass:'bg-emerald-100 text-emerald-800',
            iconClass: 'text-emerald-500',
        },
        payment_rejected: {
            label:     'Payment Rejected',
            icon:      AlertCircle,
            cardClass: 'border-red-200 bg-red-50',
            badgeClass:'bg-red-100 text-red-800',
            iconClass: 'text-red-500',
        },
        general: {
            label:     'Announcement',
            icon:      Megaphone,
            cardClass: 'border-blue-200 bg-blue-50',
            badgeClass:'bg-blue-100 text-blue-800',
            iconClass: 'text-blue-500',
        },
    };
    return configs[props.type ?? 'general'] ?? configs.general;
});

const formatDate = (d: string) => {
    if (!d) return '';
    return new Date(d + 'T12:00:00').toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
};

const dueDateUrgency = computed(() => {
    if (!props.dueDate) return null;
    const diff = Math.ceil((new Date(props.dueDate).getTime() - Date.now()) / 86_400_000);
    if (diff <= 7)  return { cls: 'text-red-700 font-bold', label: `Due ${formatDate(props.dueDate)} ⚠️` };
    if (diff <= 14) return { cls: 'text-amber-700 font-semibold', label: `Due ${formatDate(props.dueDate)}` };
    return { cls: 'text-gray-600', label: `Due ${formatDate(props.dueDate)}` };
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-sm">📺 Live Preview</CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Simulated student notification card -->
            <div
                :class="['rounded-xl border p-4 transition-all duration-300', typeConfig.cardClass]"
            >
                <!-- Type badge + icon -->
                <div class="mb-3 flex items-start gap-2">
                    <component
                        :is="typeConfig.icon"
                        :size="18"
                        :class="['mt-0.5 shrink-0', typeConfig.iconClass]"
                    />
                    <div class="min-w-0 flex-1">
                        <span
                            :class="[
                                'mb-1 inline-block rounded-md px-2 py-0.5 text-xs font-semibold',
                                typeConfig.badgeClass,
                            ]"
                        >
                            {{ typeConfig.label }}
                        </span>
                        <h4 class="text-sm font-semibold text-gray-900 leading-tight">
                            {{ title || 'Notification Title' }}
                        </h4>
                    </div>
                </div>

                <!-- Message -->
                <p class="pl-6 text-xs leading-relaxed text-gray-700 whitespace-pre-line max-h-24 overflow-y-auto">
                    {{ message || 'Your message will appear here...' }}
                </p>

                <!-- Footer meta -->
                <div class="mt-3 pl-6 space-y-1 border-t border-black/10 pt-2 text-xs text-gray-500">
                    <p v-if="dueDateUrgency" :class="['flex items-center gap-1', dueDateUrgency.cls]">
                        <CalendarClock :size="12" />
                        {{ dueDateUrgency.label }}
                    </p>
                    <p v-if="startDate" class="flex items-center gap-1">
                        📅 From: {{ formatDate(startDate) }}
                        <span v-if="endDate"> — {{ formatDate(endDate) }}</span>
                    </p>
                    <p>
                        <strong>👥 For:</strong>
                        {{ selectedStudentEmail ? selectedStudentEmail : getRoleLabel(targetRole) }}
                    </p>
                </div>

                <!-- Simulated dismiss button (non-functional, for preview only) -->
                <div class="mt-3 pl-6 flex justify-end">
                    <span class="rounded-lg border border-black/15 px-3 py-1 text-xs font-medium text-gray-400 cursor-not-allowed opacity-60">
                        Dismiss
                    </span>
                </div>
            </div>

            <p class="mt-2 text-center text-xs text-gray-400 italic">
                This is how students will see this notification
            </p>
        </CardContent>
    </Card>
</template>