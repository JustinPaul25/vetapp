<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PasswordRequirements from '@/components/PasswordRequirements.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import AddressMapPicker from '@/components/AddressMapPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const password = ref('');
const address = ref('');
const location = ref<{ lat: number | null; lng: number | null } | null>(null);

// Update location when map picker changes
const updateLocation = (value: { lat: number | null; lng: number | null }) => {
    location.value = value;
};
</script>

<template>
    <AuthBase
        title="Create an account"
        description="Enter your details below to create your account"
    >
        <Head title="Register" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
            @success="password = ''"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        name="name"
                        placeholder="Full name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <PasswordInput
                        id="password"
                        :model-value="password"
                        @update:model-value="password = $event"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Password"
                    />
                    <PasswordRequirements :password="password" />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <PasswordInput
                        id="password_confirmation"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Confirm password"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <div class="grid gap-2">
                    <Label for="mobile_number">Contact Number <span class="text-red-500">*</span></Label>
                    <Input
                        id="mobile_number"
                        type="tel"
                        required
                        :tabindex="5"
                        autocomplete="tel"
                        name="mobile_number"
                        placeholder="09123456789 or +639123456789"
                    />
                    <p class="text-xs text-muted-foreground">Format: 09XX XXX XXXX or +639XX XXX XXXX</p>
                    <InputError :message="errors.mobile_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="address">Address <span class="text-red-500">*</span></Label>
                    <AddressMapPicker
                        v-model="address"
                        :coordinates="location"
                        @update:coordinates="updateLocation"
                        required
                        height="250px"
                    />
                    <input
                        type="hidden"
                        name="address"
                        :value="address"
                    />
                    <input
                        type="hidden"
                        name="lat"
                        :value="location?.lat ?? ''"
                    />
                    <input
                        type="hidden"
                        name="lng"
                        :value="location?.lng ?? ''"
                    />
                    <InputError :message="errors.address" />
                    <InputError :message="errors.lat" />
                    <InputError :message="errors.lng" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    tabindex="7"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Create account
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="6"
                    >Log in</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
