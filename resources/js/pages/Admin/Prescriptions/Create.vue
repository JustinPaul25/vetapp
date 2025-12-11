<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
// Textarea component - using native textarea
// Using native select element
import InputError from '@/components/InputError.vue';
import { FileText, ArrowLeft, Plus, Trash2, Search } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { ref, computed, watch } from 'vue';
import axios from 'axios';

interface Symptom {
    id: number;
    name: string;
}

interface Medicine {
    id: number;
    name: string;
    dosage: string;
    stock: number;
}

interface Disease {
    id: number;
    name: string;
    accuracy?: number;
}

interface MedicineRow {
    id: number;
    medicine_id: number | null;
    dosage: string;
    instructions: string;
    quantity: string;
}

interface Props {
    appointment: {
        id: number;
        appointment_date: string;
        appointment_time: string;
        appointment_type: string;
    };
    patient: {
        id: number;
        pet_name: string;
        pet_breed: string;
        pet_type: string;
        pet_birth_date: string | null;
    };
    medicines: Medicine[];
    symptoms: Symptom[];
    instructions: string[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Prescriptions', href: '/admin/prescriptions' },
    { title: 'Create Prescription', href: '#' },
];

const form = router.form({
    pet_current_weight: '',
    symptoms: [] as string[],
    disease_ids: [] as number[],
    medicines: [] as MedicineRow[],
    notes: '',
});

const selectedSymptoms = ref<string[]>([]);
const selectedDiseases = ref<Disease[]>([]);
const diseaseSearchQuery = ref('');
const searchedDiseases = ref<Disease[]>([]);
const medicineRows = ref<MedicineRow[]>([]);
const medicineRowCounter = ref(0);
const isSearchingDiseases = ref(false);

// Add initial medicine row
const addMedicineRow = () => {
    medicineRows.value.push({
        id: medicineRowCounter.value++,
        medicine_id: null,
        dosage: '',
        instructions: '',
        quantity: '',
    });
};

// Remove medicine row
const removeMedicineRow = (rowId: number) => {
    const index = medicineRows.value.findIndex(r => r.id === rowId);
    if (index > -1) {
        medicineRows.value.splice(index, 1);
    }
};

// Search diseases by symptoms
const searchDiseasesBySymptoms = async () => {
    if (selectedSymptoms.value.length === 0) {
        searchedDiseases.value = [];
        return;
    }

    isSearchingDiseases.value = true;
    try {
        const response = await axios.get('/admin/diseases/search-by-symptoms', {
            params: { symptoms: selectedSymptoms.value },
        });
        searchedDiseases.value = response.data;
    } catch (error) {
        console.error('Error searching diseases:', error);
    } finally {
        isSearchingDiseases.value = false;
    }
};

// Watch for symptom changes and search diseases
watch(selectedSymptoms, () => {
    form.symptoms = selectedSymptoms.value;
    searchDiseasesBySymptoms();
}, { deep: true });

// Add disease to selected
const addDisease = (disease: Disease) => {
    if (!selectedDiseases.value.find(d => d.id === disease.id)) {
        selectedDiseases.value.push(disease);
        form.disease_ids = selectedDiseases.value.map(d => d.id);
        loadMedicinesForDisease(disease.id);
    }
};

// Remove disease from selected
const removeDisease = (diseaseId: number) => {
    selectedDiseases.value = selectedDiseases.value.filter(d => d.id !== diseaseId);
    form.disease_ids = selectedDiseases.value.map(d => d.id);
};

// Load medicines for a disease
const loadMedicinesForDisease = async (diseaseId: number) => {
    try {
        const response = await axios.get(`/admin/diseases/${diseaseId}/medicines`);
        const medicines = response.data;
        
        // Add medicines that aren't already in the rows
        medicines.forEach((medicine: Medicine) => {
            const exists = medicineRows.value.some(r => r.medicine_id === medicine.id);
            if (!exists) {
                medicineRows.value.push({
                    id: medicineRowCounter.value++,
                    medicine_id: medicine.id,
                    dosage: calculateDosage(medicine.dosage, form.pet_current_weight),
                    instructions: '',
                    quantity: '1 Pcs.',
                });
            }
        });
    } catch (error) {
        console.error('Error loading medicines:', error);
    }
};

// Calculate dosage based on weight
const calculateDosage = (dosagePattern: string, weight: string): string => {
    if (!weight || !dosagePattern) return '';
    
    const weightNum = parseFloat(weight);
    if (isNaN(weightNum)) return '';

    // Pattern: "1ml/10 Kg" or "1ml/10Kg"
    const match = dosagePattern.match(/(\d+(?:\.\d+)?)\s*ml\s*\/\s*(\d+(?:\.\d+)?)\s*Kg/i);
    if (match) {
        const ml = parseFloat(match[1]);
        const perKg = parseFloat(match[2]);
        const calculated = (weightNum / perKg) * ml;
        return `${calculated.toFixed(2)}ml`;
    }

    return dosagePattern;
};

// Watch weight changes and recalculate dosages
watch(() => form.pet_current_weight, (newWeight) => {
    medicineRows.value.forEach(row => {
        if (row.medicine_id) {
            const medicine = props.medicines.find(m => m.id === row.medicine_id);
            if (medicine) {
                row.dosage = calculateDosage(medicine.dosage, newWeight);
            }
        }
    });
});

// Watch medicine selection and calculate dosage
const onMedicineChange = (rowId: number, medicineId: number) => {
    const row = medicineRows.value.find(r => r.id === rowId);
    if (row) {
        row.medicine_id = medicineId;
        const medicine = props.medicines.find(m => m.id === medicineId);
        if (medicine && form.pet_current_weight) {
            row.dosage = calculateDosage(medicine.dosage, form.pet_current_weight);
        }
    }
};

// Initialize with one medicine row
addMedicineRow();

// Submit form
const submit = () => {
    // Prepare medicines data
    form.medicines = medicineRows.value
        .filter(row => row.medicine_id && row.dosage && row.instructions && row.quantity)
        .map(row => ({
            id: row.medicine_id!,
            dosage: row.dosage,
            instructions: row.instructions,
            quantity: row.quantity,
        }));

    form.post(`/admin/appointments/${props.appointment.id}/prescribe`, {
        onSuccess: () => {
            router.visit('/admin/prescriptions');
        },
    });
};
</script>

<template>
    <Head title="Create Prescription" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-6xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Link href="/admin/prescriptions">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft class="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <FileText class="h-5 w-5" />
                                Create Prescription
                            </CardTitle>
                            <CardDescription>
                                Create a new prescription for appointment #{{ appointment.id }}
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Appointment & Patient Info -->
                        <div class="grid grid-cols-2 gap-4 p-4 bg-muted rounded-lg">
                            <div>
                                <Label class="text-xs text-muted-foreground">Appointment Date</Label>
                                <p class="font-medium">{{ appointment.appointment_date }} {{ appointment.appointment_time }}</p>
                            </div>
                            <div>
                                <Label class="text-xs text-muted-foreground">Appointment Type</Label>
                                <p class="font-medium">{{ appointment.appointment_type }}</p>
                            </div>
                            <div>
                                <Label class="text-xs text-muted-foreground">Pet Name</Label>
                                <p class="font-medium">{{ patient.pet_name }}</p>
                            </div>
                            <div>
                                <Label class="text-xs text-muted-foreground">Pet Type & Breed</Label>
                                <p class="font-medium">{{ patient.pet_type }} - {{ patient.pet_breed }}</p>
                            </div>
                        </div>

