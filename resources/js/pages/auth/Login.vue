<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <AuthBase
        title="Log in to your account"
        description="Enter your email and password below to log in"
    >
        <Head title="Log in" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email" class="login-label">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                        class="login-input"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password" class="login-label">Password</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm text-blue-600 hover:text-blue-700"
                            :tabindex="5"
                        >
                            Forgot password?
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Password"
                        class="login-input"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="login-label flex items-center space-x-3 cursor-pointer">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full bg-blue-600 hover:bg-blue-700 text-white"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Log in
                </Button>
            </div>

            <div
                class="text-center text-sm text-gray-600"
                v-if="canRegister"
            >
                Don't have an account?
                <TextLink :href="register()" :tabindex="5" class="text-blue-600 hover:text-blue-700 font-medium">Sign up</TextLink>
            </div>
        </Form>
    </AuthBase>
</template>

<style scoped>
:deep(.text-blue-600) {
    color: #2563eb !important;
}

:deep(.text-blue-700) {
    color: #1d4ed8 !important;
}

:deep(.hover\:text-blue-700:hover) {
    color: #1d4ed8 !important;
}

/* Make labels visible */
.login-label {
    color: #111827 !important;
    font-weight: 500 !important;
    font-size: 0.875rem !important;
    margin-bottom: 0.25rem;
}

.login-label span {
    color: #111827 !important;
}

/* Fix input styling */
:deep(.login-input) {
    color: #111827 !important;
    background-color: #ffffff !important;
    border-color: #d1d5db !important;
    border-width: 1px !important;
    padding: 0.5rem 0.75rem !important;
    width: 100% !important;
    box-sizing: border-box !important;
}

:deep(.login-input::placeholder) {
    color: #9ca3af !important;
}

:deep(.login-input:focus) {
    outline: none !important;
    border-color: #2563eb !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1) !important;
}

:deep(.login-input:hover) {
    border-color: #9ca3af !important;
}
</style>
