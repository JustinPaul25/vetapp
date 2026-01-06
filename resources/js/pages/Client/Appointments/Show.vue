<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Calendar, ArrowLeft, X, CalendarClock } from 'lucide-vue-next';
import { dashboard } from '@/routes';
import { ref, computed, watch } from 'vue';
import { CalendarDatePicker } from '@/components/ui/calendar-date-picker';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

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

interface Patient {
    id: number;
    pet_name: string | null;
    pet_breed: string;
    pet_gender: string | null;
    pet_birth_date: string | null;
    pet_allergies: string | null;
    pet_type: string;
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
    pet_count?: number;
}

interface Props {
    appointment: Appointment;
    patient: Patient | null;
    patients?: Patient[];
    prescription: Prescription | null;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'My Appointments', href: '/appointments' },
    { title: 'Appointment Details', href: '#' },
];

const formatDate = (dateString: string | null) => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatTime = (timeString: string | null) => {
    if (!timeString) return '—';
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

const cancelDialogOpen = ref(false);
const canceling = ref(false);
const cancelReason = ref('');
const cancelReasons = [
    'Personal reason',
    'Emergency',
    'Health related',
    'Booked incorrect date/time',
    'Other/Prefer not to say',
];
const { error: showError } = useToast();

// Reschedule functionality
const rescheduleDialogOpen = ref(false);
const rescheduling = ref(false);
const rescheduleForm = ref({
    appointment_date: '',
    appointment_time: '',
    reschedule_reason: '',
});
const rescheduleReasons = [
    'Personal reason',
    'Emergency',
    'Health related',
    'Booked incorrect date/time',
    'Other/Prefer not to say',
];
const availableTimes = ref<string[]>([]);
const loadingTimes = ref(false);
const rescheduleErrors = ref<Record<string, string[]>>({});

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

// Watch for cancel dialog open to reset reason
watch(() => cancelDialogOpen.value, (isOpen) => {
    if (isOpen) {
        cancelReason.value = '';
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

const handleReschedule = () => {
    rescheduleErrors.value = {};
    rescheduling.value = true;
    
    router.patch(
        `/appointments/${props.appointment.id}/reschedule`,
        {
            appointment_date: rescheduleForm.value.appointment_date,
            appointment_time: rescheduleForm.value.appointment_time,
            reschedule_reason: rescheduleForm.value.reschedule_reason,
        },
        {
            preserveScroll: false,
            onSuccess: () => {
                rescheduleDialogOpen.value = false;
                rescheduling.value = false;
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

const cancelAppointment = () => {
    console.log('Cancel appointment called', {
        id: props.appointment.id,
        is_approved: props.appointment.is_approved,
        is_completed: props.appointment.is_completed
    });
    
    if (!props.appointment.id) {
        console.error('Appointment ID is missing');
        return;
    }
    
    // Double check that appointment can be canceled
    if (props.appointment.is_canceled) {
        cancelDialogOpen.value = false;
        return;
    }
    
    if (props.appointment.is_approved || props.appointment.is_completed) {
        cancelDialogOpen.value = false;
        return;
    }
    
    if (!cancelReason.value) {
        showError('Please select a reason for cancellation.');
        return;
    }
    
    canceling.value = true;
    const url = `/appointments/${props.appointment.id}`;
    console.log('Sending DELETE request to:', url);
    
    // Use POST with method spoofing for DELETE to send data reliably
    axios.post(url, {
        _method: 'DELETE',
        cancel_reason: cancelReason.value,
    }, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    .then(() => {
        console.log('Appointment canceled successfully');
        cancelDialogOpen.value = false;
        canceling.value = false;
        // Reload the page to reflect changes
        router.reload();
    })
    .catch((error) => {
        canceling.value = false;
        console.error('Error canceling appointment:', error);
        const errorMessage = error.response?.data?.error || error.response?.data?.message || 'Failed to cancel appointment. Please try again.';
        showError(errorMessage);
    });
};
</script>

<template>
    <Head title="Appointment Details" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-6xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Link href="/appointments">
                                <Button variant="ghost" size="sm">
                                    <ArrowLeft class="h-4 w-4" />
                                </Button>
                            </Link>
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <Calendar class="h-5 w-5" />
                                    Appointment Details
                                    <span 
                                        v-if="appointment.pet_count && appointment.pet_count > 1"
                                        class="px-2 py-0.5 rounded-full text-xs font-semibold bg-primary/10 text-primary"
                                    >
                                        {{ appointment.pet_count }} Pet{{ appointment.pet_count !== 1 ? 's' : '' }}
                                    </span>
                                </CardTitle>
                                <CardDescription>
                                    View detailed information about your appointment
                                </CardDescription>
                            </div>
                        </div>
                        <div v-if="!appointment.is_approved && !appointment.is_completed && !appointment.is_canceled" class="flex gap-2">
                            <Dialog v-model:open="rescheduleDialogOpen">
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
                                            Choose a new date and time for your appointment
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
                            <Dialog v-model:open="cancelDialogOpen">
                                <DialogTrigger as-child>
                                    <Button variant="destructive" type="button">
                                        <X class="h-4 w-4 mr-2" />
                                        Cancel Appointment
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Cancel Appointment</DialogTitle>
                                        <DialogDescription>
                                            Are you sure you want to cancel this appointment? This action cannot be undone.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div class="space-y-4 py-4">
                                        <div class="space-y-2">
                                            <Label for="cancel_reason">Reason for Cancellation <span class="text-destructive">*</span></Label>
                                            <select
                                                id="cancel_reason"
                                                v-model="cancelReason"
                                                :disabled="canceling"
                                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                                required
                                            >
                                                <option value="">Select a reason</option>
                                                <option
                                                    v-for="reason in cancelReasons"
                                                    :key="reason"
                                                    :value="reason"
                                                >
                                                    {{ reason }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="cancelDialogOpen = false"
                                            :disabled="canceling"
                                        >
                                            No, Keep Appointment
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            @click="cancelAppointment"
                                            :disabled="canceling || !cancelReason"
                                        >
                                            <span v-if="canceling">Canceling...</span>
                                            <span v-else>Yes, Cancel Appointment</span>
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
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
                                                appointment.is_canceled
                                                    ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                    : appointment.is_completed
                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                    : appointment.is_approved
                                                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                    : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                            ]"
                                        >
                                            {{ appointment.is_canceled ? 'Canceled' : appointment.is_completed ? 'Completed' : appointment.is_approved ? 'Approved' : 'Pending' }}
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
                            <h3 class="text-lg font-semibold mb-4">Pets in this appointment:</h3>
                            <div class="grid grid-cols-1 gap-4">
                                <!-- Multiple pets displayed in separate cards -->
                                <Card
                                    v-for="(pet, index) in patients"
                                    :key="pet.id"
                                    class="border"
                                >
                                    <CardContent class="p-4">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="text-base font-semibold">{{ pet.pet_name || 'Unnamed Pet' }}</div>
                                                <div class="text-sm text-muted-foreground mt-1">
                                                    {{ pet.pet_type }} · {{ pet.pet_breed || '—' }}
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
                                    <div class="text-lg font-semibold">{{ patient.pet_breed }}</div>
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
