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

interface PatientOption {
    id: number;
    pet_name: string;
    pet_breed: string;
    pet_type: string;
    has_prescription: boolean;
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
    patients?: PatientOption[]; // All pets in the appointment
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
    patient_id: props.patient.id, // Include patient_id in form
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
const isMedicineModalOpen = ref(false);
const medicineModalRowId = ref<number | null>(null);
const medicineSearchQuery = ref('');
const isInstructionsModalOpen = ref(false);
const instructionsModalRowId = ref<number | null>(null);
const instructionsSearchQuery = ref('');
const customInstruction = ref('');

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

// Computed property for filtered medicines in modal
const filteredMedicines = computed(() => {
    if (!medicineSearchQuery.value) {
        return props.medicines;
    }
    const query = medicineSearchQuery.value.toLowerCase();
    return props.medicines.filter(medicine => 
        medicine.name.toLowerCase().includes(query) ||
        medicine.dosage.toLowerCase().includes(query)
    );
});

// Computed property for filtered instructions in modal
const filteredInstructions = computed(() => {
    if (!instructionsSearchQuery.value) {
        return props.instructions;
    }
    const query = instructionsSearchQuery.value.toLowerCase();
    return props.instructions.filter(instruction => 
        instruction.toLowerCase().includes(query)
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

// Open medicine modal for a specific row
const openMedicineModal = (rowId: number) => {
    medicineModalRowId.value = rowId;
    isMedicineModalOpen.value = true;
    medicineSearchQuery.value = '';
};

// Close medicine modal
const closeMedicineModal = () => {
    isMedicineModalOpen.value = false;
    medicineModalRowId.value = null;
    medicineSearchQuery.value = '';
};

// Select medicine from modal
const selectMedicineFromModal = (medicine: Medicine) => {
    if (medicineModalRowId.value === null) return;
    
    const row = medicineRows.value.find(r => r.id === medicineModalRowId.value);
    if (row) {
        onMedicineChange(medicineModalRowId.value, medicine.id);
    }
    
    closeMedicineModal();
};

// Get selected medicine name for display
const getSelectedMedicineName = (rowId: number): string => {
    const row = medicineRows.value.find(r => r.id === rowId);
    if (!row || !row.medicine_id) return '';
    
    const medicine = props.medicines.find(m => m.id === row.medicine_id);
    if (!medicine) return '';
    
    // Only show dosage in parentheses if it exists and is meaningful
    if (medicine.dosage && medicine.dosage.trim() !== '') {
        return `${medicine.name} (${medicine.dosage})`;
    }
    
    return medicine.name;
};

// Open instructions modal for a specific row
const openInstructionsModal = (rowId: number) => {
    instructionsModalRowId.value = rowId;
    isInstructionsModalOpen.value = true;
    instructionsSearchQuery.value = '';
    const row = medicineRows.value.find(r => r.id === rowId);
    customInstruction.value = row?.instructions || '';
};

// Close instructions modal
const closeInstructionsModal = () => {
    isInstructionsModalOpen.value = false;
    instructionsModalRowId.value = null;
    instructionsSearchQuery.value = '';
    customInstruction.value = '';
};

// Select instruction from modal
const selectInstructionFromModal = (instruction: string) => {
    if (instructionsModalRowId.value === null) return;
    
    const row = medicineRows.value.find(r => r.id === instructionsModalRowId.value);
    if (row) {
        row.instructions = instruction;
    }
    
    closeInstructionsModal();
};

// Apply custom instruction
const applyCustomInstruction = () => {
    if (!customInstruction.value.trim() || instructionsModalRowId.value === null) return;
    selectInstructionFromModal(customInstruction.value.trim());
};

// Add initial medicine row
const addMedicineRow = () => {
    const newId = medicineRowCounter.value++;
    // Set default instruction value - user can modify it
    const defaultInstruction = props.instructions && props.instructions.length > 0 
        ? props.instructions[0] // Use first instruction from props if available
        : 'After meals'; // Default fallback
    
    medicineRows.value.push({
        id: newId,
        medicine_id: null,
        dosage: '',
        instructions: defaultInstruction, // Set default instruction instead of empty string
        quantity: '1 Pcs.', // Default quantity
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

// Search diseases by symptoms using ML with fallback to backend API
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

        // Try ML prediction first
        let predictions: any[] = [];
        try {
            const mlPredictions = await predictDiseasesFromSymptoms(symptomIds, 10);
            predictions = mlPredictions.map(pred => ({
                id: pred.disease_id,
                name: pred.disease_name,
                accuracy: parseFloat(pred.accuracy.replace('%', '')),
            }));
        } catch (mlError) {
            console.warn('ML prediction failed, falling back to backend API:', mlError);
        }

        // If ML returns no results or fails, use backend API as fallback
        if (predictions.length === 0) {
            try {
                const response = await axios.get('/admin/diseases/search-by-symptoms', {
                    params: { symptoms: selectedSymptoms.value }
                });
                
                if (response.data && Array.isArray(response.data)) {
                    predictions = response.data.map((disease: any) => ({
                        id: disease.id,
                        name: disease.name,
                        accuracy: disease.accuracy || 0,
                    }));
                }
            } catch (apiError) {
                console.error('Backend API fallback also failed:', apiError);
            }
        }
        
        searchedDiseases.value = predictions;
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
                // Set dosage from medicine (no auto-calculation)
                const medicineFromProps = props.medicines.find(m => m.id === medicine.id);
                if (medicineFromProps) {
                    existingRow.dosage = medicineFromProps.dosage || '';
                } else {
                    existingRow.dosage = medicine.dosage || '';
                }
            } else {
                // Add new medicine row
                const newId = medicineRowCounter.value++;
                // Set default instruction value - user can modify it
                const defaultInstruction = props.instructions && props.instructions.length > 0 
                    ? props.instructions[0] // Use first instruction from props if available
                    : 'After meals'; // Default fallback
                
                medicineRows.value.push({
                    id: newId,
                    medicine_id: medicine.id,
                    dosage: medicine.dosage || '', // No auto-calculation, just use medicine's dosage
                    instructions: defaultInstruction, // Set default instruction instead of empty string
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
const calculateDosage = (dosagePattern: string | null | undefined, weight: string): string => {
    // Handle null, undefined, or empty dosage
    if (!dosagePattern || typeof dosagePattern !== 'string' || dosagePattern.trim() === '') {
        return '';
    }
    
    const trimmedPattern = dosagePattern.trim();
    
    // Check if dosage pattern is a placeholder/non-calculable value
    const lowerPattern = trimmedPattern.toLowerCase();
    const nonCalculablePatterns = ['as prescribed', 'as per prescription', 'per prescription'];
    
    // For non-calculable patterns, return the original pattern so user can see it
    // but understand it's not auto-calculated - they can edit it if needed
    if (nonCalculablePatterns.includes(lowerPattern)) {
        return trimmedPattern;
    }
    
    // If weight is not provided, return the original pattern to show what's in the database
    // User can still edit it manually
    if (!weight || weight.trim() === '') {
        return trimmedPattern;
    }
    
    const weightNum = parseFloat(weight);
    if (isNaN(weightNum) || weightNum <= 0) {
        return trimmedPattern;
    }

    // Pattern: "1ml/10 Kg", "1ml/10Kg", "1ml/10 Kg body weight", "1ml/10kg", etc. - this is calculable
    // Match: (number)ml / (number)Kg (case-insensitive, allows optional text after Kg)
    // Try multiple pattern variations to handle different formats with flexible spacing
    let match = trimmedPattern.match(/(\d+(?:\.\d+)?)\s*ml\s*\/\s*(\d+(?:\.\d+)?)\s*kg/i);
    
    // If first pattern doesn't match, try without requiring space before ml
    if (!match) {
        match = trimmedPattern.match(/(\d+(?:\.\d+)?)ml\s*\/\s*(\d+(?:\.\d+)?)\s*kg/i);
    }
    
    // If still no match, try with very flexible spacing (allows any whitespace)
    if (!match) {
        match = trimmedPattern.match(/(\d+(?:\.\d+)?)\s*ml\s*\/\s*(\d+(?:\.\d+)?)\s*k\s*g/i);
    }
    
    // If still no match, try without requiring "kg" at all (just match the pattern structure)
    if (!match) {
        match = trimmedPattern.match(/(\d+(?:\.\d+)?)\s*ml\s*\/\s*(\d+(?:\.\d+)?)/i);
    }
    
    if (match && match.length >= 3) {
        const ml = parseFloat(match[1]);
        const perKg = parseFloat(match[2]);
        if (!isNaN(ml) && !isNaN(perKg) && perKg > 0 && ml > 0) {
            const calculated = (weightNum / perKg) * ml;
            // Round to 2 decimal places, but remove trailing zeros for cleaner display
            const rounded = parseFloat(calculated.toFixed(2));
            return `${rounded}ml`;
        }
    }

    // If pattern doesn't match calculation format but has actual content, return it
    // This covers cases like "1 tablet", "2mg", etc. that aren't weight-based calculations
    return trimmedPattern;
};

// Weight changes no longer auto-calculate dosages
// Users can manually edit dosages as needed

// Handle medicine selection
const onMedicineChange = (rowId: number, medicineId: number) => {
    const row = medicineRows.value.find(r => r.id === rowId);
    if (row) {
        row.medicine_id = medicineId;
        // Remove from newly added set once medicine is selected
        newlyAddedRowIds.value.delete(rowId);
        const medicine = props.medicines.find(m => m.id === medicineId);
        if (medicine) {
            // Just use the medicine's dosage without calculation
            row.dosage = medicine.dosage || '';
        } else {
            // If medicine not found, clear dosage
            row.dosage = '';
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

// Switch to different pet
const switchPet = (petId: number) => {
    // Check if this pet already has a prescription
    const pet = props.patients?.find(p => p.id === petId);
    if (pet?.has_prescription) {
        showError('Prescription Already Exists', `A prescription already exists for ${pet.pet_name}. Please select another pet.`);
        return;
    }
    
    // Reload page with selected pet
    router.visit(`/admin/appointments/${props.appointment.id}/prescription/create?patient_id=${petId}`, {
        preserveState: false,
        preserveScroll: false,
    });
};

// Check if fields should be optional based on appointment type
const optionalFieldsTypes = ['Deworming', 'Consultation', 'Vaccination'];
const hasOptionalFields = computed(() => {
    return optionalFieldsTypes.includes(props.appointment.appointment_type);
});

// Submit form
const submit = () => {
    // Client-side validation before submission
    const validationErrors: string[] = [];
    
    // Weight validation - optional for DEWORMING, CONSULTATION, VACCINATION
    if (!hasOptionalFields.value) {
        if (!form.pet_current_weight || parseFloat(form.pet_current_weight) <= 0) {
            validationErrors.push('Pet weight is required and must be greater than 0');
        }
    } else {
        // For optional types, only validate if weight is provided
        if (form.pet_current_weight && parseFloat(form.pet_current_weight) <= 0) {
            validationErrors.push('Pet weight must be greater than 0 if provided');
        }
    }
    
    // Symptoms validation - optional for DEWORMING, CONSULTATION, VACCINATION
    if (!hasOptionalFields.value && selectedSymptoms.value.length === 0) {
        validationErrors.push('Please select at least one symptom');
    }
    
    // Diseases validation - optional for DEWORMING, CONSULTATION, VACCINATION
    if (!hasOptionalFields.value && selectedDiseases.value.length === 0) {
        validationErrors.push('Please select at least one disease/diagnosis');
    }
    
    // Prepare medicines data - filter out empty rows and newly added rows without medicine
    // Check for actual values (not just truthy - empty strings are falsy)
    const validMedicineRows = medicineRows.value.filter(row => {
        const hasMedicine = row.medicine_id !== null;
        const hasDosage = row.dosage && row.dosage.trim() !== '';
        const hasInstructions = row.instructions && row.instructions.trim() !== '';
        const hasQuantity = row.quantity && row.quantity.trim() !== '';
        
        return hasMedicine && hasDosage && hasInstructions && hasQuantity;
    });
    
    if (validMedicineRows.length === 0) {
        // Check if there are medicines but with missing fields
        const medicinesWithoutInstructions = medicineRows.value.filter(row => 
            row.medicine_id !== null && (!row.instructions || row.instructions.trim() === '')
        );
        
        if (medicinesWithoutInstructions.length > 0) {
            validationErrors.push('Please fill in the instructions field for all medicines');
        } else {
            validationErrors.push('Please add at least one medicine with dosage, instructions, and quantity');
        }
    }
    
    // If there are client-side validation errors, show them and don't submit
    if (validationErrors.length > 0) {
        showError('Error creating prescription', validationErrors.join('. ') + '.');
        return;
    }
    
    // Sync disease_ids from selectedDiseases before submission (can be empty for optional types)
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
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4 p-4 bg-muted rounded-lg">
                                <div>
                                    <Label class="text-xs text-muted-foreground">Appointment Date</Label>
                                    <p class="font-medium">{{ appointment.appointment_date }} {{ appointment.appointment_time }}</p>
                                </div>
                                <div>
                                    <Label class="text-xs text-muted-foreground">Appointment Type</Label>
                                    <p class="font-medium">{{ appointment.appointment_type }}</p>
                                </div>
                            </div>
                            
                            <!-- Pet Selection (if multiple pets) -->
                            <div v-if="patients && patients.length > 1" class="space-y-2">
                                <Label>Select Pet for Prescription</Label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div
                                        v-for="pet in patients"
                                        :key="pet.id"
                                        :class="cn(
                                            'p-3 border-2 rounded-lg cursor-pointer transition-all',
                                            patient.id === pet.id
                                                ? 'border-primary bg-primary/5'
                                                : 'border-border hover:border-primary/50',
                                            pet.has_prescription && 'opacity-60'
                                        )"
                                        @click="switchPet(pet.id)"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="font-medium flex items-center gap-2">
                                                    {{ pet.pet_name || 'Unnamed Pet' }}
                                                    <Badge v-if="patient.id === pet.id" variant="default" class="text-xs">
                                                        Selected
                                                    </Badge>
                                                    <Badge v-if="pet.has_prescription" variant="outline" class="text-xs">
                                                        Has Prescription
                                                    </Badge>
                                                </div>
                                                <div class="text-sm text-muted-foreground mt-1">
                                                    {{ pet.pet_type }} - {{ pet.pet_breed }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Current Pet Info -->
                            <div class="grid grid-cols-2 gap-4 p-4 bg-muted rounded-lg">
                                <div>
                                    <Label class="text-xs text-muted-foreground">Pet Name</Label>
                                    <p class="font-medium">{{ patient.pet_name }}</p>
                                </div>
                                <div>
                                    <Label class="text-xs text-muted-foreground">Pet Type & Breed</Label>
                                    <p class="font-medium">{{ patient.pet_type }} - {{ patient.pet_breed }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Pet Weight -->
                        <div class="space-y-2">
                            <Label for="pet_weight">
                                Pet Current Weight (Kg) <span v-if="!hasOptionalFields">*</span>
                            </Label>
                            <Input
                                id="pet_weight"
                                v-model="form.pet_current_weight"
                                type="number"
                                step="0.1"
                                :required="!hasOptionalFields"
                                placeholder="e.g., 10.5"
                            />
                            <InputError :message="form.errors.pet_current_weight" />
                            <p v-if="hasOptionalFields" class="text-xs text-muted-foreground">
                                Weight is optional for this appointment type. If not provided, you can manually enter medicine dosages.
                            </p>
                        </div>

                        <!-- Symptoms Selection -->
                        <div class="space-y-2">
                            <Label>Symptoms <span v-if="!hasOptionalFields">*</span></Label>
                            
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
                            <p v-if="hasOptionalFields" class="text-xs text-muted-foreground">
                                Symptoms are optional for this appointment type. You can proceed without selecting symptoms.
                            </p>

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

                        <!-- Medicine Selection Modal -->
                        <Dialog v-model:open="isMedicineModalOpen">
                            <DialogContent class="w-full md:w-2/3 max-w-none max-h-[600px] flex flex-col">
                                <DialogHeader>
                                    <DialogTitle>Select Medicine</DialogTitle>
                                </DialogHeader>
                                
                                <!-- Search input -->
                                <div class="relative">
                                    <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        v-model="medicineSearchQuery"
                                        type="text"
                                        placeholder="Search medicines by name or dosage..."
                                        class="pl-9"
                                        autofocus
                                    />
                                </div>

                                <!-- Medicines grid -->
                                <div class="flex-1 overflow-y-auto border rounded-md p-4">
                                    <div v-if="filteredMedicines.length === 0" class="text-center py-8 text-muted-foreground">
                                        No medicines found
                                    </div>
                                    <div v-else class="grid grid-cols-1 gap-2">
                                        <button
                                            v-for="medicine in filteredMedicines"
                                            :key="medicine.id"
                                            type="button"
                                            @click="selectMedicineFromModal(medicine)"
                                            :disabled="medicine.stock <= 0"
                                            :class="cn(
                                                'flex items-center p-3 rounded-md border transition-colors text-left w-full',
                                                medicine.stock <= 0
                                                    ? 'border-destructive/50 bg-destructive/5 cursor-not-allowed opacity-60'
                                                    : 'border-border hover:border-primary hover:bg-primary/5 cursor-pointer'
                                            )"
                                        >
                                            <div class="flex-1">
                                                <div class="font-medium">{{ medicine.name }}</div>
                                                <div class="text-sm text-muted-foreground mt-1">
                                                    Dosage: {{ medicine.dosage }}
                                                </div>
                                                <div class="text-xs text-muted-foreground mt-1">
                                                    Stock: {{ medicine.stock }}
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                </div>

                                <!-- Footer with close button -->
                                <div class="flex items-center justify-end pt-4 border-t">
                                    <Button 
                                        type="button" 
                                        variant="outline"
                                        @click="closeMedicineModal"
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </DialogContent>
                        </Dialog>

                        <!-- Instructions Selection Modal -->
                        <Dialog v-model:open="isInstructionsModalOpen">
                            <DialogContent class="w-full md:w-2/3 max-w-none max-h-[600px] flex flex-col">
                                <DialogHeader>
                                    <DialogTitle>Select Instruction</DialogTitle>
                                </DialogHeader>
                                
                                <!-- Search input -->
                                <div class="relative">
                                    <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        v-model="instructionsSearchQuery"
                                        type="text"
                                        placeholder="Search instructions..."
                                        class="pl-9"
                                        autofocus
                                    />
                                </div>

                                <!-- Instructions list -->
                                <div class="flex-1 overflow-y-auto border rounded-md p-4">
                                    <div v-if="filteredInstructions.length === 0" class="text-center py-8 text-muted-foreground">
                                        No instructions found
                                    </div>
                                    <div v-else class="grid grid-cols-1 gap-2">
                                        <button
                                            v-for="(instruction, index) in filteredInstructions"
                                            :key="index"
                                            type="button"
                                            @click="selectInstructionFromModal(instruction)"
                                            :class="cn(
                                                'flex items-center p-3 rounded-md border transition-colors text-left',
                                                'border-border hover:border-primary hover:bg-primary/5 cursor-pointer'
                                            )"
                                        >
                                            <span class="text-sm">{{ instruction }}</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Custom instruction input -->
                                <div class="pt-4 border-t space-y-2">
                                    <Label for="custom_instruction">Or enter custom instruction:</Label>
                                    <Input
                                        id="custom_instruction"
                                        v-model="customInstruction"
                                        type="text"
                                        placeholder="Type custom instruction..."
                                        @keyup.enter="applyCustomInstruction"
                                    />
                                </div>

                                <!-- Footer with close button -->
                                <div class="flex items-center justify-end pt-4 border-t gap-2">
                                    <Button 
                                        type="button" 
                                        variant="outline"
                                        @click="closeInstructionsModal"
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        v-if="customInstruction.trim()"
                                        type="button"
                                        @click="applyCustomInstruction"
                                    >
                                        Use Custom
                                    </Button>
                                </div>
                            </DialogContent>
                        </Dialog>

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

                        <!-- No Diseases Found Message -->
                        <div v-else-if="selectedSymptoms.length > 0 && !isSearchingDiseases && searchedDiseases.length === 0" class="space-y-2">
                            <Label>Predicted Diseases (based on symptoms)</Label>
                            <Card>
                                <CardContent class="p-4">
                                    <div class="text-center py-4">
                                        <p class="text-sm text-muted-foreground mb-2">
                                            No diseases found matching the selected symptoms.
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            Try selecting different symptoms or use the manual search below to add diseases.
                                        </p>
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
                            <Label>Selected Diagnoses <span v-if="!hasOptionalFields">*</span></Label>
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
                        
                        <!-- Show message if diseases are optional but none selected -->
                        <div v-if="hasOptionalFields && selectedDiseases.length === 0" class="space-y-2">
                            <Label>Selected Diagnoses (Optional)</Label>
                            <p class="text-sm text-muted-foreground">
                                No diseases selected. You can proceed without selecting diseases for this appointment type.
                            </p>
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
                                                <div
                                                    @click="openMedicineModal(row.id)"
                                                    :class="cn(
                                                        'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background',
                                                        'cursor-pointer hover:ring-2 hover:ring-ring/50 transition-all',
                                                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                                                        'flex items-center justify-between'
                                                    )"
                                                >
                                                    <span :class="row.medicine_id ? 'text-foreground' : 'text-muted-foreground'">
                                                        {{ row.medicine_id ? getSelectedMedicineName(row.id) : 'Select medicine...' }}
                                                    </span>
                                                    <ChevronDown class="h-4 w-4 text-muted-foreground shrink-0" />
                                                </div>
                                            </td>
                                            <td class="p-2">
                                                <Input
                                                    v-model="row.dosage"
                                                    type="text"
                                                    placeholder="Enter dosage (auto-calculated if pattern matches)"
                                                />
                                            </td>
                                            <td class="p-2">
                                                <div
                                                    @click="openInstructionsModal(row.id)"
                                                    :class="cn(
                                                        'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background',
                                                        'cursor-pointer hover:ring-2 hover:ring-ring/50 transition-all',
                                                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                                                        'flex items-center justify-between'
                                                    )"
                                                >
                                                    <span :class="row.instructions ? 'text-foreground' : 'text-muted-foreground'" class="flex-1 truncate">
                                                        {{ row.instructions || 'Type or select instruction...' }}
                                                    </span>
                                                    <ChevronDown class="h-4 w-4 text-muted-foreground shrink-0 ml-2" />
                                                </div>
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
