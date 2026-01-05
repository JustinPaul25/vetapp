<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PasswordRequirements from '@/components/PasswordRequirements.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Form, Head, router } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';

const password = ref('');
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const form = router.form({
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.password = password.value;
    form.post('/register/password');
};
</script>

<template>
    <AuthBase
        title="Create your password"
        description="Step 4 of 5: Set up a secure password"
    >
        <Head title="Register - Step 4" />

        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-muted-foreground">Step 4 of 5</span>
                <span class="text-sm text-muted-foreground">Password Setup</span>
            </div>
            <div class="w-full bg-muted rounded-full h-2">
                <div class="bg-primary h-2 rounded-full" style="width: 80%"></div>
            </div>
        </div>

        <Form
            :form="form"
            @submit.prevent="submit"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="password">Password <span class="text-red-500">*</span></Label>
                    <div class="relative">
                        <Input
                            id="password"
                            v-model="password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            autofocus
                            autocomplete="new-password"
                            placeholder="Password"
                            class="pr-10"
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none"
                            tabindex="-1"
                        >
                            <Eye v-if="!showPassword" class="h-4 w-4" />
                            <EyeOff v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <PasswordRequirements :password="password" />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm Password <span class="text-red-500">*</span></Label>
                    <div class="relative">
                        <Input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            :type="showPasswordConfirmation ? 'text' : 'password'"
                            required
                            autocomplete="new-password"
                            placeholder="Confirm password"
                            class="pr-10"
                        />
                        <button
                            type="button"
                            @click="showPasswordConfirmation = !showPasswordConfirmation"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none"
                            tabindex="-1"
                        >
                            <Eye v-if="!showPasswordConfirmation" class="h-4 w-4" />
                            <EyeOff v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Continue to Review
                </Button>
            </div>
        </Form>
    </AuthBase>
</template>
