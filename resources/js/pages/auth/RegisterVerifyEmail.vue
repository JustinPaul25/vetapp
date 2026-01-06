<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Input } from '@/components/ui/input';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Form, Head, router } from '@inertiajs/vue3';
import { Mail, CheckCircle2, AlertCircle } from 'lucide-vue-next';
import { ref, onMounted, computed } from 'vue';

defineProps<{
    status?: string;
    errors?: {
        code?: string;
    };
}>();

const verificationForm = router.form({
    code: '',
});

const resendForm = router.form({});

const submitVerification = () => {
    verificationForm.post('/register/verify-code', {
        preserveScroll: true,
        onSuccess: () => {
            router.visit('/register/address');
        },
    });
};

const resendCode = () => {
    resendForm.post('/register/resend-verification', {
        preserveScroll: true,
    });
};

// Only allow numeric input and ensure proper length
const handleCodeInput = (event: Event) => {
    const input = event.target as HTMLInputElement;
    let numericValue = input.value.replace(/\D/g, '').slice(0, 6);
    // Update the form value directly
    verificationForm.code = numericValue;
};

// Prevent non-numeric characters on keypress
const handleKeyPress = (event: KeyboardEvent) => {
    const char = String.fromCharCode(event.which || event.keyCode);
    if (!/[0-9]/.test(char)) {
        event.preventDefault();
    }
};

// Computed property to check if button should be enabled
const isVerifyButtonDisabled = computed(() => {
    return verificationForm.processing || 
           !verificationForm.code || 
           verificationForm.code.length !== 6 ||
           !/^\d{6}$/.test(verificationForm.code);
});

onMounted(() => {
    // Auto-focus the code input after a short delay to ensure DOM is ready
    setTimeout(() => {
        const inputElement = document.getElementById('code') as HTMLInputElement | null;
        inputElement?.focus();
    }, 100);
});
</script>

<template>
    <AuthBase
        title="Verify your email"
        description="Step 2 of 5: Please verify your email address"
    >
        <Head title="Register - Step 2" />

        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-muted-foreground">Step 2 of 5</span>
                <span class="text-sm text-muted-foreground">Email Verification</span>
            </div>
            <div class="w-full bg-muted rounded-full h-2">
                <div class="bg-primary h-2 rounded-full" style="width: 40%"></div>
            </div>
        </div>

        <div class="flex flex-col items-center gap-6">
            <div class="rounded-full bg-primary/10 p-4">
                <Mail class="h-8 w-8 text-primary" />
            </div>

            <div class="text-center space-y-2">
                <h3 class="text-lg font-semibold">Check your email</h3>
                <p class="text-sm text-muted-foreground">
                    We've sent a verification code to your email address. Please enter the code below to verify your email.
                </p>
            </div>

            <div
                v-if="status === 'verification-code-sent'"
                class="w-full p-4 bg-green-50 border border-green-200 rounded-lg text-center"
            >
                <div class="flex items-center justify-center gap-2 text-green-700">
                    <CheckCircle2 class="h-5 w-5" />
                    <span class="text-sm font-medium">Verification code sent!</span>
                </div>
            </div>

            <Form
                :form="verificationForm"
                @submit.prevent="submitVerification"
                class="w-full space-y-4"
            >
                <div class="space-y-2">
                    <label for="code" class="text-sm font-medium text-foreground">
                        Verification Code
                    </label>
                    <Input
                        id="code"
                        v-model="verificationForm.code"
                        type="text"
                        placeholder="Enter 6-digit code"
                        maxlength="6"
                        class="text-center text-2xl font-mono tracking-widest"
                        :class="{ 'border-red-500': errors?.code }"
                        autocomplete="one-time-code"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        @input="handleCodeInput"
                        @keypress="handleKeyPress"
                    />
                    <p v-if="errors?.code" class="text-sm text-red-600 flex items-center gap-1">
                        <AlertCircle class="h-4 w-4" />
                        {{ errors.code }}
                    </p>
                </div>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="isVerifyButtonDisabled"
                >
                    <Spinner v-if="verificationForm.processing" />
                    Verify Email
                </Button>
            </Form>

            <div class="w-full border-t pt-4">
                <Form
                    :form="resendForm"
                    @submit.prevent="resendCode"
                    class="w-full space-y-4"
                >
                    <p class="text-sm text-muted-foreground text-center">
                        Didn't receive the code? Check your spam folder or
                    </p>
                    <Button
                        type="submit"
                        variant="outline"
                        class="w-full"
                        :disabled="resendForm.processing"
                    >
                        <Spinner v-if="resendForm.processing" />
                        Resend verification code
                    </Button>
                </Form>
            </div>

            <p class="text-xs text-muted-foreground text-center">
                The verification code will expire in 60 minutes.
            </p>
        </div>
    </AuthBase>
</template>
