<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { CalendarDatePicker } from '@/components/ui/calendar-date-picker';
// Textarea component - using native textarea
// Using native select element
import InputError from '@/components/InputError.vue';
import { FileText, ArrowLeft, Plus, Trash2, Search, ChevronDown, X } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { cn } from '@/lib/utils';
import { useToast } from '@/composables/useToast';
import { useDiseaseML } from '@/composables/useDiseaseML';

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
    disease_ids: number[]; // Track which diseases need this medicine
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

const { success: showSuccess, error: showError } = useToast();
const { predictDiseasesFromSymptoms } = useDiseaseML();

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
    follow_up_date: '',
});

const selectedSymptoms = ref<string[]>([]);
const selectedDiseases = ref<Disease[]>([]);
const diseaseSearchQuery = ref('');
const searchedDiseases = ref<Disease[]>([]);
const medicineRows = ref<MedicineRow[]>([]);
const medicineRowCounter = ref(0);
const newlyAddedRowIds = ref<Set<number>>(new Set());
// Track which medicines are needed by which diseases
const diseaseMedicinesMap = ref<Map<number, Set<number>>>(new Map());
const isSearchingDiseases = ref(false);
const isSymptomsModalOpen = ref(false);
const symptomSearchQuery = ref('');
const manualDiseaseSearchQuery = ref('');
const manualSearchedDiseases = ref<Disease[]>([]);
const isSearchingManualDiseases = ref(false);

// Computed property for filtered symptoms in modal
const filteredSymptoms = computed(() => {
    if (!symptomSearchQuery.value) {
        return props.symptoms;
    }
    const query = symptomSearchQuery.value.toLowerCase();
    return props.symptoms.filter(symptom => 
        symptom.name.toLowerCase().includes(query)
    );
});

// Computed property for selected symptom objects
const selectedSymptomObjects = computed(() => {
    return props.symptoms.filter(symptom => 
        selectedSymptoms.value.includes(symptom.name)
    );
});

// Computed property for top 10 predicted diseases
const topPredictedDiseases = computed(() => {
    return searchedDiseases.value.slice(0, 10);
});

// Toggle symptom selection
const toggleSymptom = (symptomName: string) => {
    const index = selectedSymptoms.value.indexOf(symptomName);
    if (index > -1) {
        selectedSymptoms.value.splice(index, 1);
    } else {
        selectedSymptoms.value.push(symptomName);
    }
};

// Remove a selected symptom
const removeSymptom = (symptomName: string, e: Event) => {
    e.stopPropagation();
    const index = selectedSymptoms.value.indexOf(symptomName);
    if (index > -1) {
        selectedSymptoms.value.splice(index, 1);
    }
};

// Open symptoms modal
const openSymptomsModal = () => {
    isSymptomsModalOpen.value = true;
    symptomSearchQuery.value = '';
};

// Add initial medicine row
const addMedicineRow = () => {
    const newId = medicineRowCounter.value++;
    medicineRows.value.push({
        id: newId,
        medicine_id: null,
        dosage: '',
        instructions: '',
        quantity: '',
        disease_ids: [], // No disease_ids for manually added medicines
    });
    // Mark as newly added so it shows even if empty
    newlyAddedRowIds.value.add(newId);
};

// Remove medicine row
const removeMedicineRow = (rowId: number) => {
    const index = medicineRows.value.findIndex(r => r.id === rowId);
    if (index > -1) {
        medicineRows.value.splice(index, 1);
        newlyAddedRowIds.value.delete(rowId);
    }
};

