<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Form, Head, router } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    user: {
        name: string;
        email: string;
        mobile_number: string;
        province: string;
        city: string;
        barangay: string;
        street: string;
    };
}

const props = defineProps<Props>();

const confirmed = ref(false);

const form = router.form({
    confirmed: false,
});

const submit = () => {
    form.confirmed = confirmed.value;
    form.post('/register/finalize');
};
</script>

<template>
    <AuthBase
        title="Review your information"
        description="Step 5 of 5: Please review and confirm your details"
    >
        <Head title="Register - Step 5" />

        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-muted-foreground">Step 5 of 5</span>
                <span class="text-sm text-muted-foreground">Review & Confirm</span>
            </div>
            <div class="w-full bg-muted rounded-full h-2">
                <div class="bg-primary h-2 rounded-full" style="width: 100%"></div>
            </div>
        </div>

        <Form
            :form="form"
            @submit.prevent="submit"
            class="flex flex-col gap-6"
        >
            <div class="space-y-4">
                <div class="rounded-lg border p-4 space-y-3">
                    <h3 class="font-semibold text-lg mb-4">Your Information</h3>
                    
                    <div class="grid gap-3">
                        <div>
                            <Label class="text-sm font-medium text-muted-foreground">Full Name</Label>
                            <p class="text-base font-medium">{{ user.name }}</p>
                        </div>

                        <div>
                            <Label class="text-sm font-medium text-muted-foreground">Email Address</Label>
                            <p class="text-base font-medium">{{ user.email }}</p>
                        </div>

                        <div>
                            <Label class="text-sm font-medium text-muted-foreground">Contact Number</Label>
                            <p class="text-base font-medium">{{ user.mobile_number }}</p>
                        </div>

                        <div>
                            <Label class="text-sm font-medium text-muted-foreground">Address</Label>
                            <p class="text-base font-medium">
                                {{ user.street }}, {{ user.barangay }}, {{ user.city }}, {{ user.province }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-start space-x-2 p-4 border rounded-lg">
                    <Checkbox
                        id="confirmed"
                        v-model="confirmed"
                        required
                    />
                    <Label
                        for="confirmed"
                        class="text-sm leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                        @click="confirmed = !confirmed"
                    >
                        I confirm that all the information provided is accurate and correct.
                    </Label>
                </div>

                <InputError :message="form.errors.confirmed" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="form.processing || !confirmed"
            >
                <Spinner v-if="form.processing" />
                <CheckCircle2 v-else class="h-4 w-4 mr-2" />
                Complete Registration
            </Button>
        </Form>
    </AuthBase>
</template>
