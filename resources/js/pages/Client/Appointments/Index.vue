<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { MultiSelect } from '@/components/ui/multi-select';
import { CalendarDatePicker } from '@/components/ui/calendar-date-picker';
import AppointmentCalendar from '@/components/AppointmentCalendar.vue';
import InputError from '@/components/InputError.vue';
// Using native textarea
import { Calendar, Plus, Eye, Search, List, ChevronRight, ChevronLeft, Check, MapPin, AlertCircle, Heart } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

interface Appointment {
    id: number;
    appointment_type: string;
    appointment_date: string | null;
    appointment_time: string | null;
    status: string;
    pet_type: string;
    pet_name: string;
}

interface Pet {
    id: number;
    pet_name: string;
    pet_type: string;
}

interface AppointmentType {
    id: number;
    name: string;
}

interface PetType {
    id: number;
    name: string;
}

interface Props {
    pets: Pet[];
    appointment_types: AppointmentType[];
    pet_types: PetType[];
    pet_breeds: Record<string, string[]>;
    has_location_pin: boolean;
}

const props = defineProps<Props>();
const page = usePage();
const { success: showSuccess } = useToast();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'My Appointments', href: '#' },
];

const appointments = ref<Appointment[]>([]);
const loading = ref(false);
const submitting = ref(false);
const searchQuery = ref('');
const statusFilter = ref('all');
const isModalOpen = ref(false);
const viewMode = ref<'list' | 'calendar'>('list');

// Booking form
const form = ref({
    pet_ids: [] as string[],
    appointment_type_ids: [] as string[],
    appointment_date: '',
    appointment_times: [] as string[],
    symptoms: '',
});

const availableTimes = ref<string[]>([]);
const loadingTimes = ref(false);
const errors = ref<Record<string, string[]>>({});
const currentStep = ref(0);
const showLocationPinDialog = ref(false);

// Pet creation dialog state
const showCreatePetDialog = ref(false);
const creatingPet = ref(false);

// Pet creation form
const petForm = ref({
    pet_type_id: '',
    custom_pet_type_name: '',
    pet_name: '',
    pet_breed: '',
    custom_pet_breed_name: '',
    pet_gender: '',
    pet_birth_date: '',
    pet_allergies: '',
});

const petFormErrors = ref<Record<string, string[]>>({});
const customPetTypeDisplay = ref<string | null>(null);
const customBreedDisplay = ref<string | null>(null);

// Step definitions
const steps = [
    { id: 0, title: 'Pet & Type', description: 'Select pet and appointment type(s)' },
    { id: 1, title: 'Date', description: 'Choose appointment date' },
    { id: 2, title: 'Time', description: 'Select appointment time' },
    { id: 3, title: 'Symptoms', description: 'Add symptoms (optional)' },
];

// Get tomorrow's date as minimum date
const minDate = computed(() => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    return tomorrow.toISOString().split('T')[0];
});

// Computed property for step 0 validation (for better reactivity)
const canProceedStep0 = computed(() => {
    return !!(form.value.pet_ids.length > 0 && form.value.appointment_type_ids.length > 0);
});

// Watch for date changes to fetch available times
watch(() => form.value.appointment_date, (newDate) => {
    if (newDate) {
        fetchAvailableTimes(newDate);
    } else {
        availableTimes.value = [];
        form.value.appointment_times = [];
    }
});

// Watch for pet selection changes - reset time slots if pet count changes
watch(() => form.value.pet_ids, (newPetIds, oldPetIds) => {
    if (newPetIds.length !== oldPetIds.length) {
        form.value.appointment_times = [];
    }
});

const fetchAppointments = async () => {
    loading.value = true;
    try {
        const params: Record<string, any> = {};
        if (searchQuery.value && searchQuery.value.trim()) {
            params['search[value]'] = searchQuery.value.trim();
        }
        if (statusFilter.value && statusFilter.value !== 'all') {
            params['status'] = statusFilter.value;
        }
        
        const response = await axios.get('/appointments', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            params,
        });
        appointments.value = response.data?.data || [];
    } catch (error) {
        console.error('Error fetching appointments:', error);
        appointments.value = [];
    } finally {
        loading.value = false;
    }
};