                        <!-- Pet Weight -->
                        <div class="space-y-2">
                            <Label for="pet_weight">Pet Current Weight (Kg) *</Label>
                            <Input
                                id="pet_weight"
                                v-model="form.pet_current_weight"
                                type="number"
                                step="0.1"
                                required
                                placeholder="e.g., 10.5"
                            />
                            <InputError :message="form.errors.pet_current_weight" />
                        </div>

                        <!-- Symptoms Selection -->
                        <div class="space-y-2">
                            <Label>Symptoms *</Label>
                            <div class="flex flex-wrap gap-2 mb-2">
                                <Button
                                    v-for="symptom in symptoms"
                                    :key="symptom.id"
                                    type="button"
                                    :variant="selectedSymptoms.includes(symptom.name) ? 'default' : 'outline'"
                                    size="sm"
                                    @click="
                                        selectedSymptoms.includes(symptom.name)
                                            ? selectedSymptoms = selectedSymptoms.filter(s => s !== symptom.name)
                                            : selectedSymptoms.push(symptom.name)
                                    "
                                >
                                    {{ symptom.name }}
                                </Button>
                            </div>
                            <InputError :message="form.errors.symptoms" />
                        </div>

                        <!-- Disease Search Results -->
                        <div v-if="searchedDiseases.length > 0" class="space-y-2">
                            <Label>Predicted Diseases (based on symptoms)</Label>
                            <div class="grid grid-cols-2 gap-2">
                                <Card
                                    v-for="disease in searchedDiseases"
                                    :key="disease.id"
                                    class="cursor-pointer hover:bg-muted"
                                    :class="{ 'ring-2 ring-primary': selectedDiseases.find(d => d.id === disease.id) }"
                                    @click="addDisease(disease)"
                                >
                                    <CardContent class="p-3">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="font-medium">{{ disease.name }}</p>
                                                <p v-if="disease.accuracy" class="text-xs text-muted-foreground">
                                                    Accuracy: {{ disease.accuracy }}%
                                                </p>
                                            </div>
                                            <Button
                                                v-if="selectedDiseases.find(d => d.id === disease.id)"
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                @click.stop="removeDisease(disease.id)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>

