<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Form, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import { Stethoscope, ArrowLeft } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';

interface Symptom {
    id: number;
    name: string;
}

interface Medicine {
    id: number;
    name: string;
    dosage: string;
}

interface Disease {
    id: number;
    name: string;
    home_remedy: string | null;
    symptoms: number[];
    medicines: number[];
}

interface Props {
    disease: Disease;
    allSymptoms: Symptom[];
    allMedicines: Medicine[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Diseases', href: '/admin/diseases' },
    { title: props.disease.name, href: `/admin/diseases/${props.disease.id}` },
    { title: 'Edit', href: '#' },
];

const form = router.form({
    name: props.disease.name,
    symptoms: [...props.disease.symptoms] as number[],
    medicines: [...props.disease.medicines] as number[],
    home_remedy: props.disease.home_remedy || '',
});

const toggleSymptom = (symptomId: number) => {
    const index = form.symptoms.indexOf(symptomId);
    if (index > -1) {
        form.symptoms.splice(index, 1);
    } else {
        form.symptoms.push(symptomId);
    }
};

const toggleMedicine = (medicineId: number) => {
    const index = form.medicines.indexOf(medicineId);
    if (index > -1) {
        form.medicines.splice(index, 1);
    } else {
        form.medicines.push(medicineId);
    }
};

const submit = () => {
    form.put(`/admin/diseases/${props.disease.id}`);
};
</script>

<template>
    <Head :title="`Edit ${disease.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-4xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Link :href="`/admin/diseases/${disease.id}`">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft class="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Stethoscope class="h-5 w-5" />
                                Edit Disease
                            </CardTitle>
                            <CardDescription>
                                Update disease information, symptoms, and medicines
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Form @submit.prevent="submit" class="space-y-6">
                        <div class="space-y-2">
                            <Label for="name">Disease Name</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="e.g., Canine Distemper"
                                autocomplete="off"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="home_remedy">Home Remedy</Label>
                            <Textarea
                                id="home_remedy"
                                v-model="form.home_remedy"
                                placeholder="Describe home remedies or care instructions..."
                                rows="4"
                            />
                            <InputError :message="form.errors.home_remedy" />
                            <p class="text-sm text-muted-foreground">Optional. Maximum 1825 characters.</p>
                        </div>

                        <div class="space-y-3">
                            <Label>Associated Symptoms</Label>
                            <InputError :message="form.errors.symptoms" />
                            <div class="border rounded-md p-4 max-h-64 overflow-y-auto">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div
                                        v-for="symptom in allSymptoms"
                                        :key="symptom.id"
                                        class="flex items-center space-x-2"
                                    >
                                        <Checkbox
                                            :id="`symptom-${symptom.id}`"
                                            :checked="form.symptoms.includes(symptom.id)"
                                            @update:checked="() => toggleSymptom(symptom.id)"
                                        />
                                        <label
                                            :for="`symptom-${symptom.id}`"
                                            class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                                        >
                                            {{ symptom.name }}
                                        </label>
                                    </div>
                                </div>
                                <div v-if="allSymptoms.length === 0" class="text-sm text-muted-foreground text-center py-4">
                                    No symptoms available. Please add symptoms first.
                                </div>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Selected: {{ form.symptoms.length }} symptom{{ form.symptoms.length !== 1 ? 's' : '' }}
                            </p>
                        </div>

                        <div class="space-y-3">
                            <Label>Recommended Medicines</Label>
                            <InputError :message="form.errors.medicines" />
                            <div class="border rounded-md p-4 max-h-64 overflow-y-auto">
                                <div class="space-y-3">
                                    <div
                                        v-for="medicine in allMedicines"
                                        :key="medicine.id"
                                        class="flex items-start space-x-2"
                                    >
                                        <Checkbox
                                            :id="`medicine-${medicine.id}`"
                                            :checked="form.medicines.includes(medicine.id)"
                                            @update:checked="() => toggleMedicine(medicine.id)"
                                            class="mt-1"
                                        />
                                        <label
                                            :for="`medicine-${medicine.id}`"
                                            class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer flex-1"
                                        >
                                            <div>{{ medicine.name }}</div>
                                            <div class="text-xs text-muted-foreground mt-0.5">{{ medicine.dosage }}</div>
                                        </label>
                                    </div>
                                </div>
                                <div v-if="allMedicines.length === 0" class="text-sm text-muted-foreground text-center py-4">
                                    No medicines available. Please add medicines first.
                                </div>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Selected: {{ form.medicines.length }} medicine{{ form.medicines.length !== 1 ? 's' : '' }}
                            </p>
                        </div>

                        <div class="flex justify-end gap-4">
                            <Link :href="`/admin/diseases/${disease.id}`">
                                <Button type="button" variant="outline">Cancel</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                Update Disease
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

