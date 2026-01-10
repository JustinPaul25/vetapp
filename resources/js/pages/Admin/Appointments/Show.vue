<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Calendar, ArrowLeft, CheckCircle, FileText, Download, CalendarClock, CheckCircle2 } from 'lucide-vue-next';
import { dashboard } from '@/routes';
import { ref, computed, watch } from 'vue';
import { CalendarDatePicker } from '@/components/ui/calendar-date-picker';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

interface Owner {
    id: number;
    name: string;
    email: string;
    mobile_number?: string;
}

interface Patient {
    id: number;
    pet_name: string | null;
    pet_breed: string;
    pet_gender: string | null;
    pet_birth_date: string | null;
    pet_allergies: string | null;
    pet_type: string;
    appointment_types?: string[];
    has_prescription?: boolean;
    owner: Owner | null;
}

interface Diagnosis {
    id: number;
    disease: string;
}

interface PrescriptionMedicine {
    id: number;
    medicine: string;
    dosage: string;
    instructions: string;
    quantity: string;
}

interface Prescription {
    id: number;
    symptoms: string;
    notes: string;
    pet_weight: string;
    diagnoses: Diagnosis[];
    medicines: PrescriptionMedicine[];
}

interface Appointment {
    id: number;
    appointment_type: string;
    appointment_date: string;
    appointment_time: string;
    symptoms: string;
    is_approved: boolean;
    is_completed: boolean;
    is_canceled: boolean;
    remarks: string | null;
    summary: string | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    appointment: Appointment;
    patient: Patient | null;
    patients?: Patient[];
    prescription: Prescription | null;
    medicines: Array<{
        id: number;
        name: string;
        dosage: string;
        stock: number;
    }>;
}

const props = defineProps<Props>();

const page = usePage();
const auth = computed(() => page.props.auth);
const isAdmin = computed(() => auth.value?.user?.roles?.includes('admin') ?? false);

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Appointments', href: '/admin/appointments' },
    { title: 'View Appointment', href: '#' },
];

const formatDate = (dateString: string | null) => {
    if (!dateString) return '—';
    // Parse date string (YYYY-MM-DD) as local date to avoid timezone issues
    const [year, month, day] = dateString.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatTime = (timeString: string | null) => {
    if (!timeString) return '—';
    // Check if time already contains AM/PM (already formatted)
    if (timeString.includes('AM') || timeString.includes('PM')) {
        return timeString;
    }
    // Otherwise, format from 24-hour format
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
};

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const calculateAge = (birthDate: string | null) => {
    if (!birthDate) return '—';
    const birth = new Date(birthDate);
    const today = new Date();
    let years = today.getFullYear() - birth.getFullYear();
    let months = today.getMonth() - birth.getMonth();
    if (months < 0) {
        years--;
        months += 12;
    }
    if (years > 0) {
        return `${years} year${years > 1 ? 's' : ''}`;
    }
    return `${months} month${months > 1 ? 's' : ''}`;
};

// Convert 12-hour format (h:mm AM/PM) to 24-hour format (HH:mm) for time input
const convertTo24HourForInput = (timeString: string | null): string => {
    if (!timeString) return '';
    
    // Check if already in 24-hour format (HH:mm or HH:mm:ss)
    if (/^\d{2}:\d{2}(:\d{2})?$/.test(timeString)) {
        // Extract just HH:mm
        return timeString.substring(0, 5);
    }
    
    // Check if in 12-hour format with AM/PM
    if (timeString.includes('AM') || timeString.includes('PM')) {
        const [time, period] = timeString.split(' ');
        const [hours, minutes] = time.split(':');
        let hour24 = parseInt(hours);
        
        if (period === 'PM' && hour24 !== 12) {
            hour24 += 12;
        } else if (period === 'AM' && hour24 === 12) {
            hour24 = 0;
        }
        
        return `${hour24.toString().padStart(2, '0')}:${minutes}`;
    }
    
    // If format is unknown, return empty string
    return '';
};

const approveDialogOpen = ref(false);

const approveForm = useForm({
    appointment_date: props.appointment.appointment_date,
    appointment_time: convertTo24HourForInput(props.appointment.appointment_time),
    pet_gender: props.patient?.pet_gender || '',
    pet_allergies: props.patient?.pet_allergies || '',
});

const approveAppointment = () => {
    approveForm.patch(`/admin/appointments/${props.appointment.id}/approve`, {
        onSuccess: () => {
            approveDialogOpen.value = false;
        },
    });
};

const downloadPrescription = () => {
    // Use prescription.id if available (more accurate), otherwise fall back to appointment route
    if (props.prescription?.id) {
        window.open(`/admin/prescriptions/${props.prescription.id}/download`, '_blank');
    } else {
        // Fallback for backward compatibility (gets first prescription for appointment)
        window.open(`/admin/appointments/${props.appointment.id}/prescription`, '_blank');
    }
};

// Reschedule functionality
const { showError, showSuccess } = useToast();
const rescheduleDialogOpen = ref(false);
const rescheduling = ref(false);
const rescheduleForm = ref({
    appointment_date: '',
    appointment_time: '',
    reschedule_reason: '',
});
const rescheduleReasons = [
    'Scheduling conflict',
    'Veterinarian unavailable',
    'No show',
    'Others',
];
const availableTimes = ref<string[]>([]);
const loadingTimes = ref(false);
const rescheduleErrors = ref<Record<string, string[]>>({});

// Create Prescription Dialog
const createPrescriptionDialogOpen = ref(false);

const createPrescriptionForPet = (petId: number) => {
    // Check if pet already has a prescription
    const pet = props.patients?.find(p => p.id === petId);
    if (pet?.has_prescription) {
        showError('Prescription Already Exists', `A prescription already exists for ${pet.pet_name}. Please select another pet.`);
        return;
    }
    
    router.visit(`/admin/appointments/${props.appointment.id}/prescription/create?patient_id=${petId}`);
};

// Get tomorrow's date as minimum date
const minDate = computed(() => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    return tomorrow.toISOString().split('T')[0];
});