                        <!-- Selected Diseases -->
                        <div v-if="selectedDiseases.length > 0" class="space-y-2">
                            <Label>Selected Diagnoses *</Label>
                            <div class="flex flex-wrap gap-2">
                                <div
                                    v-for="disease in selectedDiseases"
                                    :key="disease.id"
                                    class="flex items-center gap-2 px-3 py-1 bg-primary/10 rounded-md"
                                >
                                    <span class="text-sm font-medium">{{ disease.name }}</span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        @click="removeDisease(disease.id)"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                    </Button>
                                </div>
                            </div>
                            <InputError :message="form.errors.disease_ids" />
                        </div>

                        <!-- Medicines Table -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label>Medicines *</Label>
                                <Button type="button" variant="outline" size="sm" @click="addMedicineRow">
                                    <Plus class="h-4 w-4 mr-2" />
                                    Add Medicine
                                </Button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full border-collapse">
                                    <thead>
                                        <tr class="border-b">
                                            <th class="text-left p-2 font-semibold">Medicine</th>
                                            <th class="text-left p-2 font-semibold">Dosage</th>
                                            <th class="text-left p-2 font-semibold">Instructions</th>
                                            <th class="text-left p-2 font-semibold">Quantity</th>
                                            <th class="text-right p-2 font-semibold">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="row in medicineRows"
                                            :key="row.id"
                                            class="border-b"
                                        >
                                            <td class="p-2">
                                                <select
                                                    :value="row.medicine_id?.toString() || ''"
                                                    @change="(e) => onMedicineChange(row.id, parseInt((e.target as HTMLSelectElement).value))"
                                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    <option value="">Select medicine</option>
                                                    <option
                                                        v-for="medicine in medicines"
                                                        :key="medicine.id"
                                                        :value="medicine.id.toString()"
                                                    >
                                                        {{ medicine.name }} ({{ medicine.dosage }})
                                                    </option>
                                                </select>
                                            </td>
                                            <td class="p-2">
                                                <Input
                                                    v-model="row.dosage"
                                                    type="text"
                                                    placeholder="Auto-calculated"
                                                />
                                            </td>
                                            <td class="p-2">
                                                <Input
                                                    v-model="row.instructions"
                                                    type="text"
                                                    placeholder="e.g., After meals"
                                                    list="instructions-list"
                                                />
                                                <datalist id="instructions-list">
                                                    <option
                                                        v-for="instruction in instructions"
                                                        :key="instruction"
                                                        :value="instruction"
                                                    />
                                                </datalist>
                                            </td>
                                            <td class="p-2">
                                                <Input
                                                    v-model="row.quantity"
                                                    type="text"
                                                    placeholder="e.g., 1 Bottle"
                                                />
                                            </td>
                                            <td class="p-2">
                                                <div class="flex justify-end">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        @click="removeMedicineRow(row.id)"
                                                    >
                                                        <Trash2 class="h-4 w-4 text-destructive" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <InputError :message="form.errors.medicines" />
                        </div>

                        <!-- Notes -->
                        <div class="space-y-2">
                            <Label for="notes">Notes</Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Additional notes or instructions..."
                            />
                            <InputError :message="form.errors.notes" />
                        </div>

                        <!-- Submit Buttons -->
                        <div class="flex justify-end gap-4">
                            <Link href="/admin/prescriptions">
                                <Button type="button" variant="outline">Cancel</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                Create Prescription
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
