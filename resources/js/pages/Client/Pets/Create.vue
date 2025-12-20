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
import { computed, watch, ref } from 'vue';

interface PetType {
    id: number;
    name: string;
}

interface Props {
    pet_types: PetType[];
    pet_breeds: Record<string, string[]>;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'My Pets', href: '/pets' },
    { title: 'Add Pet', href: '#' },
];

const form = router.form({
    pet_type_id: '',
    custom_pet_type_name: '', // For new custom pet types
    pet_name: '',
    pet_breed: '',
    custom_pet_breed_name: '', // For new custom breeds
    pet_gender: '',
    pet_birth_date: '',
    microchip_number: '',
    pet_allergies: '',
});

// Custom pet type display value (shown when creating new)
const customPetTypeDisplay = ref<string | null>(null);

// Custom pet breed display value (shown when creating new)
const customBreedDisplay = ref<string | null>(null);

// Handle creating a new pet type
const handleCreatePetType = (name: string) => {
    form.custom_pet_type_name = name;
    customPetTypeDisplay.value = name;
};

// Handle creating a new breed
const handleCreateBreed = (name: string) => {
    form.custom_pet_breed_name = name;
    customBreedDisplay.value = name;
};

const submit = () => {
    form.post('/pets');
};

// Transform pet types for SearchableSelect
const petTypeOptions = computed(() => {
    return props.pet_types.map(pt => ({
        value: pt.id.toString(),
        label: pt.name,
    }));
});

// Get the selected pet type name
const selectedPetTypeName = computed(() => {
    if (!form.pet_type_id) return null;
    const petType = props.pet_types.find(pt => pt.id.toString() === form.pet_type_id);
    return petType?.name || null;
});

// Get breed options based on selected pet type
const breedOptions = computed(() => {
    if (!selectedPetTypeName.value || !props.pet_breeds[selectedPetTypeName.value]) {
        return [];
    }
    return props.pet_breeds[selectedPetTypeName.value].map(breed => ({
        value: breed,
        label: breed,
    }));
});

// Clear breed selection when pet type changes
watch(() => form.pet_type_id, (newValue) => {
    form.pet_breed = '';
    form.custom_pet_breed_name = '';
    customBreedDisplay.value = null;
    // Clear custom pet type when selecting an existing type
    if (newValue !== '__new__') {
        form.custom_pet_type_name = '';
        customPetTypeDisplay.value = null;
    }
});

// Clear custom breed when selecting an existing breed
watch(() => form.pet_breed, (newValue) => {
    if (newValue !== '__new__') {
        form.custom_pet_breed_name = '';
        customBreedDisplay.value = null;
    }
});
</script>

<template>
    <Head title="Add Pet" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-4xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Link href="/pets">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft class="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Heart class="h-5 w-5" />
                                Register New Pet
                            </CardTitle>
                            <CardDescription>
                                Add a new pet to your account
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
                                    :allow-create="true"
                                    create-prefix="Add new type"
                                    :custom-value="customPetTypeDisplay"
                                    @update:custom-value="(val) => customPetTypeDisplay = val"
                                    @create="handleCreatePetType"
                                />
                                <p v-if="customPetTypeDisplay" class="text-xs text-blue-600">
                                    New pet type "{{ customPetTypeDisplay }}" will be created when you submit the form.
                                </p>
                                <InputError :message="form.errors.pet_type_id" />
                                <InputError :message="form.errors.custom_pet_type_name" />
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
                                <SearchableSelect
                                    id="pet_breed"
                                    v-model="form.pet_breed"
                                    :options="breedOptions"
                                    placeholder="Select Pet Breed"
                                    search-placeholder="Search breeds..."
                                    :required="true"
                                    :disabled="!selectedPetTypeName && !customPetTypeDisplay"
                                    :allow-create="!!selectedPetTypeName || !!customPetTypeDisplay"
                                    create-prefix="Add new breed"
                                    :custom-value="customBreedDisplay"
                                    @update:custom-value="(val) => customBreedDisplay = val"
                                    @create="handleCreateBreed"
                                />
                                <p v-if="customBreedDisplay" class="text-xs text-blue-600">
                                    New breed "{{ customBreedDisplay }}" will be created when you submit the form.
                                </p>
                                <InputError :message="form.errors.pet_breed" />
                                <InputError :message="form.errors.custom_pet_breed_name" />
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

                            <div class="space-y-2">
                                <Label for="microchip_number">Microchip Number</Label>
                                <Input
                                    id="microchip_number"
                                    v-model="form.microchip_number"
                                    type="text"
                                    placeholder="Microchip number"
                                    autocomplete="off"
                                />
                                <InputError :message="form.errors.microchip_number" />
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

                        <div class="flex justify-end gap-4">
                            <Link href="/pets">
                                <Button type="button" variant="outline">Cancel</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                Register Pet
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>















