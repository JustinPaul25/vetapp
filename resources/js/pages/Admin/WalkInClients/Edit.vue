<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Form, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import LocationMapPicker from '@/components/LocationMapPicker.vue';
import { UserPlus, ArrowLeft } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { ref } from 'vue';

interface WalkInClient {
    id: number;
    first_name: string | null;
    last_name: string | null;
    name: string;
    email: string;
    mobile_number: string | null;
    address: string | null;
    lat: number | null;
    lng: number | null;
}

interface Props {
    walkInClient: WalkInClient;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Walk-In Clients', href: '/admin/walk_in_clients' },
    { title: 'Edit Walk-In Client', href: '#' },
];

const location = ref<{ lat: number | null; lng: number | null } | null>(
    props.walkInClient.lat && props.walkInClient.lng
        ? { lat: props.walkInClient.lat, lng: props.walkInClient.lng }
        : null
);

const form = router.form({
    first_name: props.walkInClient.first_name || '',
    last_name: props.walkInClient.last_name || '',
    name: props.walkInClient.name || '',
    email: props.walkInClient.email,
    mobile_number: props.walkInClient.mobile_number || '',
    address: props.walkInClient.address || '',
    lat: props.walkInClient.lat || null,
    lng: props.walkInClient.lng || null,
});

const updateLocation = (value: { lat: number | null; lng: number | null }) => {
    location.value = value;
    form.lat = value.lat;
    form.lng = value.lng;
};

const submit = () => {
    form.put(`/admin/walk_in_clients/${props.walkInClient.id}`);
};
</script>

<template>
    <Head title="Edit Walk-In Client" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-2xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Link href="/admin/walk_in_clients">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft class="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <UserPlus class="h-5 w-5" />
                                Edit Walk-In Client
                            </CardTitle>
                            <CardDescription>
                                Update walk-in client information
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <Label for="first_name">First Name</Label>
                                <Input
                                    id="first_name"
                                    v-model="form.first_name"
                                    type="text"
                                    autocomplete="given-name"
                                />
                                <InputError :message="form.errors.first_name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="last_name">Last Name</Label>
                                <Input
                                    id="last_name"
                                    v-model="form.last_name"
                                    type="text"
                                    autocomplete="family-name"
                                />
                                <InputError :message="form.errors.last_name" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="name">Full Name <span class="text-muted-foreground text-xs">(optional, auto-generated if left blank)</span></Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                autocomplete="name"
                                placeholder="Will be auto-generated from first and last name"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="email">Email <span class="text-red-500">*</span></Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="email"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="mobile_number">Mobile Number <span class="text-muted-foreground text-xs">(Philippine number)</span></Label>
                            <Input
                                id="mobile_number"
                                v-model="form.mobile_number"
                                type="tel"
                                autocomplete="tel"
                                placeholder="09123456789 or +639123456789"
                            />
                            <p class="text-xs text-muted-foreground">Format: 09XX XXX XXXX or +639XX XXX XXXX</p>
                            <InputError :message="form.errors.mobile_number" />
                        </div>

                        <div class="space-y-2">
                            <Label for="address">Complete Address</Label>
                            <Input
                                id="address"
                                v-model="form.address"
                                type="text"
                                autocomplete="street-address"
                                placeholder="Street, Barangay, City, Province"
                            />
                            <InputError :message="form.errors.address" />
                        </div>

                        <div class="space-y-2">
                            <Label>Location Pin</Label>
                            <p class="text-xs text-muted-foreground mb-2">Click on the map to set a location pin, or drag the pin to adjust its position.</p>
                            <LocationMapPicker
                                :model-value="location"
                                @update:model-value="updateLocation"
                                height="400px"
                            />
                            <InputError :message="form.errors.lat" />
                            <InputError :message="form.errors.lng" />
                        </div>

                        <div class="flex justify-end gap-4">
                            <Link :href="`/admin/walk_in_clients/${walkInClient.id}`">
                                <Button type="button" variant="outline">Cancel</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                Update Walk-In Client
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>






