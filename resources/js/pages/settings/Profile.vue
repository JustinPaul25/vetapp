<script setup lang="ts">
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import { Form, Head, Link, usePage, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import LocationMapPicker from '@/components/LocationMapPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

const page = usePage();
const user = page.props.auth.user;

// Location state - convert from database 'long' to component 'lng'
const location = ref<{ lat: number | null; lng: number | null } | null>(
    user.lat && user.long
        ? { lat: parseFloat(user.lat), lng: parseFloat(user.long) }
        : null
);

const address = ref<string>(user.address || '');

// Update location when map picker changes
const updateLocation = (value: { lat: number | null; lng: number | null }) => {
    location.value = value;
};

// Form instance for profile update
const form = router.form({
    name: user.name,
    email: user.email,
    address: user.address || '',
    lat: location.value?.lat ?? null,
    long: location.value?.lng ?? null,
});

// Watch for location and address changes to update form
watch([location, address], () => {
    form.lat = location.value?.lat ?? null;
    form.long = location.value?.lng ?? null;
    form.address = address.value;
}, { deep: true });

const submit = () => {
    // Ensure form has latest values
    form.lat = location.value?.lat ?? null;
    form.long = location.value?.lng ?? null;
    form.address = address.value;
    
    form.patch(ProfileController.update.url());
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Profile information"
                    description="Update your name and email address"
                />

                <form
                    @submit.prevent="submit"
                    class="space-y-6"
                >
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            name="name"
                            v-model="form.name"
                            required
                            autocomplete="name"
                            placeholder="Full name"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="address">Address</Label>
                        <Input
                            id="address"
                            type="text"
                            class="mt-1 block w-full"
                            name="address"
                            v-model="address"
                            autocomplete="street-address"
                            placeholder="Street, Barangay, City, Province"
                        />
                        <InputError class="mt-2" :message="form.errors.address" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Location Pin</Label>
                        <p class="text-xs text-muted-foreground mb-2">
                            Click on the map to set a location pin, or drag the pin to adjust its position.
                        </p>
                        <LocationMapPicker
                            :model-value="location"
                            @update:model-value="updateLocation"
                            height="400px"
                        />
                        <InputError class="mt-2" :message="form.errors.lat" />
                        <InputError class="mt-2" :message="form.errors.long" />
                        <!-- Hidden inputs for location data -->
                        <input
                            type="hidden"
                            name="lat"
                            :value="location?.lat ?? ''"
                        />
                        <input
                            type="hidden"
                            name="long"
                            :value="location?.lng ?? ''"
                        />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Your email address is unverified.
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Click here to resend the verification email.
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            A new verification link has been sent to your email
                            address.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            data-test="update-profile-button"
                            >Save</Button
                        >

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="form.recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
