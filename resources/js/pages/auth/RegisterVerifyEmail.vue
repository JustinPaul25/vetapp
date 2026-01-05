<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Form, Head, router } from '@inertiajs/vue3';
import { Mail, CheckCircle2 } from 'lucide-vue-next';
import { onMounted, onBeforeUnmount } from 'vue';

defineProps<{
    status?: string;
}>();

const form = router.form({});

const submit = () => {
    form.post('/register/resend-verification');
};

// Poll for email verification status
let checkInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    // Check every 3 seconds if email is verified
    checkInterval = setInterval(() => {
        router.reload({
            only: ['auth'],
            onSuccess: (page) => {
                if (page.props.auth?.user?.email_verified_at) {
                    if (checkInterval) clearInterval(checkInterval);
                    router.visit('/register/address');
                }
            },
        });
    }, 3000);
});

onBeforeUnmount(() => {
    if (checkInterval) {
        clearInterval(checkInterval);
    }
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
                    We've sent a verification link to your email address. Please click the link to verify your email.
                </p>
            </div>

            <div
                v-if="status === 'verification-link-sent'"
                class="w-full p-4 bg-green-50 border border-green-200 rounded-lg text-center"
            >
                <div class="flex items-center justify-center gap-2 text-green-700">
                    <CheckCircle2 class="h-5 w-5" />
                    <span class="text-sm font-medium">Verification link sent!</span>
                </div>
            </div>

            <Form
                :form="form"
                @submit.prevent="submit"
                class="w-full space-y-4"
            >
                <p class="text-sm text-muted-foreground text-center">
                    Didn't receive the email? Check your spam folder or
                </p>
                <Button
                    type="submit"
                    variant="outline"
                    class="w-full"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Resend verification email
                </Button>
            </Form>

            <p class="text-xs text-muted-foreground text-center">
                Once you verify your email, you'll automatically proceed to the next step.
            </p>
        </div>
    </AuthBase>
</template>
