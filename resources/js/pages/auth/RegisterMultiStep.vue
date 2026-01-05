<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { Form, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = router.form({
    name: '',
    email: '',
    mobile_number: '',
});

const submit = () => {
    form.post('/register/step1');
};
</script>

<template>
    <AuthBase
        title="Create an account"
        description="Step 1 of 5: Enter your basic information"
    >
        <Head title="Register - Step 1" />

        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-muted-foreground">Step 1 of 5</span>
                <span class="text-sm text-muted-foreground">Basic Information</span>
            </div>
            <div class="w-full bg-muted rounded-full h-2">
                <div class="bg-primary h-2 rounded-full" style="width: 20%"></div>
            </div>
        </div>

        <Form
            :form="form"
            @submit.prevent="submit"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Full Name <span class="text-red-500">*</span></Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Full name"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email Address <span class="text-red-500">*</span></Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="mobile_number">Contact Number <span class="text-red-500">*</span></Label>
                    <Input
                        id="mobile_number"
                        v-model="form.mobile_number"
                        type="tel"
                        required
                        autocomplete="tel"
                        placeholder="09123456789 or +639123456789"
                    />
                    <p class="text-xs text-muted-foreground">Format: 09XX XXX XXXX or +639XX XXX XXXX</p>
                    <InputError :message="form.errors.mobile_number" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Continue to Email Verification
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                >Log in</TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