// Search diseases by symptoms using ML
const searchDiseasesBySymptoms = async () => {
    if (selectedSymptoms.value.length === 0) {
        searchedDiseases.value = [];
        return;
    }

    isSearchingDiseases.value = true;
    try {
        // Convert symptom names to IDs
        const symptomIds = props.symptoms
            .filter(symptom => selectedSymptoms.value.includes(symptom.name))
            .map(symptom => symptom.id);

        if (symptomIds.length === 0) {
            searchedDiseases.value = [];
            return;
        }

        // Use ML composable to predict diseases
        const predictions = await predictDiseasesFromSymptoms(symptomIds, 10);
        
        // Convert predictions to the format expected by the UI
        searchedDiseases.value = predictions.map(pred => ({
            id: pred.disease_id,
            name: pred.disease_name,
            accuracy: parseFloat(pred.accuracy.replace('%', '')),
        }));
    } catch (error) {
        console.error('Error searching diseases:', error);
        searchedDiseases.value = [];
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
    
    // Get medicines that were added for this disease
    const medicinesForDisease = diseaseMedicinesMap.value.get(diseaseId) || new Set<number>();
    
    // Remove disease from medicine rows' disease_ids
    medicineRows.value.forEach(row => {
        const index = row.disease_ids.indexOf(diseaseId);
        if (index > -1) {
            row.disease_ids.splice(index, 1);
        }
    });
    
    // Remove medicines that are no longer needed by any disease
    // Only remove medicines that were added by diseases (have disease_ids) and now have none left
    const rowsToRemove: number[] = [];
    medicineRows.value.forEach(row => {
        // If medicine was added by a disease and has no disease_ids left, remove it
        if (row.disease_ids.length === 0 && row.medicine_id !== null && medicinesForDisease.has(row.medicine_id)) {
            rowsToRemove.push(row.id);
        }
    });
    
    rowsToRemove.forEach(rowId => {
        removeMedicineRow(rowId);
    });
    
    // Clean up the disease from the map
    diseaseMedicinesMap.value.delete(diseaseId);
};

// Manual disease search
const searchDiseasesManually = async () => {
    if (!manualDiseaseSearchQuery.value || manualDiseaseSearchQuery.value.trim().length < 2) {
        manualSearchedDiseases.value = [];
        return;
    }

    isSearchingManualDiseases.value = true;
    try {
        const response = await axios.get('/search-diseases', {
            params: { keyword: manualDiseaseSearchQuery.value },
        });
        manualSearchedDiseases.value = response.data;
    } catch (error) {
        console.error('Error searching diseases manually:', error);
        manualSearchedDiseases.value = [];
    } finally {
        isSearchingManualDiseases.value = false;
    }
};

// Watch for manual disease search query changes
watch(manualDiseaseSearchQuery, () => {
    searchDiseasesManually();
});

// Load medicines for a disease
const loadMedicinesForDisease = async (diseaseId: number) => {
    try {
        const response = await axios.get(`/admin/diseases/${diseaseId}/medicines`);
        const medicines = response.data;
        
        // Track medicines for this disease
        if (!diseaseMedicinesMap.value.has(diseaseId)) {
            diseaseMedicinesMap.value.set(diseaseId, new Set());
        }
        const diseaseMedicines = diseaseMedicinesMap.value.get(diseaseId)!;
        
        medicines.forEach((medicine: Medicine) => {
            diseaseMedicines.add(medicine.id);
            
            // Find existing row with this medicine
            const existingRow = medicineRows.value.find(r => r.medicine_id === medicine.id);
            
            if (existingRow) {
                // Medicine already exists, just add this disease to its disease_ids
                if (!existingRow.disease_ids.includes(diseaseId)) {
                    existingRow.disease_ids.push(diseaseId);
                }
            } else {
                // Add new medicine row
                const newId = medicineRowCounter.value++;
                medicineRows.value.push({
                    id: newId,
                    medicine_id: medicine.id,
                    dosage: calculateDosage(medicine.dosage, form.pet_current_weight),
                    instructions: '',
                    quantity: '1 Pcs.',
                    disease_ids: [diseaseId], // Track which disease added this medicine
                });
                // Don't mark auto-added medicines as newly added since they already have medicine_id
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
        // Remove from newly added set once medicine is selected
        newlyAddedRowIds.value.delete(rowId);
        const medicine = props.medicines.find(m => m.id === medicineId);
        if (medicine && form.pet_current_weight) {
            row.dosage = calculateDosage(medicine.dosage, form.pet_current_weight);
        }
    }
};

// Handle blur on medicine select - remove empty newly added rows
const onMedicineBlur = (rowId: number) => {
    const row = medicineRows.value.find(r => r.id === rowId);
    // If row is newly added but still empty, remove it after a short delay
    // This allows the change event to fire first if user selected something
    setTimeout(() => {
        if (row && row.medicine_id === null && newlyAddedRowIds.value.has(rowId)) {
            removeMedicineRow(rowId);
        }
    }, 100);
};

// Computed property to filter out empty medicine rows (rows without medicine_id)
// But keep newly added rows visible so user can fill them
const filledMedicineRows = computed(() => {
    return medicineRows.value.filter(row => 
        row.medicine_id !== null || newlyAddedRowIds.value.has(row.id)
    );
});

// Submit form
const submit = () => {
    // Client-side validation before submission
    const validationErrors: string[] = [];
    
    if (!form.pet_current_weight || parseFloat(form.pet_current_weight) <= 0) {
        validationErrors.push('Pet weight is required and must be greater than 0');
    }
    
    if (selectedSymptoms.value.length === 0) {
        validationErrors.push('Please select at least one symptom');
    }
    
    if (selectedDiseases.value.length === 0) {
        validationErrors.push('Please select at least one disease/diagnosis');
    }
    
    // Prepare medicines data - filter out empty rows and newly added rows without medicine
    const validMedicineRows = medicineRows.value.filter(row => 
        row.medicine_id !== null && row.dosage && row.instructions && row.quantity
    );
    
    if (validMedicineRows.length === 0) {
        validationErrors.push('Please add at least one medicine with dosage, instructions, and quantity');
    }
    
    // If there are client-side validation errors, show them and don't submit
    if (validationErrors.length > 0) {
        showError('Error creating prescription', validationErrors.join('. ') + '.');
        return;
    }
    
    // Sync disease_ids from selectedDiseases before submission
    form.disease_ids = selectedDiseases.value.map(d => d.id);
    
    // Map to the structure expected by the backend
    // Backend expects 'id' not 'medicine_id' for the medicine identifier
    form.medicines = validMedicineRows.map(row => ({
        id: row.medicine_id!, // Backend expects 'id' field to contain medicine_id
        dosage: row.dosage,
        instructions: row.instructions,
        quantity: row.quantity,
    })) as any;

    form.post(`/admin/appointments/${props.appointment.id}/prescribe`, {
        onSuccess: () => {
            showSuccess('Prescription created successfully!', 'The prescription has been saved and the pet owner will be notified.');
        },
        onError: (errors) => {
            // Show error toast for general errors
            let errorMessage = 'Failed to create prescription. Please check the form for errors.';
            
            // Build detailed error message from validation errors
            if (errors && typeof errors === 'object') {
                const errorMessages: string[] = [];
                
                if (errors.disease_ids) {
                    errorMessages.push('Please select at least one disease/diagnosis');
                }
                if (errors.medicines) {
                    errorMessages.push('Please add at least one medicine with all required fields');
                }
                if (errors.symptoms) {
                    errorMessages.push('Please select at least one symptom');
                }
                if (errors.pet_current_weight) {
                    errorMessages.push('Please enter a valid pet weight');
                }
                
                // Add any other specific error messages
                Object.keys(errors).forEach(key => {
                    if (!['disease_ids', 'medicines', 'symptoms', 'pet_current_weight'].includes(key) && errors[key]) {
                        errorMessages.push(Array.isArray(errors[key]) ? errors[key][0] : String(errors[key]));
                    }
                });
                
                if (errorMessages.length > 0) {
                    errorMessage = errorMessages.join('. ') + '.';
                } else if (errors.message) {
                    errorMessage = errors.message;
                }
            } else if (typeof errors === 'string') {
                errorMessage = errors;
            }
            
            showError('Error creating prescription', errorMessage);
            console.error('Error creating prescription:', errors);
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
                            
                            <!-- Multi-select input trigger -->
                            <div
                                @click="openSymptomsModal"
                                :class="cn(
                                    'flex min-h-10 w-full flex-wrap items-center gap-2 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background',
                                    'cursor-pointer hover:ring-2 hover:ring-ring/50 transition-all',
                                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2'
                                )"
                            >
                                <!-- Selected symptoms as badges -->
                                <div v-if="selectedSymptomObjects.length > 0" class="flex flex-wrap gap-1.5 flex-1">
                                    <Badge
                                        v-for="symptom in selectedSymptomObjects"
                                        :key="symptom.id"
                                        variant="default"
                                        class="flex items-center gap-1.5 px-2 py-0.5"
                                    >
                                        <span>{{ symptom.name }}</span>
                                        <button
                                            type="button"
                                            @click="removeSymptom(symptom.name, $event)"
                                            class="ml-0.5 rounded-sm hover:bg-primary/20 focus:outline-none focus:ring-1 focus:ring-ring"
                                        >
                                            <X class="h-3 w-3" />
                                        </button>
                                    </Badge>
                                </div>
                                
                                <!-- Placeholder when nothing selected -->
                                <span
                                    v-else
                                    class="text-muted-foreground flex-1"
                                >
                                    Click to select symptoms
                                </span>
                                
                                <!-- Dropdown icon -->
                                <ChevronDown class="h-4 w-4 text-muted-foreground shrink-0" />
                            </div>
                            
                            <InputError :message="form.errors.symptoms" />

                            <!-- Symptoms Modal -->
                            <Dialog v-model:open="isSymptomsModalOpen">
                                <DialogContent class="w-full md:w-1/2 max-w-none max-h-[600px] flex flex-col">
                                    <DialogHeader>
                                        <DialogTitle>Select Symptoms</DialogTitle>
                                    </DialogHeader>
                                    
                                    <!-- Search input -->
                                    <div class="relative">
                                        <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            v-model="symptomSearchQuery"
                                            type="text"
                                            placeholder="Search symptoms..."
                                            class="pl-9"
                                        />
                                    </div>

                                    <!-- Symptoms grid -->
                                    <div class="flex-1 overflow-y-auto border rounded-md p-4">
                                        <div v-if="filteredSymptoms.length === 0" class="text-center py-8 text-muted-foreground">
                                            No symptoms found
                                        </div>
                                        <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                                            <Button
                                                v-for="symptom in filteredSymptoms"
                                                :key="symptom.id"
                                                type="button"
                                                :variant="selectedSymptoms.includes(symptom.name) ? 'default' : 'outline'"
                                                size="sm"
                                                class="justify-start h-auto py-2 whitespace-normal text-left"
                                                @click="toggleSymptom(symptom.name)"
                                            >
                                                {{ symptom.name }}
                                            </Button>
                                        </div>
                                    </div>

                                    <!-- Footer with selected count and close button -->
                                    <div class="flex items-center justify-between pt-4 border-t">
                                        <p class="text-sm text-muted-foreground">
                                            {{ selectedSymptoms.length }} symptom{{ selectedSymptoms.length !== 1 ? 's' : '' }} selected
                                        </p>
                                        <Button 
                                            type="button" 
                                            @click="isSymptomsModalOpen = false"
                                        >
                                            Done
                                        </Button>
                                    </div>
                                </DialogContent>
                            </Dialog>
                        </div>

                        <!-- Loading Indicator for Predicted Diseases -->
                        <div v-if="isSearchingDiseases" class="space-y-2">
                            <Label>Predicted Diseases (based on symptoms)</Label>
                            <Card>
                                <CardContent class="p-8">
                                    <div class="flex flex-col items-center justify-center gap-3">
                                        <Spinner class="h-8 w-8 text-primary" />
                                        <p class="text-sm text-muted-foreground">Analyzing symptoms and predicting diseases...</p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Disease Search Results -->
                        <div v-else-if="searchedDiseases.length > 0" class="space-y-2">
                            <Label>Predicted Diseases (based on symptoms) - Top 10</Label>
                            <div class="grid grid-cols-2 gap-2">
                                <Card
                                    v-for="disease in topPredictedDiseases"
                                    :key="disease.id"
                                    class="cursor-pointer hover:bg-muted py-0"
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

                        <!-- Manual Disease Search -->
                        <div class="space-y-2">
                            <Label>Add Disease Manually</Label>
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input
                                    v-model="manualDiseaseSearchQuery"
                                    type="text"
                                    placeholder="Search for diseases by name..."
                                    class="pl-9"
                                />
                            </div>

                            <!-- Manual Search Loading -->
                            <div v-if="isSearchingManualDiseases" class="py-4">
                                <div class="flex items-center justify-center gap-2">
                                    <Spinner class="h-4 w-4 text-primary" />
                                    <p class="text-sm text-muted-foreground">Searching diseases...</p>
                                </div>
                            </div>

                            <!-- Manual Search Results -->
                            <div v-else-if="manualSearchedDiseases.length > 0" class="border rounded-md p-2 max-h-[200px] overflow-y-auto">
                                <div class="grid grid-cols-1 gap-1">
                                    <button
                                        v-for="disease in manualSearchedDiseases"
                                        :key="disease.id"
                                        type="button"
                                        @click="addDisease(disease)"
                                        :disabled="!!selectedDiseases.find(d => d.id === disease.id)"
                                        :class="cn(
                                            'flex items-center justify-between px-3 py-2 text-sm rounded-md transition-colors text-left',
                                            selectedDiseases.find(d => d.id === disease.id)
                                                ? 'bg-primary/10 text-primary cursor-not-allowed'
                                                : 'hover:bg-muted cursor-pointer'
                                        )"
                                    >
                                        <span>{{ disease.name }}</span>
                                        <span v-if="selectedDiseases.find(d => d.id === disease.id)" class="text-xs text-primary">
                                            Added
                                        </span>
                                        <Plus v-else class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            <!-- No results message -->
                            <div v-else-if="manualDiseaseSearchQuery && manualDiseaseSearchQuery.length >= 2 && !isSearchingManualDiseases" class="text-center py-4 text-sm text-muted-foreground">
                                No diseases found matching "{{ manualDiseaseSearchQuery }}"
                            </div>

                            <!-- Help text -->
                            <p v-if="!manualDiseaseSearchQuery" class="text-xs text-muted-foreground">
                                Type at least 2 characters to search for diseases
                            </p>
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
                                            v-for="row in filledMedicineRows"
                                            :key="row.id"
                                            class="border-b"
                                        >
                                            <td class="p-2">
                                                <select
                                                    :value="row.medicine_id?.toString() || ''"
                                                    @change="(e) => onMedicineChange(row.id, parseInt((e.target as HTMLSelectElement).value))"
                                                    @blur="onMedicineBlur(row.id)"
                                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                                >
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

                        <!-- Follow-up Date -->
                        <div class="space-y-2">
                            <Label for="follow_up_date">Follow-up Checkup Date (Optional)</Label>
                            <CalendarDatePicker
                                v-model="form.follow_up_date"
                                id="follow_up_date"
                            />
                            <p class="text-xs text-muted-foreground">
                                Schedule a follow-up checkup date. A reminder will be sent to the pet owner 3 days before the scheduled date.
                            </p>
                            <InputError :message="form.errors.follow_up_date" />
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
