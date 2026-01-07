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
import { Calendar, Plus, Eye, Search, List, ChevronRight, ChevronLeft, Check, Heart } from 'lucide-vue-next';
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import { dashboard } from '@/routes';
import axios from 'axios';
import { useToast } from '@/composables/useToast';
import { useAbly } from '@/composables/useAbly';

interface AppointmentItem {
    id: number;
    appointment_type: string;
    pet_type: string;
    pet_name: string;
}

interface Appointment {
    id: number | string;
    appointment_type: string;
    appointment_date: string | null;
    appointment_time: string | null;
    status: string;
    pet_type: string;
    pet_name: string;
    pet_count?: number;
    appointments?: AppointmentItem[]; // Pets in this appointment
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
    philippine_holidays?: string[];
}

const props = defineProps<Props>();
const page = usePage();
const { success: showSuccess } = useToast();
const { notifications, connect, disconnect } = useAbly();

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
const expandedGroups = ref<Set<string | number>>(new Set());

// Pet-appointment type pair interface
interface PetAppointmentPair {
    pet_id: string;
    appointment_type_id: string;
}

// Checkbox selection state: pet_id -> appointment_type_ids[]
interface PetAppointmentSelections {
    [petId: string]: string[];
}

// Booking form
const form = ref({
    pet_appointments: [] as PetAppointmentPair[],
    appointment_date: '',
    appointment_times: [] as string[],
    symptoms: '',
});

// Checkbox selections for the table
const checkboxSelections = ref<PetAppointmentSelections>({});

const availableTimes = ref<string[]>([]);
const loadingTimes = ref(false);
const errors = ref<Record<string, string[]>>({});
const currentStep = ref(0);

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

// Pet sorting
const petSortOrder = ref<'az' | 'za'>('az');

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
    // Check if at least one checkbox is selected
    const hasSelection = Object.values(checkboxSelections.value).some(
        appointmentTypeIds => appointmentTypeIds.length > 0
    );
    return hasSelection;
});

// Convert checkbox selections to pet_appointments format for submission
const getPetAppointmentsFromCheckboxes = (): PetAppointmentPair[] => {
    const pairs: PetAppointmentPair[] = [];
    Object.entries(checkboxSelections.value).forEach(([petId, appointmentTypeIds]) => {
        appointmentTypeIds.forEach(appointmentTypeId => {
            pairs.push({
                pet_id: petId,
                appointment_type_id: appointmentTypeId,
            });
        });
    });
    return pairs;
};

// Handle checkbox toggle
const toggleCheckbox = (petId: string, appointmentTypeId: string) => {
    if (!checkboxSelections.value[petId]) {
        checkboxSelections.value[petId] = [];
    }
    const index = checkboxSelections.value[petId].indexOf(appointmentTypeId);
    if (index > -1) {
        checkboxSelections.value[petId].splice(index, 1);
    } else {
        checkboxSelections.value[petId].push(appointmentTypeId);
    }
};

// Check if checkbox is selected
const isCheckboxSelected = (petId: string, appointmentTypeId: string): boolean => {
    return checkboxSelections.value[petId]?.includes(appointmentTypeId) || false;
};

// Watch for date changes to fetch available times
watch(() => form.value.appointment_date, (newDate) => {
    if (newDate) {
        fetchAvailableTimes(newDate);
    } else {
        availableTimes.value = [];
        form.value.appointment_times = [];
    }
});

// Watch for checkbox selections changes - reset time slots if selections change
watch(() => checkboxSelections.value, () => {
    form.value.appointment_times = [];
}, { deep: true });

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
    errors.value = {};
    submitting.value = true;
    
    // Convert checkbox selections to pet_appointments format
    const petAppointments = getPetAppointmentsFromCheckboxes();
    
    router.post(
        '/appointments',
        {
            pet_appointments: petAppointments,
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
        pet_appointments: [],
        appointment_date: '',
        appointment_times: [],
        symptoms: '',
    };
    checkboxSelections.value = {};
    availableTimes.value = [];
    errors.value = {};
    currentStep.value = 0;
};