const handleStatusChange = (status: string) => {
    statusFilter.value = status;
    fetchAppointments();
};

const fetchAvailableTimes = async (date: string) => {
    loadingTimes.value = true;
    form.value.appointment_times = [];
    try {
        const response = await axios.get('/appointments/times/available', {
            params: { selectedDate: date },
        });
        availableTimes.value = response.data.availableTimes || [];
    } catch (error) {
        console.error('Error fetching available times:', error);
        availableTimes.value = [];
    } finally {
        loadingTimes.value = false;
    }
};


const handleBookAppointment = () => {
    // Check if user has location pin set
    if (!props.has_location_pin) {
        showLocationPinDialog.value = true;
        return;
    }

    errors.value = {};
    submitting.value = true;
    router.post(
        '/appointments',
        {
            pet_ids: form.value.pet_ids,
            appointment_type_ids: form.value.appointment_type_ids,
            appointment_date: form.value.appointment_date,
            appointment_times: form.value.appointment_times,
            symptoms: form.value.symptoms,
        },
        {
            onSuccess: () => {
                isModalOpen.value = false;
                resetForm();
                fetchAppointments();
                submitting.value = false;
                showSuccess('Appointment booked successfully!', 'Your appointment has been submitted and is pending approval.');
            },
            onError: (err: any) => {
                errors.value = (err.errors as Record<string, string[]>) || {};
                // Check if error is about location pin
                if (err.errors?.location_pin) {
                    showLocationPinDialog.value = true;
                }
                submitting.value = false;
            },
            onFinish: () => {
                submitting.value = false;
            },
        }
    );
};

const resetForm = () => {
    form.value = {
        pet_ids: [],
        appointment_type_ids: [],
        appointment_date: '',
        appointment_times: [],
        symptoms: '',
    };
    availableTimes.value = [];
    errors.value = {};
    currentStep.value = 0;
};

// Computed property for step 2 validation - time slots must match pet count
const canProceedStep2 = computed(() => {
    const petCount = form.value.pet_ids.length;
    const timeSlotCount = form.value.appointment_times.length;
    return petCount > 0 && timeSlotCount === petCount;
});

// Step validation
const canProceedFromStep = (step: number): boolean => {
    switch (step) {
        case 0:
            return canProceedStep0.value;
        case 1:
            return !!form.value.appointment_date && form.value.appointment_date.trim() !== '';
        case 2:
            return canProceedStep2.value;
        case 3:
            return true; // Symptoms are optional
        default:
            return false;
    }
};

const nextStep = () => {
    if (canProceedFromStep(currentStep.value) && currentStep.value < steps.length - 1) {
        currentStep.value++;
    }
};

const previousStep = () => {
    if (currentStep.value > 0) {
        currentStep.value--;
    }
};

const goToStep = (step: number) => {
    // Only allow going to a step if all previous steps are valid
    let canGo = true;
    for (let i = 0; i < step; i++) {
        if (!canProceedFromStep(i)) {
            canGo = false;
            break;
        }
    }
    if (canGo) {
        currentStep.value = step;
    }
};

const formatDate = (dateString: string | null) => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString();
};

const formatTime = (timeString: string | null) => {
    if (!timeString) return '—';
    // Convert 24-hour format to 12-hour format if needed
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
};

const getStatusBadgeClass = (status: string) => {
    switch (status.toLowerCase()) {
        case 'completed':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'approved':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
    }
};

// Pet type options for SearchableSelect
const petTypeOptions = computed(() => {
    return (props.pet_types || []).map(pt => ({
        value: pt.id.toString(),
        label: pt.name,
    }));
});

// Get the selected pet type name
const selectedPetTypeName = computed(() => {
    if (!petForm.value.pet_type_id) return null;
    const petType = props.pet_types?.find(pt => pt.id.toString() === petForm.value.pet_type_id);
    return petType?.name || null;
});

// Get breed options based on selected pet type
const breedOptions = computed(() => {
    const typeName = selectedPetTypeName.value || customPetTypeDisplay.value;
    if (!typeName || !props.pet_breeds || !props.pet_breeds[typeName]) {
        return [];
    }
    return props.pet_breeds[typeName].map(breed => ({
        value: breed,
        label: breed,
    }));
});

