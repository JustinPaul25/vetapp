<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { SearchableSelect } from '@/components/ui/searchable-select';
import InputError from '@/components/InputError.vue';
import { PawPrint, ArrowLeft } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { computed } from 'vue';

interface PetType {
    id: number;
    name: string;
}

interface PetBreed {
    id: number;
    name: string;
    pet_type_id: number;
}

interface Props {
    pet_breed: PetBreed;
    pet_types: PetType[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Pet Breeds', href: '/admin/pet_breeds' },
    { title: 'Edit Pet Breed', href: '#' },
];

const petTypeOptions = computed(() =>
    props.pet_types.map(pt => ({ value: String(pt.id), label: pt.name }))
);

const form = router.form({
    name: props.pet_breed.name,
    pet_type_id: String(props.pet_breed.pet_type_id),
});

const submit = () => {
    form.put(`/admin/pet_breeds/${props.pet_breed.id}`);
};
</script>

<template>
    <Head title="Edit Pet Breed" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-2xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Link href="/admin/pet_breeds">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft class="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <PawPrint class="h-5 w-5" />
                                Edit Pet Breed
                            </CardTitle>
                            <CardDescription>
                                Update pet breed information
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="space-y-2">
                            <Label for="pet_type_id">Pet Type</Label>
                            <SearchableSelect
                                v-model="form.pet_type_id"
                                :options="petTypeOptions"
                                placeholder="Select a pet type"
                                :required="true"
                            />
                            <InputError :message="form.errors.pet_type_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="name">Breed Name</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="e.g., Golden Retriever, Persian, Holland Lop"
                                autocomplete="off"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="flex justify-end gap-4">
                            <Link href="/admin/pet_breeds">
                                <Button type="button" variant="outline">Cancel</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                Update Pet Breed
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>