// Computed property for step 2 validation - only one time slot required (multiple pets can share)
const canProceedStep2 = computed(() => {
    return form.value.appointment_times.length > 0;
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
    // Parse date string (YYYY-MM-DD) as local date to avoid timezone issues
    const [year, month, day] = dateString.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    return date.toLocaleDateString();
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

const toggleGroupExpansion = (groupId: string | number) => {
    if (expandedGroups.value.has(groupId)) {
        expandedGroups.value.delete(groupId);
    } else {
        expandedGroups.value.add(groupId);
    }
};

const isGroupExpanded = (groupId: string | number): boolean => {
    return expandedGroups.value.has(groupId);
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

// Sorted pets based on sort order
const sortedPets = computed(() => {
    if (!props.pets || props.pets.length === 0) {
        return [];
    }
    const pets = [...props.pets];
    if (petSortOrder.value === 'az') {
        return pets.sort((a, b) => a.pet_name.localeCompare(b.pet_name));
    } else {
        return pets.sort((a, b) => b.pet_name.localeCompare(a.pet_name));
    }
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
            // Initialize checkbox selections for the new pet (empty array)
            checkboxSelections.value[newPet.id.toString()] = [];
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

// Watch for reschedule notifications and refresh appointments list
watch(notifications, (newNotifications) => {
    // Check if there's a new reschedule notification
    const rescheduleNotification = newNotifications.find(
        (n) => n.subject?.toLowerCase().includes('rescheduled') || n.message?.toLowerCase().includes('rescheduled')
    );
    
    if (rescheduleNotification) {
        // Refresh appointments list to show updated schedule
        fetchAppointments();
    }
}, { deep: true });

// Connect to Ably on mount and disconnect on unmount
onMounted(() => {
    connect();
    fetchAppointments();
});

onUnmounted(() => {
    disconnect();
});
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
                            <DialogContent class="sm:max-w-[700px] max-h-[95vh] sm:max-h-[90vh] !flex flex-col m-4 sm:m-0 !top-4 sm:!top-[50%] !translate-y-0 sm:!translate-y-[-50%] overflow-hidden">
                                <div class="flex flex-col flex-1 min-h-0 overflow-y-auto sm:overflow-visible">
                                    <DialogHeader class="pb-2 sm:pb-4 flex-shrink-0">
                                        <DialogTitle class="text-lg sm:text-xl">Book an Appointment</DialogTitle>
                                        <DialogDescription class="text-xs sm:text-sm">
                                            {{ steps[currentStep].description }}
                                        </DialogDescription>
                                    </DialogHeader>
                                    
                                    <!-- Stepper Indicator -->
                                    <div class="py-3 sm:py-6 px-2 sm:px-4 border-b flex-shrink-0">
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
                                                        'flex items-center justify-center w-8 h-8 sm:w-10 sm:h-10 rounded-full border-2 transition-all duration-200',
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
                                                        class="w-4 h-4 sm:w-5 sm:h-5"
                                                    />
                                                    <span v-else class="text-xs sm:text-sm font-semibold">{{ index + 1 }}</span>
                                                </button>
                                                <!-- Step Label -->
                                                <div class="mt-1 sm:mt-2 text-center max-w-[60px] sm:max-w-[80px]">
                                                    <p
                                                        :class="[
                                                            'text-[10px] sm:text-xs font-medium whitespace-nowrap',
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
                                                class="flex-1 relative -mt-[16px] sm:-mt-[20px] mx-1 sm:mx-2"
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
                                <div class="flex flex-col gap-3 sm:gap-4 py-3 sm:py-6 min-h-[200px] sm:min-h-[300px] flex-1">
                                    <!-- Step 0: Pet & Appointment Type -->
                                    <div v-if="currentStep === 0" class="flex flex-col gap-3 sm:gap-4 sm:flex-1 sm:min-h-0">
                                        <div class="flex items-center justify-between gap-2 flex-shrink-0">
                                            <Label class="text-sm sm:text-base">Select Pet(s) and Appointment Type(s)</Label>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="text-xs sm:text-sm whitespace-nowrap"
                                                @click="showCreatePetDialog = true"
                                            >
                                                <Plus class="h-3 w-3 sm:h-4 sm:w-4 mr-1 sm:mr-2" />
                                                <span class="hidden sm:inline">Add Pet</span>
                                                <span class="sm:hidden">Add</span>
                                            </Button>
                                        </div>
                                        
                                        <div
                                            v-if="!props.pets || props.pets.length === 0"
                                            class="text-center py-8 border-2 border-dashed rounded-lg"
                                        >
                                            <p class="text-sm text-muted-foreground mb-4">
                                                No pets registered. Click "Add Pet" to create one.
                                            </p>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                @click="showCreatePetDialog = true"
                                            >
                                                <Plus class="h-4 w-4 mr-2" />
                                                Add Your First Pet
                                            </Button>
                                        </div>

                                        <div
                                            v-else
                                            class="flex flex-col gap-3 sm:flex-1 sm:min-h-0"
                                        >
                                            <!-- Sort Options -->
                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                <Label class="text-xs sm:text-sm text-muted-foreground">Sort by name:</Label>
                                                <div class="inline-flex gap-1 rounded-lg bg-muted p-1">
                                                    <Button
                                                        type="button"
                                                        :variant="petSortOrder === 'az' ? 'default' : 'ghost'"
                                                        size="sm"
                                                        @click="petSortOrder = 'az'"
                                                        class="h-6 sm:h-7 px-2 sm:px-3 text-xs"
                                                    >
                                                        A-Z
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        :variant="petSortOrder === 'za' ? 'default' : 'ghost'"
                                                        size="sm"
                                                        @click="petSortOrder = 'za'"
                                                        class="h-6 sm:h-7 px-2 sm:px-3 text-xs"
                                                    >
                                                        Z-A
                                                    </Button>
                                                </div>
                                            </div>

                                            <!-- Scrollable Table Container -->
                                            <div class="border rounded-lg overflow-hidden sm:flex-1 sm:min-h-0 flex flex-col">
                                                <div class="overflow-x-auto sm:overflow-y-auto sm:max-h-[350px] md:max-h-[400px]">
                                                    <table class="w-full border-collapse">
                                                        <thead class="sticky top-0 bg-muted/50 z-10 backdrop-blur-sm">
                                                            <tr class="border-b">
                                                                <th class="text-left p-2 sm:p-3 font-semibold text-xs sm:text-sm">Pet</th>
                                                                <th
                                                                    v-for="appointmentType in props.appointment_types"
                                                                    :key="appointmentType.id"
                                                                    class="text-center p-2 sm:p-3 font-semibold text-xs sm:text-sm"
                                                                >
                                                                    {{ appointmentType.name }}
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr
                                                                v-for="pet in sortedPets"
                                                                :key="pet.id"
                                                                class="border-b hover:bg-muted/30"
                                                            >
                                                                <td class="p-2 sm:p-3 font-medium text-xs sm:text-sm">
                                                                    {{ pet.pet_name }} ({{ pet.pet_type }})
                                                                </td>
                                                                <td
                                                                    v-for="appointmentType in props.appointment_types"
                                                                    :key="appointmentType.id"
                                                                    class="p-2 sm:p-3 text-center"
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        :id="`checkbox_${pet.id}_${appointmentType.id}`"
                                                                        :checked="isCheckboxSelected(pet.id.toString(), appointmentType.id.toString())"
                                                                        @change="toggleCheckbox(pet.id.toString(), appointmentType.id.toString())"
                                                                        class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary cursor-pointer"
                                                                    />
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex-shrink-0 space-y-1 sm:space-y-2">
                                            <p
                                                v-if="errors.pet_appointments"
                                                class="text-xs sm:text-sm text-destructive"
                                            >
                                                {{ errors.pet_appointments[0] }}
                                            </p>
                                            <p class="text-xs sm:text-sm text-muted-foreground">
                                                Select one or more appointment types for each pet by checking the boxes. You can select multiple pets and multiple appointment types.
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
                                                :disabled-dates="props.philippine_holidays || []"
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
                                                Appointment Time
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
                                                            : 'Select a time slot'
                                                "
                                                :disabled="!form.appointment_date || loadingTimes || !availableTimes || availableTimes.length === 0"
                                                :max-selected="1"
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
                                                v-else-if="canProceedStep0"
                                                class="text-sm text-muted-foreground"
                                            >
                                                <span v-if="form.appointment_times.length > 0">
                                                    Selected: {{ form.appointment_times[0] }}
                                                </span>
                                                <span v-else>
                                                    All selected pets will share the same time slot.
                                                </span>
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
                                </div>

                                <!-- Navigation Footer -->
                                <DialogFooter class="border-t pt-3 sm:pt-4 mt-3 sm:mt-4 flex-shrink-0 gap-2 sm:gap-0">
                                    <div class="flex justify-between w-full gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="text-xs sm:text-sm"
                                            @click="isModalOpen = false"
                                        >
                                            Cancel
                                        </Button>
                                        <div class="flex gap-2">
                                            <Button
                                                v-if="currentStep > 0"
                                                variant="outline"
                                                size="sm"
                                                class="text-xs sm:text-sm"
                                                @click="previousStep"
                                            >
                                                <ChevronLeft class="h-3 w-3 sm:h-4 sm:w-4 mr-1 sm:mr-2" />
                                                <span class="hidden sm:inline">Previous</span>
                                                <span class="sm:hidden">Prev</span>
                                            </Button>
                                            <Button
                                                v-if="currentStep < steps.length - 1"
                                                size="sm"
                                                class="text-xs sm:text-sm"
                                                @click="nextStep"
                                                :disabled="!canProceedFromStep(currentStep)"
                                            >
                                                Next
                                                <ChevronRight class="h-3 w-3 sm:h-4 sm:w-4 ml-1 sm:ml-2" />
                                            </Button>
                                            <Button
                                                v-if="currentStep === steps.length - 1"
                                                size="sm"
                                                class="text-xs sm:text-sm"
                                                @click="handleBookAppointment"
                                                :disabled="!canProceedStep0 || !form.appointment_date || form.appointment_times.length === 0 || submitting"
                                            >
                                                <span v-if="submitting">Booking...</span>
                                                <template v-else>
                                                    <span class="hidden sm:inline">Book Appointment</span>
                                                    <span class="sm:hidden">Book</span>
                                                </template>
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
                        class="space-y-3"
                    >
                        <div
                            v-for="appointment in (appointments || [])"
                            :key="appointment.id"
                            class="border rounded-lg overflow-hidden"
                        >
                            <!-- Appointment Row - ONE appointment with potentially multiple pets -->
                            <div
                                class="grid grid-cols-7 gap-4 p-4 items-center hover:bg-muted/50 transition-colors"
                                :class="appointment.pet_count > 1 ? 'cursor-pointer bg-blue-50/30 dark:bg-blue-950/20' : ''"
                                @click="appointment.pet_count > 1 ? toggleGroupExpansion(appointment.id) : null"
                            >
                                <div class="text-sm font-medium">
                                    <template v-if="appointment.pet_count > 1">
                                        <span class="inline-flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 whitespace-nowrap">
                                                {{ appointment.pet_count }} Pet{{ appointment.pet_count !== 1 ? 's' : '' }}
                                            </span>
                                            <span>{{ appointment.appointment_type }}</span>
                                        </span>
                                    </template>
                                    <span v-else>{{ appointment.appointment_type }}</span>
                                </div>
                                <div class="text-sm">
                                    <template v-if="appointment.pet_count > 1">
                                        <span class="text-muted-foreground">Multiple</span>
                                    </template>
                                    <span v-else>{{ appointment.pet_type }}</span>
                                </div>
                                <div class="text-sm">
                                    <template v-if="appointment.pet_count > 1">
                                        <span class="text-muted-foreground">Click to view</span>
                                    </template>
                                    <span v-else>{{ appointment.pet_name }}</span>
                                </div>
                                <div class="text-sm">{{ formatDate(appointment.appointment_date) }}</div>
                                <div class="text-sm">{{ formatTime(appointment.appointment_time) }}</div>
                                <div>
                                    <span
                                        :class="['px-2 py-1 rounded-full text-xs font-medium', getStatusBadgeClass(appointment.status)]"
                                    >
                                        {{ appointment.status }}
                                    </span>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <Link
                                        v-if="appointment.pet_count === 1"
                                        :href="`/appointments/${appointment.id}`"
                                        @click.stop
                                    >
                                        <Button
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                    </Link>
                                    <Button
                                        v-else
                                        variant="outline"
                                        size="sm"
                                        @click.stop="toggleGroupExpansion(appointment.id)"
                                        class="p-0 h-8 w-8"
                                    >
                                        <ChevronRight
                                            :class="['h-4 w-4 transition-transform', isGroupExpanded(appointment.id) ? 'rotate-90' : '']"
                                        />
                                    </Button>
                                </div>
                            </div>

                            <!-- Expanded Pets List - Show all pets in this ONE appointment -->
                            <div
                                v-if="appointment.pet_count > 1 && isGroupExpanded(appointment.id)"
                                class="border-t bg-background"
                            >
                                <div class="p-4 space-y-3">
                                    <div class="text-sm font-semibold mb-2">Pets in this appointment:</div>
                                    <div
                                        v-for="(pet, index) in appointment.appointments"
                                        :key="pet.id"
                                        class="flex items-center justify-between p-3 bg-muted/30 rounded-lg"
                                    >
                                        <div class="flex-1 grid grid-cols-3 gap-4">
                                            <div>
                                                <span class="text-xs text-muted-foreground">Pet Name</span>
                                                <p class="text-sm font-medium">{{ pet.pet_name }}</p>
                                            </div>
                                            <div>
                                                <span class="text-xs text-muted-foreground">Pet Type</span>
                                                <p class="text-sm">{{ pet.pet_type }}</p>
                                            </div>
                                            <div>
                                                <span class="text-xs text-muted-foreground">Appointment Type</span>
                                                <p class="text-sm">{{ pet.appointment_type }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pt-2 border-t">
                                        <Link :href="`/appointments/${appointment.id}`">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="w-full"
                                            >
                                                <Eye class="h-4 w-4 mr-2" />
                                                View Appointment Details
                                            </Button>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="!appointments || appointments.length === 0"
                            class="p-8 text-center text-muted-foreground border rounded-lg"
                        >
                            No appointments found
                        </div>
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
    </AppLayout>
</template>