// Handle creating a new pet type
const handleCreatePetType = (name: string) => {
    petForm.value.custom_pet_type_name = name;
    customPetTypeDisplay.value = name;
};

// Handle creating a new breed
const handleCreateBreed = (name: string) => {
    petForm.value.custom_pet_breed_name = name;
    customBreedDisplay.value = name;
};

// Watch for pet type changes to clear breed
watch(() => petForm.value.pet_type_id, (newValue) => {
    petForm.value.pet_breed = '';
    petForm.value.custom_pet_breed_name = '';
    customBreedDisplay.value = null;
    if (newValue !== '__new__') {
        petForm.value.custom_pet_type_name = '';
        customPetTypeDisplay.value = null;
    }
});

// Watch for breed changes
watch(() => petForm.value.pet_breed, (newValue) => {
    if (newValue !== '__new__') {
        petForm.value.custom_pet_breed_name = '';
        customBreedDisplay.value = null;
    }
});

// Handle pet creation
const handleCreatePet = async () => {
    petFormErrors.value = {};
    creatingPet.value = true;
    
    try {
        const response = await axios.post('/pets', petForm.value, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        });
        
        // Reload pets from server to get updated list
        await router.reload({ 
            only: ['pets'],
            preserveScroll: true,
        });
        
        // Get the newly created pet from the response
        if (response.data?.pet) {
            const newPet = response.data.pet;
            // Add the new pet to the selection if not already selected
            if (!form.value.pet_ids.includes(newPet.id.toString())) {
                form.value.pet_ids.push(newPet.id.toString());
            }
        }
        
        // Reset pet form
        petForm.value = {
            pet_type_id: '',
            custom_pet_type_name: '',
            pet_name: '',
            pet_breed: '',
            custom_pet_breed_name: '',
            pet_gender: '',
            pet_birth_date: '',
            pet_allergies: '',
        };
        customPetTypeDisplay.value = null;
        customBreedDisplay.value = null;
        petFormErrors.value = {};
        showCreatePetDialog.value = false;
    } catch (error: any) {
        if (error.response?.data?.errors) {
            petFormErrors.value = error.response.data.errors;
        } else {
            petFormErrors.value = { general: ['Failed to create pet. Please try again.'] };
        }
    } finally {
        creatingPet.value = false;
    }
};

// Fetch appointments on mount
fetchAppointments();
</script>