// Watch for date changes to fetch available times
watch(() => rescheduleForm.value.appointment_date, (newDate) => {
    if (newDate) {
        fetchAvailableTimes(newDate);
    } else {
        availableTimes.value = [];
        rescheduleForm.value.appointment_time = '';
    }
});

// Watch for dialog open to initialize form
watch(() => rescheduleDialogOpen.value, (isOpen) => {
    if (isOpen) {
        // Initialize with current appointment date/time, but ensure it's not in the past
        const currentDate = props.appointment.appointment_date || '';
        const today = new Date().toISOString().split('T')[0];
        const appointmentDate = currentDate > today ? currentDate : minDate.value;
        
        rescheduleForm.value = {
            appointment_date: appointmentDate,
            appointment_time: formatTime(props.appointment.appointment_time) || '',
            reschedule_reason: '',
        };
        rescheduleErrors.value = {};
        if (rescheduleForm.value.appointment_date) {
            fetchAvailableTimes(rescheduleForm.value.appointment_date);
        }
    }
});

// Watch for approve dialog open to ensure time is properly formatted
watch(() => approveDialogOpen.value, (isOpen) => {
    if (isOpen) {
        // Update form with properly formatted time
        approveForm.appointment_time = convertTo24HourForInput(props.appointment.appointment_time);
    }
});

