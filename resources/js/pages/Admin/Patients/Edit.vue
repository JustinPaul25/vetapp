<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Form, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { SearchableSelect } from '@/components/ui/searchable-select';
import InputError from '@/components/InputError.vue';
import { Heart, ArrowLeft } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { computed } from 'vue';

interface PetType {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface Patient {
    id: number;
    pet_type_id: number;
    pet_name: string | null;
    pet_breed: string;
    pet_gender: string | null;
    pet_birth_date: string | null;
    pet_allergies: string | null;
    user_id: number | null;
}

interface Props {
    patient: Patient;
    pet_types: PetType[];
    users: User[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Patients', href: '/admin/patients' },
    { title: 'Edit Patient', href: '#' },
];

const form = router.form({
    pet_type_id: props.patient.pet_type_id.toString(),
    pet_name: props.patient.pet_name || '',
    pet_breed: props.patient.pet_breed,
    pet_gender: props.patient.pet_gender || '',
    pet_birth_date: props.patient.pet_birth_date || '',
    pet_allergies: props.patient.pet_allergies || '',
    user_id: props.patient.user_id ? props.patient.user_id.toString() : '',
});

const submit = () => {
    form.put(`/admin/patients/${props.patient.id}`);
};

// Transform pet types for SearchableSelect
const petTypeOptions = computed(() => {
    return props.pet_types.map(pt => ({
        value: pt.id.toString(),
        label: pt.name,
    }));
});
</script>

<template>
    <Head title="Edit Patient" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-4xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Link href="/admin/patients">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft class="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Heart class="h-5 w-5" />
                                Edit Patient
                            </CardTitle>
                            <CardDescription>
                                Update patient information
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <Label for="pet_type_id">Pet Type <span class="text-destructive">*</span></Label>
                                <SearchableSelect
                                    id="pet_type_id"
                                    v-model="form.pet_type_id"
                                    :options="petTypeOptions"
                                    placeholder="Select Pet Type"
                                    search-placeholder="Search pet types..."
                                    :required="true"
                                />
                                <InputError :message="form.errors.pet_type_id" />
                            </div>

                            <div class="space-y-2">
                                <Label for="pet_name">Pet Name</Label>
                                <Input
                                    id="pet_name"
                                    v-model="form.pet_name"
                                    type="text"
                                    placeholder="e.g., Max, Bella"
                                    autocomplete="off"
                                />
                                <InputError :message="form.errors.pet_name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="pet_breed">Pet Breed <span class="text-destructive">*</span></Label>
                                <Input
                                    id="pet_breed"
                                    v-model="form.pet_breed"
                                    type="text"
                                    required
                                    placeholder="e.g., Golden Retriever, Persian"
                                    autocomplete="off"
                                />
                                <InputError :message="form.errors.pet_breed" />
                            </div>

                            <div class="space-y-2">
                                <Label for="pet_gender">Pet Gender</Label>
                                <select
                                    id="pet_gender"
                                    v-model="form.pet_gender"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                                <InputError :message="form.errors.pet_gender" />
                            </div>

                            <div class="space-y-2">
                                <Label for="pet_birth_date">Pet Birth Date</Label>
                                <Input
                                    id="pet_birth_date"
                                    v-model="form.pet_birth_date"
                                    type="date"
                                    autocomplete="off"
                                />
                                <InputError :message="form.errors.pet_birth_date" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="pet_allergies">Pet Allergies</Label>
                            <Input
                                id="pet_allergies"
                                v-model="form.pet_allergies"
                                type="text"
                                placeholder="e.g., Peanuts, Pollen"
                                autocomplete="off"
                            />
                            <InputError :message="form.errors.pet_allergies" />
                        </div>

                        <div class="space-y-2">
                            <Label for="user_id">Owner</Label>
                            <select
                                id="user_id"
                                v-model="form.user_id"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="">Select Owner (Optional)</option>
                                <option
                                    v-for="user in users"
                                    :key="user.id"
                                    :value="user.id.toString()"
                                >
                                    {{ user.name }} ({{ user.email }})
                                </option>
                            </select>
                            <InputError :message="form.errors.user_id" />
                        </div>

                        <div class="flex justify-end gap-4">
                            <Link :href="`/admin/patients/${patient.id}`">
                                <Button type="button" variant="outline">Cancel</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                Update Patient
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
