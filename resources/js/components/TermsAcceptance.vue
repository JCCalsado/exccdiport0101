<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Props {
    adminId?: number;
    onTermsAccepted?: () => void;
}

withDefaults(defineProps<Props>(), {});

const accepted = ref(false);
const termsVisible = ref(false);

const form = useForm({
    terms_accepted: false,
});

const submitTerms = () => {
    // In a real implementation, this would POST to accept terms
    accepted.value = true;
    emit('termsAccepted');
};

const emit = defineEmits<{
    termsAccepted: [];
}>();
</script>

<template>
    <div class="space-y-4">
        <div v-if="!accepted" class="rounded-lg border bg-amber-50 p-4">
            <h3 class="mb-4 text-lg font-semibold">Terms & Conditions</h3>

            <div v-if="!termsVisible" class="mb-4">
                <p class="mb-4 text-sm text-gray-600">As an administrator, you must accept the terms and conditions before proceeding.</p>
                <Button @click="termsVisible = true" variant="outline" class="w-full"> Read Terms & Conditions </Button>
            </div>

            <div v-else class="mb-4 max-h-64 overflow-y-auto rounded border bg-white p-4">
                <h4 class="mb-2 font-semibold">Administrator Terms & Conditions</h4>
                <div class="space-y-2 text-sm text-gray-700">
                    <p><strong>1. Responsibility:</strong> Administrators are responsible for maintaining system integrity.</p>
                    <p><strong>2. Data Security:</strong> All user data must be handled securely and confidentially.</p>
                    <p><strong>3. Audit Trail:</strong> All admin actions are logged and auditable.</p>
                    <p><strong>4. Compliance:</strong> Administrators must comply with all system policies.</p>
                    <p><strong>5. Account Security:</strong> You are responsible for protecting your login credentials.</p>
                    <p><strong>6. Misuse:</strong> Unauthorized access or misuse of admin privileges is prohibited.</p>
                </div>
            </div>

            <div class="mb-4 flex items-start space-x-2">
                <Checkbox id="terms" v-model:checked="form.terms_accepted" />
                <label for="terms" class="cursor-pointer text-sm"> I accept the terms and conditions </label>
            </div>

            <Button @click="submitTerms" :disabled="!form.terms_accepted || form.processing" class="w-full">
                {{ form.processing ? 'Processing...' : 'Accept Terms' }}
            </Button>
        </div>

        <div v-else class="border-l-4 border-green-500 bg-green-50 p-4">
            <p class="text-green-800">✓ Terms and conditions accepted</p>
        </div>
    </div>
</template>