const fetchAvailableTimes = async (date: string) => {
    loadingTimes.value = true;
    rescheduleForm.value.appointment_time = '';
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

// Convert 12-hour format (h:mm AM/PM) to 24-hour format (HH:mm)
const convertTo24Hour = (time12h: string): string => {
    if (!time12h) return '';
    const [time, period] = time12h.split(' ');
    const [hours, minutes] = time.split(':');
    let hour24 = parseInt(hours);
    if (period === 'PM' && hour24 !== 12) {
        hour24 += 12;
    } else if (period === 'AM' && hour24 === 12) {
        hour24 = 0;
    }
    return `${hour24.toString().padStart(2, '0')}:${minutes}`;
};

const handleReschedule = () => {
    rescheduleErrors.value = {};
    rescheduling.value = true;
    
    // Convert time from 12-hour format to 24-hour format for the backend
    const time24h = convertTo24Hour(rescheduleForm.value.appointment_time);
    
    router.patch(
        `/admin/appointments/${props.appointment.id}/reschedule`,
        {
            appointment_date: rescheduleForm.value.appointment_date,
            appointment_time: time24h,
            reschedule_reason: rescheduleForm.value.reschedule_reason,
        },
        {
            preserveScroll: false,
            onSuccess: () => {
                rescheduleDialogOpen.value = false;
                rescheduling.value = false;
                showSuccess('Appointment rescheduled successfully.');
            },
            onError: (err: any) => {
                rescheduleErrors.value = (err.errors as Record<string, string[]>) || {};
                rescheduling.value = false;
                const errorMessage = err.message || 'Failed to reschedule appointment. Please check the form for errors.';
                showError(errorMessage);
            },
            onFinish: () => {
                rescheduling.value = false;
            },
        }
    );
};
</script>

<template>
    <Head title="View Appointment" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-6xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Link href="/admin/appointments">
                                <Button variant="ghost" size="sm">
                                    <ArrowLeft class="h-4 w-4" />
                                </Button>
                            </Link>
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <Calendar class="h-5 w-5" />
                                    View Appointment
                                </CardTitle>
                                <CardDescription>
                                    Appointment details and actions
                                </CardDescription>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Dialog v-if="!appointment.is_approved && !appointment.is_completed && !appointment.is_canceled" v-model:open="rescheduleDialogOpen">
                                <DialogTrigger as-child>
                                    <Button variant="outline" type="button">
                                        <CalendarClock class="h-4 w-4 mr-2" />
                                        Reschedule
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="sm:max-w-[500px]">
                                    <DialogHeader>
                                        <DialogTitle>Reschedule Appointment</DialogTitle>
                                        <DialogDescription>
                                            Choose a new date and time for this appointment
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div class="space-y-4 py-4">
                                        <div class="space-y-2">
                                            <Label for="reschedule_date">Appointment Date</Label>
                                            <CalendarDatePicker
                                                id="reschedule_date"
                                                v-model="rescheduleForm.appointment_date"
                                                :min-date="minDate"
                                                :disabled="rescheduling"
                                            />
                                            <p v-if="rescheduleErrors.appointment_date" class="text-sm text-destructive">
                                                {{ rescheduleErrors.appointment_date[0] }}
                                            </p>
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="reschedule_time">Appointment Time</Label>
                                            <select
                                                id="reschedule_time"
                                                v-model="rescheduleForm.appointment_time"
                                                :disabled="!rescheduleForm.appointment_date || loadingTimes || rescheduling"
                                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                <option value="">Select a time</option>
                                                <option
                                                    v-for="time in availableTimes"
                                                    :key="time"
                                                    :value="time"
                                                >
                                                    {{ time }}
                                                </option>
                                            </select>
                                            <p v-if="loadingTimes" class="text-sm text-muted-foreground">
                                                Loading available times...
                                            </p>
                                            <p v-if="rescheduleErrors.appointment_time" class="text-sm text-destructive">
                                                {{ rescheduleErrors.appointment_time[0] }}
                                            </p>
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="reschedule_reason">Reason for Rescheduling <span class="text-destructive">*</span></Label>
                                            <select
                                                id="reschedule_reason"
                                                v-model="rescheduleForm.reschedule_reason"
                                                :disabled="rescheduling"
                                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                                required
                                            >
                                                <option value="">Select a reason</option>
                                                <option
                                                    v-for="reason in rescheduleReasons"
                                                    :key="reason"
                                                    :value="reason"
                                                >
                                                    {{ reason }}
                                                </option>
                                            </select>
                                            <p v-if="rescheduleErrors.reschedule_reason" class="text-sm text-destructive">
                                                {{ rescheduleErrors.reschedule_reason[0] }}
                                            </p>
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="rescheduleDialogOpen = false"
                                            :disabled="rescheduling"
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="button"
                                            @click="handleReschedule"
                                            :disabled="rescheduling || !rescheduleForm.appointment_date || !rescheduleForm.appointment_time || !rescheduleForm.reschedule_reason"
                                        >
                                            <span v-if="rescheduling">Rescheduling...</span>
                                            <span v-else>Reschedule Appointment</span>
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                            <Dialog v-if="!appointment.is_approved" v-model:open="approveDialogOpen">
                                <DialogTrigger as-child>
                                    <Button>
                                        <CheckCircle class="h-4 w-4 mr-2" />
                                        Approve Appointment
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="max-w-2xl">
                                    <DialogHeader>
                                        <DialogTitle>Approve Appointment</DialogTitle>
                                        <DialogDescription>
                                            Review appointment details and approve the appointment
                                        </DialogDescription>
                                    </DialogHeader>
                                    <form @submit.prevent="approveAppointment" class="space-y-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="space-y-2">
                                                <Label for="appointment_date">Appointment Date <span class="text-destructive">*</span></Label>
                                                <Input
                                                    id="appointment_date"
                                                    v-model="approveForm.appointment_date"
                                                    type="date"
                                                    :min="new Date().toISOString().split('T')[0]"
                                                    required
                                                    disabled
                                                    class="disabled:opacity-50 disabled:cursor-not-allowed"
                                                />
                                            </div>
                                            <div class="space-y-2">
                                                <Label for="appointment_time">Appointment Time <span class="text-destructive">*</span></Label>
                                                <Input
                                                    id="appointment_time"
                                                    v-model="approveForm.appointment_time"
                                                    type="time"
                                                    required
                                                    disabled
                                                    class="disabled:opacity-50 disabled:cursor-not-allowed"
                                                />
                                            </div>
                                            <div class="space-y-2">
                                                <Label for="pet_gender">Pet Gender</Label>
                                                <select
                                                    id="pet_gender"
                                                    v-model="approveForm.pet_gender"
                                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
                                                    disabled
                                                >
                                                    <option value="">Select Gender</option>
                                                    <option value="Male">Male</option>
                                                    <option value="Female">Female</option>
                                                </select>
                                            </div>
                                            <div class="space-y-2 md:col-span-2">
                                                <Label for="pet_allergies">Pet Allergies</Label>
                                                <Input
                                                    id="pet_allergies"
                                                    v-model="approveForm.pet_allergies"
                                                    type="text"
                                                    placeholder="e.g., Peanuts, Pollen"
                                                    disabled
                                                    class="disabled:opacity-50 disabled:cursor-not-allowed"
                                                />
                                            </div>
                                        </div>
                                        <DialogFooter>
                                            <Button type="button" variant="outline" @click="approveDialogOpen = false">
                                                Cancel
                                            </Button>
                                            <Button type="submit" :disabled="approveForm.processing">
                                                Approve Appointment
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                            <!-- Create Prescription Button - Show dialog if multiple pets, direct link if single pet -->
                            <Dialog v-if="isAdmin && appointment.is_approved && !appointment.is_completed && patients && patients.length > 1" v-model:open="createPrescriptionDialogOpen">
                                <DialogTrigger as-child>
                                    <Button>
                                        <FileText class="h-4 w-4 mr-2" />
                                        Create Prescription
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Select Pet for Prescription</DialogTitle>
                                        <DialogDescription>
                                            This appointment has multiple pets. Select which pet to create a prescription for.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div class="space-y-2 py-4">
                                        <div
                                            v-for="pet in patients"
                                            :key="pet.id"
                                            :class="[
                                                'flex items-center justify-between p-3 border rounded-lg transition-colors',
                                                pet.has_prescription 
                                                    ? 'bg-green-50 dark:bg-green-950/20 border-green-300 dark:border-green-800 cursor-not-allowed opacity-75' 
                                                    : 'hover:bg-muted cursor-pointer'
                                            ]"
                                            @click="!pet.has_prescription && createPrescriptionForPet(pet.id)"
                                        >
                                            <div class="flex-1">
                                                <div class="font-medium flex items-center gap-2">
                                                    {{ pet.pet_name || 'Unnamed Pet' }}
                                                    <span
                                                        v-if="pet.has_prescription"
                                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"
                                                    >
                                                        <CheckCircle2 class="h-3 w-3" />
                                                        Already Prescribed
                                                    </span>
                                                </div>
                                                <div class="text-sm text-muted-foreground">{{ pet.pet_type }} - {{ pet.pet_breed }}</div>
                                            </div>
                                            <Button
                                                type="button"
                                                :variant="pet.has_prescription ? 'outline' : 'default'"
                                                :disabled="pet.has_prescription"
                                                size="sm"
                                                @click.stop="!pet.has_prescription && createPrescriptionForPet(pet.id)"
                                                :class="pet.has_prescription ? 'cursor-not-allowed opacity-50' : ''"
                                            >
                                                {{ pet.has_prescription ? 'Already Prescribed' : 'Create Prescription' }}
                                            </Button>
                                        </div>
                                    </div>
                                </DialogContent>
                            </Dialog>
                            <Link
                                v-else-if="isAdmin && appointment.is_approved && !appointment.is_completed"
                                :href="`/admin/appointments/${appointment.id}/prescription/create`"
                            >
                                <Button>
                                    <FileText class="h-4 w-4 mr-2" />
                                    Create Prescription
                                </Button>
                            </Link>
                            <Button
                                v-if="appointment.is_completed && prescription"
                                @click="downloadPrescription"
                            >
                                <Download class="h-4 w-4 mr-2" />
                                Download Prescription
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <!-- Appointment Details -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Appointment Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Appointment Type</Label>
                                    <div class="text-lg font-semibold">{{ appointment.appointment_type }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Status</Label>
                                    <div class="text-lg font-semibold">
                                        <span
                                            :class="[
                                                'px-2 py-1 rounded-full text-xs font-medium',
                                                appointment.is_completed
                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                    : appointment.is_approved
                                                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                    : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                            ]"
                                        >
                                            {{ appointment.is_completed ? 'Completed' : appointment.is_approved ? 'Approved' : 'Pending' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Appointment Date</Label>
                                    <div class="text-lg font-semibold">{{ formatDate(appointment.appointment_date) }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Appointment Time</Label>
                                    <div class="text-lg font-semibold">{{ formatTime(appointment.appointment_time) }}</div>
                                </div>
                                <div class="space-y-2 md:col-span-2" v-if="appointment.symptoms">
                                    <Label class="text-sm font-medium text-muted-foreground">Symptoms</Label>
                                    <div class="text-lg font-semibold">{{ appointment.symptoms || '—' }}</div>
                                </div>
                                <div class="space-y-2 md:col-span-2" v-if="appointment.remarks">
                                    <Label class="text-sm font-medium text-muted-foreground">Remarks</Label>
                                    <div class="text-lg font-semibold">{{ appointment.remarks }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Patient Details -->
                        <div v-if="patients && patients.length > 0">
                            <h3 class="text-lg font-semibold mb-4">Patient Information</h3>
                            <div class="grid grid-cols-1 gap-4">
                                <!-- Multiple pets displayed in separate cards -->
                                <Card
                                    v-for="(pet, index) in patients"
                                    :key="pet.id"
                                    class="border-2"
                                >
                                    <CardHeader class="pb-3">
                                        <CardTitle class="text-base flex items-center gap-2">
                                            <span class="text-muted-foreground">Pet {{ index + 1 }}</span>
                                            <span class="text-foreground">{{ pet.pet_name || 'Unnamed Pet' }}</span>
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div class="space-y-4">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div class="space-y-2">
                                                    <Label class="text-sm font-medium text-muted-foreground">Pet Name</Label>
                                                    <div class="text-lg font-semibold">{{ pet.pet_name || '—' }}</div>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-sm font-medium text-muted-foreground">Pet Type</Label>
                                                    <div class="text-lg font-semibold">{{ pet.pet_type }}</div>
                                                </div>
                                                <div class="space-y-2 md:col-span-2">
                                                    <Label class="text-sm font-medium text-muted-foreground">Appointment Type(s)</Label>
                                                    <div class="flex flex-wrap gap-2">
                                                        <span
                                                            v-for="(type, idx) in (pet.appointment_types || [])"
                                                            :key="idx"
                                                            class="px-3 py-1 bg-primary/10 text-primary rounded-full text-sm font-medium"
                                                        >
                                                            {{ type }}
                                                        </span>
                                                        <span v-if="!pet.appointment_types || pet.appointment_types.length === 0" class="text-lg font-semibold">—</span>
                                                    </div>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-sm font-medium text-muted-foreground">Breed</Label>
                                                    <div class="text-lg font-semibold">{{ pet.pet_breed || '—' }}</div>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-sm font-medium text-muted-foreground">Gender</Label>
                                                    <div class="text-lg font-semibold">{{ pet.pet_gender || '—' }}</div>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-sm font-medium text-muted-foreground">Birth Date</Label>
                                                    <div class="text-lg font-semibold">{{ formatDate(pet.pet_birth_date) }}</div>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-sm font-medium text-muted-foreground">Age</Label>
                                                    <div class="text-lg font-semibold">{{ calculateAge(pet.pet_birth_date) }}</div>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-sm font-medium text-muted-foreground">Allergies</Label>
                                                    <div class="text-lg font-semibold">{{ pet.pet_allergies || '—' }}</div>
                                                </div>
                                            </div>
                                            <!-- Owner Information for each pet -->
                                            <div v-if="pet.owner" class="pt-4 border-t">
                                                <h4 class="text-sm font-semibold mb-3 text-muted-foreground">Owner Information</h4>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                    <div class="space-y-2">
                                                        <Label class="text-sm font-medium text-muted-foreground">Owner Name</Label>
                                                        <div class="text-lg font-semibold">{{ pet.owner.name }}</div>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label class="text-sm font-medium text-muted-foreground">Email</Label>
                                                        <div class="text-lg font-semibold">{{ pet.owner.email }}</div>
                                                    </div>
                                                    <div class="space-y-2" v-if="pet.owner.mobile_number">
                                                        <Label class="text-sm font-medium text-muted-foreground">Mobile Number</Label>
                                                        <div class="text-lg font-semibold">{{ pet.owner.mobile_number }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                        <!-- Fallback for backward compatibility with single patient -->
                        <div v-else-if="patient">
                            <h3 class="text-lg font-semibold mb-4">Patient Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Pet Name</Label>
                                    <div class="text-lg font-semibold">{{ patient.pet_name || '—' }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Pet Type</Label>
                                    <div class="text-lg font-semibold">{{ patient.pet_type }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Breed</Label>
                                    <div class="text-lg font-semibold">{{ patient.pet_breed || '—' }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Gender</Label>
                                    <div class="text-lg font-semibold">{{ patient.pet_gender || '—' }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Birth Date</Label>
                                    <div class="text-lg font-semibold">{{ formatDate(patient.pet_birth_date) }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Age</Label>
                                    <div class="text-lg font-semibold">{{ calculateAge(patient.pet_birth_date) }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Allergies</Label>
                                    <div class="text-lg font-semibold">{{ patient.pet_allergies || '—' }}</div>
                                </div>
                            </div>
                            
                            <!-- Owner Information -->
                            <div v-if="patient.owner" class="mt-6">
                                <h3 class="text-lg font-semibold mb-4">Owner Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium text-muted-foreground">Owner Name</Label>
                                        <div class="text-lg font-semibold">{{ patient.owner.name }}</div>
                                    </div>
                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium text-muted-foreground">Email</Label>
                                        <div class="text-lg font-semibold">{{ patient.owner.email }}</div>
                                    </div>
                                    <div class="space-y-2" v-if="patient.owner.mobile_number">
                                        <Label class="text-sm font-medium text-muted-foreground">Mobile Number</Label>
                                        <div class="text-lg font-semibold">{{ patient.owner.mobile_number }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Appointment Summary -->
                        <div v-if="appointment.is_completed && appointment.summary">
                            <h3 class="text-lg font-semibold mb-4">Appointment Summary</h3>
                            <Card>
                                <CardContent class="p-6">
                                    <pre class="whitespace-pre-wrap text-sm font-mono bg-muted p-4 rounded-md">{{ appointment.summary }}</pre>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Prescription Details -->
                        <div v-if="prescription">
                            <h3 class="text-lg font-semibold mb-4">Prescription Details</h3>
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium text-muted-foreground">Symptoms</Label>
                                        <div class="text-lg font-semibold">{{ prescription.symptoms || '—' }}</div>
                                    </div>
                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium text-muted-foreground">Pet Weight</Label>
                                        <div class="text-lg font-semibold">{{ prescription.pet_weight }} kg</div>
                                    </div>
                                    <div class="space-y-2 md:col-span-2" v-if="prescription.notes">
                                        <Label class="text-sm font-medium text-muted-foreground">Notes</Label>
                                        <div class="text-lg font-semibold">{{ prescription.notes }}</div>
                                    </div>
                                </div>

                                <div v-if="prescription.diagnoses.length > 0">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2 block">Diagnoses</Label>
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="diagnosis in prescription.diagnoses"
                                            :key="diagnosis.id"
                                            class="px-3 py-1 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded-full text-sm font-medium"
                                        >
                                            {{ diagnosis.disease }}
                                        </span>
                                    </div>
                                </div>

                                <div v-if="prescription.medicines.length > 0">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2 block">Medicines</Label>
                                    <div class="overflow-x-auto">
                                        <table class="w-full border-collapse">
                                            <thead>
                                                <tr class="border-b">
                                                    <th class="text-left p-3 font-semibold">Medicine</th>
                                                    <th class="text-left p-3 font-semibold">Dosage</th>
                                                    <th class="text-left p-3 font-semibold">Quantity</th>
                                                    <th class="text-left p-3 font-semibold">Instructions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="medicine in prescription.medicines"
                                                    :key="medicine.id"
                                                    class="border-b hover:bg-muted/50"
                                                >
                                                    <td class="p-3">{{ medicine.medicine }}</td>
                                                    <td class="p-3">{{ medicine.dosage }}</td>
                                                    <td class="p-3">{{ medicine.quantity }}</td>
                                                    <td class="p-3">{{ medicine.instructions }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Metadata -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t">
                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-muted-foreground">Created At</Label>
                                <div class="text-sm text-muted-foreground">
                                    {{ formatDateTime(appointment.created_at) }}
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-muted-foreground">Updated At</Label>
                                <div class="text-sm text-muted-foreground">
                                    {{ formatDateTime(appointment.updated_at) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>