<template>
    <Head title="My Appointments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Calendar class="h-5 w-5" />
                                My Appointments
                            </CardTitle>
                            <CardDescription>
                                View and manage your pet appointments
                            </CardDescription>
                        </div>
                        <Dialog v-model:open="isModalOpen" @update:open="(open) => { isModalOpen = open; if (!open) resetForm(); }">
                            <DialogTrigger as-child>
                                <Button @click="resetForm()">
                                    <Plus class="h-4 w-4 mr-2" />
                                    Book Appointment
                                </Button>
                            </DialogTrigger>
                            <DialogContent class="sm:max-w-[700px]">
                                <DialogHeader class="pb-4">
                                    <DialogTitle>Book an Appointment</DialogTitle>
                                    <DialogDescription>
                                        {{ steps[currentStep].description }}
                                    </DialogDescription>
                                </DialogHeader>
                                
                                <!-- Stepper Indicator -->
                                <div class="py-6 px-4 border-b">
                                    <div class="flex items-start">
                                        <div
                                            v-for="(step, index) in steps"
                                            :key="step.id"
                                            class="flex items-center flex-1 last:flex-none"
                                        >
                                            <!-- Step Circle and Label Container -->
                                            <div class="flex flex-col items-center relative z-10">
                                                <!-- Step Circle -->
                                                <button
                                                    type="button"
                                                    @click="goToStep(step.id)"
                                                    :disabled="index > 0 && !canProceedFromStep(index - 1)"
                                                    :class="[
                                                        'flex items-center justify-center w-10 h-10 rounded-full border-2 transition-all duration-200',
                                                        'disabled:pointer-events-none disabled:opacity-50',
                                                        currentStep === index
                                                            ? 'bg-primary border-primary text-primary-foreground shadow-lg scale-110'
                                                            : index < currentStep
                                                            ? 'bg-primary border-primary text-primary-foreground cursor-pointer hover:bg-primary/90 hover:scale-105'
                                                            : canProceedFromStep(index - 1) || index === 0
                                                            ? 'border-muted-foreground/50 bg-background text-muted-foreground cursor-pointer hover:border-primary hover:bg-muted/50'
                                                            : 'border-muted-foreground/30 bg-background text-muted-foreground/50 cursor-not-allowed'
                                                    ]"
                                                >
                                                    <Check
                                                        v-if="index < currentStep"
                                                        class="w-5 h-5"
                                                    />
                                                    <span v-else class="text-sm font-semibold">{{ index + 1 }}</span>
                                                </button>
                                                <!-- Step Label -->
                                                <div class="mt-2 text-center max-w-[80px]">
                                                    <p
                                                        :class="[
                                                            'text-xs font-medium whitespace-nowrap',
                                                            currentStep === index
                                                                ? 'text-foreground font-semibold'
                                                                : index < currentStep
                                                                ? 'text-foreground'
                                                                : 'text-muted-foreground'
                                                        ]"
                                                    >
                                                        {{ step.title }}
                                                    </p>
                                                </div>
                                            </div>
                                            <!-- Connector Line -->
                                            <div
                                                v-if="index < steps.length - 1"
                                                class="flex-1 relative -mt-[20px] mx-2"
                                            >
                                                <div
                                                    :class="[
                                                        'h-0.5 w-full transition-colors duration-200',
                                                        index < currentStep ? 'bg-primary' : 'bg-muted'
                                                    ]"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step Content -->
                                <div class="grid gap-4 py-6 min-h-[300px]">
                                    <!-- Step 0: Pet & Appointment Type -->
                                    <div v-if="currentStep === 0" class="space-y-4">
                                        <div class="grid gap-2">
                                            <Label for="pet_ids">Pet(s)</Label>
                                            <div class="flex gap-2">
                                                <MultiSelect
                                                    v-model="form.pet_ids"
                                                    :options="(props.pets || []).map(pet => ({ value: pet.id.toString(), label: `${pet.pet_name} (${pet.pet_type})` }))"
                                                    placeholder="Select one or more pets"
                                                    search-placeholder="Search pets..."
                                                    :disabled="!props.pets || props.pets.length === 0"
                                                    class="flex-1"
                                                />
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    @click="showCreatePetDialog = true"
                                                    class="shrink-0"
                                                >
                                                    <Plus class="h-4 w-4 mr-2" />
                                                    Add Pet
                                                </Button>
                                            </div>
                                            <p
                                                v-if="errors.pet_ids"
                                                class="text-sm text-destructive"
                                            >
                                                {{ errors.pet_ids[0] }}
                                            </p>
                                            <p
                                                v-if="!props.pets || props.pets.length === 0"
                                                class="text-sm text-muted-foreground"
                                            >
                                                No pets registered. Click "Add Pet" to create one.
                                            </p>
                                            <p class="text-sm text-muted-foreground">
                                                You can select multiple pets for this appointment.
                                            </p>
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="appointment_type_ids">Appointment Type</Label>
                                            <MultiSelect
                                                v-model="form.appointment_type_ids"
                                                :options="(props.appointment_types || []).map(type => ({ value: type.id.toString(), label: type.name }))"
                                                placeholder="Select appointment type(s)"
                                                search-placeholder="Search appointment types..."
                                                :disabled="!props.appointment_types || props.appointment_types.length === 0"
                                            />
                                            <p
                                                v-if="errors.appointment_type_ids"
                                                class="text-sm text-destructive"
                                            >
                                                {{ errors.appointment_type_ids[0] }}
                                            </p>
                                            <p
                                                v-if="!props.appointment_types || props.appointment_types.length === 0"
                                                class="text-sm text-muted-foreground"
                                            >
                                                No appointment types available.
                                            </p>
                                            <p class="text-sm text-muted-foreground">
                                                Select one or more appointment types for your visit.
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Step 1: Appointment Date -->
                                    <div v-if="currentStep === 1" class="space-y-4">
                                        <div class="grid gap-2">
                                            <Label for="appointment_date">Appointment Date</Label>
                                            <CalendarDatePicker
                                                id="appointment_date"
                                                v-model="form.appointment_date"
                                                :min-date="minDate"
                                                required
                                            />
                                            <p
                                                v-if="errors.appointment_date"
                                                class="text-sm text-destructive"
                                            >
                                                {{ errors.appointment_date[0] }}
                                            </p>
                                            <p class="text-sm text-muted-foreground">
                                                Please select a date for your appointment. Appointments must be booked at least one day in advance.
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Step 2: Appointment Time -->
                                    <div v-if="currentStep === 2" class="space-y-4">
                                        <div class="grid gap-2">
                                            <Label for="appointment_times">
                                                Appointment Time(s)
                                                <span class="text-muted-foreground font-normal">
                                                    (Select {{ form.pet_ids.length }} time slot{{ form.pet_ids.length !== 1 ? 's' : '' }} - one for each pet)
                                                </span>
                                            </Label>
                                            <MultiSelect
                                                v-model="form.appointment_times"
                                                :options="availableTimes.map(time => ({ value: time, label: time }))"
                                                :placeholder="
                                                    loadingTimes
                                                        ? 'Loading available times...'
                                                        : !form.appointment_date
                                                          ? 'Please select a date first'
                                                          : !availableTimes || availableTimes.length === 0
                                                            ? 'No available times for this date'
                                                            : `Select ${form.pet_ids.length} time slot${form.pet_ids.length !== 1 ? 's' : ''}`
                                                "
                                                :disabled="!form.appointment_date || loadingTimes || !availableTimes || availableTimes.length === 0"
                                                :max-selected="form.pet_ids.length"
                                            />
                                            <p
                                                v-if="errors.appointment_times"
                                                class="text-sm text-destructive"
                                            >
                                                {{ errors.appointment_times[0] }}
                                            </p>
                                            <p
                                                v-if="loadingTimes"
                                                class="text-sm text-muted-foreground"
                                            >
                                                Loading available times...
                                            </p>
                                            <p
                                                v-else-if="form.appointment_date && !loadingTimes && availableTimes.length === 0"
                                                class="text-sm text-muted-foreground"
                                            >
                                                No available times for this date. Please select a different date.
                                            </p>
                                            <p
                                                v-else-if="form.pet_ids.length > 0"
                                                class="text-sm text-muted-foreground"
                                            >
                                                You have selected {{ form.pet_ids.length }} pet{{ form.pet_ids.length !== 1 ? 's' : '' }}. 
                                                Please select {{ form.pet_ids.length }} time slot{{ form.pet_ids.length !== 1 ? 's' : '' }} 
                                                ({{ form.appointment_times.length }} of {{ form.pet_ids.length }} selected).
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Step 3: Symptoms -->
                                    <div v-if="currentStep === 3" class="space-y-4">
                                        <div class="grid gap-2">
                                            <Label for="symptoms">Symptoms (Optional)</Label>
                                            <textarea
                                                id="symptoms"
                                                v-model="form.symptoms"
                                                placeholder="Describe any symptoms your pet is experiencing..."
                                                rows="6"
                                                class="flex min-h-[150px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                            />
                                            <p
                                                v-if="errors.symptoms"
                                                class="text-sm text-destructive"
                                            >
                                                {{ errors.symptoms[0] }}
                                            </p>
                                            <p class="text-sm text-muted-foreground">
                                                Providing symptoms helps our veterinarians prepare for your appointment. This field is optional.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Navigation Footer -->
                                <DialogFooter class="border-t pt-4 mt-4">
                                    <div class="flex justify-between w-full">
                                        <Button
                                            variant="outline"
                                            @click="isModalOpen = false"
                                        >
                                            Cancel
                                        </Button>
                                        <div class="flex gap-2">
                                            <Button
                                                v-if="currentStep > 0"
                                                variant="outline"
                                                @click="previousStep"
                                            >
                                                <ChevronLeft class="h-4 w-4 mr-2" />
                                                Previous
                                            </Button>
                                            <Button
                                                v-if="currentStep < steps.length - 1"
                                                @click="nextStep"
                                                :disabled="!canProceedFromStep(currentStep)"
                                            >
                                                Next
                                                <ChevronRight class="h-4 w-4 ml-2" />
                                            </Button>
                                            <Button
                                                v-if="currentStep === steps.length - 1"
                                                @click="handleBookAppointment"
                                                :disabled="form.pet_ids.length === 0 || form.appointment_type_ids.length === 0 || !form.appointment_date || form.appointment_times.length !== form.pet_ids.length || submitting"
                                            >
                                                <span v-if="submitting">Booking...</span>
                                                <span v-else>Book Appointment</span>
                                            </Button>
                                        </div>
                                    </div>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Status Tabs -->
                    <div class="mb-4">
                        <div class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800">
                            <button
                                v-for="status in [
                                    { value: 'all', label: 'All' },
                                    { value: 'pending', label: 'Pending' },
                                    { value: 'approved', label: 'Approved' },
                                    { value: 'completed', label: 'Completed' },
                                    { value: 'canceled', label: 'Canceled' }
                                ]"
                                :key="status.value"
                                @click="handleStatusChange(status.value)"
                                :class="[
                                    'flex items-center rounded-md px-3.5 py-1.5 transition-colors text-sm font-medium',
                                    statusFilter === status.value
                                        ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                                        : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60'
                                ]"
                            >
                                {{ status.label }}
                            </button>
                        </div>
                    </div>

                    <!-- View Toggle and Search -->
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <div class="flex gap-2 flex-1 max-w-sm">
                            <div class="relative flex-1">
                                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search appointments..."
                                    class="pl-10"
                                    @keyup.enter="fetchAppointments"
                                />
                            </div>
                            <Button
                                variant="outline"
                                @click="fetchAppointments"
                            >
                                Search
                            </Button>
                            <Button
                                v-if="searchQuery"
                                variant="ghost"
                                @click="searchQuery = ''; fetchAppointments()"
                            >
                                Clear
                            </Button>
                            <Button
                                v-if="statusFilter !== 'all'"
                                variant="ghost"
                                @click="statusFilter = 'all'; fetchAppointments()"
                            >
                                Clear Filter
                            </Button>
                        </div>
                        
                        <!-- View Toggle -->
                        <div class="inline-flex gap-1 rounded-lg bg-muted p-1">
                            <Button
                                :variant="viewMode === 'list' ? 'default' : 'ghost'"
                                size="sm"
                                @click="viewMode = 'list'"
                                class="gap-2"
                            >
                                <List class="h-4 w-4" />
                                List
                            </Button>
                            <Button
                                :variant="viewMode === 'calendar' ? 'default' : 'ghost'"
                                size="sm"
                                @click="viewMode = 'calendar'"
                                class="gap-2"
                            >
                                <Calendar class="h-4 w-4" />
                                Calendar
                            </Button>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div
                        v-if="loading"
                        class="flex items-center justify-center p-8"
                    >
                        <div class="text-muted-foreground">Loading appointments...</div>
                    </div>

                    <!-- Calendar View -->
                    <div
                        v-else-if="viewMode === 'calendar'"
                    >
                        <AppointmentCalendar :appointments="appointments || []" />
                    </div>

                    <!-- List View -->
                    <div
                        v-else
                        class="overflow-x-auto"
                    >
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left p-3 font-semibold">Appointment Type</th>
                                    <th class="text-left p-3 font-semibold">Pet Type</th>
                                    <th class="text-left p-3 font-semibold">Pet Name</th>
                                    <th class="text-left p-3 font-semibold">Date</th>
                                    <th class="text-left p-3 font-semibold">Time</th>
                                    <th class="text-left p-3 font-semibold">Status</th>
                                    <th class="text-right p-3 font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="appointment in (appointments || [])"
                                    :key="appointment.id"
                                    class="border-b hover:bg-muted/50"
                                >
                                    <td class="p-3 text-sm font-medium">
                                        {{ appointment.appointment_type }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ appointment.pet_type }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ appointment.pet_name }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ formatDate(appointment.appointment_date) }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ formatTime(appointment.appointment_time) }}
                                    </td>
                                    <td class="p-3">
                                        <span
                                            :class="['px-2 py-1 rounded-full text-xs font-medium', getStatusBadgeClass(appointment.status)]"
                                        >
                                            {{ appointment.status }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex justify-end gap-2">
                                            <Link :href="`/appointments/${appointment.id}`">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Eye class="h-4 w-4" />
                                                </Button>
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!appointments || appointments.length === 0">
                                    <td
                                        colspan="7"
                                        class="p-8 text-center text-muted-foreground"
                                    >
                                        No appointments found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
        
        <!-- Create New Pet Dialog -->
        <Dialog v-model:open="showCreatePetDialog">
            <DialogContent class="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <div class="flex items-center gap-2">
                        <Heart class="h-5 w-5" />
                        <DialogTitle>Create New Pet</DialogTitle>
                    </div>
                    <DialogDescription>
                        Add a new pet to your account and continue booking your appointment
                    </DialogDescription>
                </DialogHeader>
                
                <div class="grid gap-4 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="create_pet_type_id">Pet Type <span class="text-destructive">*</span></Label>
                            <SearchableSelect
                                id="create_pet_type_id"
                                v-model="petForm.pet_type_id"
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
                                New pet type "{{ customPetTypeDisplay }}" will be created.
                            </p>
                            <InputError :message="petFormErrors.pet_type_id?.[0]" />
                            <InputError :message="petFormErrors.custom_pet_type_name?.[0]" />
                        </div>

                        <div class="space-y-2">
                            <Label for="create_pet_name">Pet Name</Label>
                            <Input
                                id="create_pet_name"
                                v-model="petForm.pet_name"
                                type="text"
                                placeholder="e.g., Max, Bella"
                                autocomplete="off"
                            />
                            <InputError :message="petFormErrors.pet_name?.[0]" />
                        </div>

                        <div class="space-y-2">
                            <Label for="create_pet_breed">Pet Breed <span class="text-destructive">*</span></Label>
                            <SearchableSelect
                                id="create_pet_breed"
                                v-model="petForm.pet_breed"
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
                                New breed "{{ customBreedDisplay }}" will be created.
                            </p>
                            <InputError :message="petFormErrors.pet_breed?.[0]" />
                            <InputError :message="petFormErrors.custom_pet_breed_name?.[0]" />
                        </div>

                        <div class="space-y-2">
                            <Label for="create_pet_gender">Pet Gender</Label>
                            <select
                                id="create_pet_gender"
                                v-model="petForm.pet_gender"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                            <InputError :message="petFormErrors.pet_gender?.[0]" />
                        </div>

                        <div class="space-y-2">
                            <Label for="create_pet_birth_date">Pet Birth Date</Label>
                            <Input
                                id="create_pet_birth_date"
                                v-model="petForm.pet_birth_date"
                                type="date"
                                autocomplete="off"
                            />
                            <InputError :message="petFormErrors.pet_birth_date?.[0]" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="create_pet_allergies">Pet Allergies</Label>
                            <Input
                                id="create_pet_allergies"
                                v-model="petForm.pet_allergies"
                                type="text"
                                placeholder="e.g., Peanuts, Pollen"
                                autocomplete="off"
                            />
                            <InputError :message="petFormErrors.pet_allergies?.[0]" />
                        </div>
                    </div>
                    
                    <p v-if="petFormErrors.general" class="text-sm text-destructive">
                        {{ petFormErrors.general[0] }}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showCreatePetDialog = false"
                        :disabled="creatingPet"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="handleCreatePet"
                        :disabled="creatingPet || !petForm.pet_type_id || !petForm.pet_breed"
                    >
                        <span v-if="creatingPet">Creating...</span>
                        <span v-else>Create Pet</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Location Pin Warning Dialog -->
        <Dialog v-model:open="showLocationPinDialog">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-yellow-100 dark:bg-yellow-900/20">
                            <AlertCircle class="h-6 w-6 text-yellow-600 dark:text-yellow-400" />
                        </div>
                        <div>
                            <DialogTitle>Location Pin Required</DialogTitle>
                            <DialogDescription class="mt-1">
                                You need to set your home address location pin before booking an appointment.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <div class="py-4">
                    <p class="text-sm text-muted-foreground">
                        To book an appointment, please set the location pin of your home address in your profile settings. 
                        This helps us provide better service and accurate location information.
                    </p>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showLocationPinDialog = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        as-child
                    >
                        <Link :href="editProfile().url">
                            Go to Settings
                        </Link>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
