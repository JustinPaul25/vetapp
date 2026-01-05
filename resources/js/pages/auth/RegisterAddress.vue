<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import StructuredAddressPicker from '@/components/StructuredAddressPicker.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Form, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const province = ref('');
const city = ref('');
const barangay = ref('');
const street = ref('');
const location = ref<{ lat: number | null; lng: number | null } | null>(null);

const form = router.form({
    province: '',
    city: '',
    barangay: '',
    street: '',
    lat: null as number | null,
    lng: null as number | null,
});

const updateLocation = (value: { lat: number | null; lng: number | null }) => {
    location.value = value;
    form.lat = value.lat;
    form.lng = value.lng;
};

const submit = () => {
    form.province = province.value;
    form.city = city.value;
    form.barangay = barangay.value;
    form.street = street.value;
    form.post('/register/address');
};
</script>

<template>
    <AuthBase
        title="Enter your address"
        description="Step 3 of 5: Provide your complete address"
    >
        <Head title="Register - Step 3" />

        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-muted-foreground">Step 3 of 5</span>
                <span class="text-sm text-muted-foreground">Address Information</span>
            </div>
            <div class="w-full bg-muted rounded-full h-2">
                <div class="bg-primary h-2 rounded-full" style="width: 60%"></div>
            </div>
        </div>

        <Form
            :form="form"
            @submit.prevent="submit"
            class="flex flex-col gap-6"
        >
            <StructuredAddressPicker
                v-model:province="province"
                v-model:city="city"
                v-model:barangay="barangay"
                v-model:street="street"
                :coordinates="location"
                @update:coordinates="updateLocation"
                height="300px"
            />

            <InputError :message="form.errors.province" />
            <InputError :message="form.errors.city" />
            <InputError :message="form.errors.barangay" />
            <InputError :message="form.errors.street" />
            <InputError :message="form.errors.lat" />
            <InputError :message="form.errors.lng" />

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="form.processing"
            >
                <Spinner v-if="form.processing" />
                Continue to Password Setup
            </Button>
        </Form>
    </AuthBase>
</template>
